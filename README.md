# Petly

A Laravel 13, Inertia 3 and Vue 3 app where users generate images through the
[Pixelforge API](https://pixelforge.promptedit.dev/docs), see what each image will
cost before they submit, and are charged exactly what it cost (plus a 25% markup)
once it succeeds.

## Running it locally

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # seeds the DEMO_USERS accounts, e.g. admin@example.com / 11112222
npm run build
composer run dev             # Horizon, scheduler, logs and Vite
```

Queues run on Redis through [Horizon](https://laravel.com/docs/horizon), so Redis
must be running locally. `.env.example` uses the Predis client, which needs no PHP
extension; set `REDIS_CLIENT=phpredis` if you have the `redis` extension.

Create a Pixelforge API key for local development and put it in `.env`:

```dotenv
PIXELFORGE_API_KEY=pf_...
PIXELFORGE_WEBHOOK_SECRET=whsec_...
```

Webhooks need a public URL, so expose the app with a tunnel (`valet share`,
ngrok, Cloudflare Tunnel) and set the key's webhook URL to
`https://<tunnel>/webhooks/pixelforge`. Without webhooks the app still works:
the scheduler polls for generations it hasn't heard about.

`php artisan app:make-admin someone@example.com` gives a user the admin report and,
outside local development, the Horizon dashboard at `/horizon`.

### Demo accounts

`php artisan db:seed` creates the accounts listed in `DEMO_USERS`, so each environment
can have its own. Entries are comma-separated `email:name:credits`, where credits
are whole dollars, with `:admin` appended for admins. They all get
`DEMO_USERS_PASSWORD`:

```dotenv
DEMO_USERS="reviewer@example.com:Reviewer:20:admin,guest@example.com:Guest:5"
DEMO_USERS_PASSWORD=a-long-random-password
```

Seeding is safe to re-run. Existing accounts keep their credits and only get their
password reset, so changing `DEMO_USERS_PASSWORD` and seeding again rotates it.
With `DEMO_USERS` empty, nothing is seeded. A malformed entry stops the seeder before
it creates any account.

## Deploying

Any Laravel host works; on Laravel Cloud:

1. Create a separate Pixelforge API key for production, with its webhook URL set to
   `https://<your-app>/webhooks/pixelforge`.
2. Set `PIXELFORGE_API_KEY`, `PIXELFORGE_WEBHOOK_SECRET` and a database.
3. Build with `composer install --no-dev && npm ci && npm run build`, deploy with
   `php artisan migrate --force`.
4. Attach a Redis-compatible cache (Laravel Valkey), set `QUEUE_CONNECTION=redis`,
   add a background process running `php artisan horizon` (in place of a queue
   worker or Cloud's managed queues), and enable the scheduler.
5. Attach a **private** Object Storage bucket as the environment's default disk, then
   redeploy. Images are copied to the default filesystem disk, and Cloud's local disk
   is wiped on each deploy. The S3 adapter (`league/flysystem-aws-s3-v3`) is already
   installed. Images are served through the app, so the bucket never needs to be
   public.
6. Optionally set the server's own `DEMO_USERS` and a strong `DEMO_USERS_PASSWORD`
   (see [Demo accounts](#demo-accounts)), then run `php artisan db:seed --force` once.
   The repository is public, so never reuse the local password there.

## How it works

### Money is integers of nanodollars

Pixelforge reports costs to nine decimals, so every amount is stored as an integer
number of nanodollars (`App\Support\Money`). Sums are exact, in PHP and in SQL. The
API's decimal strings are parsed without floats, and the frontend formats the
server's decimal strings with `BigInt`.

`App\Services\Billing` holds all the arithmetic:

- **Provider cost** = Σ tokens × rate, using the `rates` on the generation itself
  (fixed when Pixelforge created it), not today's prices.
- **User price** = provider cost × 1.25, rounded up to the next nanodollar. With
  Pixelforge's rates it's always exact.

Users see prices to four decimals ($0.0583); hovering shows the exact amount. The
admin report shows full precision.

### Estimates, and never charging more than quoted

Text tokens (±15% of `ceil(characters / 4)`) and reasoning tokens (a range per
quality) are only known once an image finishes, so the form shows a **range**,
computed from `GET /v1/pricing` (cached for 5 minutes and served stale for up to an
hour while it refreshes). The top of the range assumes the most tokens possible, so
the actual price can't exceed it. Characters are counted as Unicode code points on
both sides, as Pixelforge does.

### Credits

Credits live in an append-only ledger (`credit_transactions`): purchases are
positive rows, charges negative, and a balance is the sum. Unfinished generations
**hold** their maximum price, so a user can only start what they can pay for. The
user row is locked while checking, so two quick submissions can't spend the same
credits. "Buy credits" adds a ledger row; no payment is taken.

### Submitting: a queued job with an idempotency key

Saving a generation and calling Pixelforge are separate steps. The request saves
the generation as `pending` and queues `SubmitGenerationToPixelforge`, which:

- sends the generation's UUID as the `Idempotency-Key`, so retrying after a timeout
  never creates or bills a second generation;
- puts the same UUID in `metadata`, so a webhook can be matched even if it arrives
  before the create request returns;
- retries rate limits, the 3-at-a-time concurrency limit, overloads, 5xx and
  network errors after `Retry-After`, backing off further each time (2s, 4s, 8s…,
  capped at 60s), and gives up after 10 attempts. While waiting, the card says why;
- fails immediately, for free, on content-policy rejections, validation errors and
  the daily quota.

If the queue is down when a generation is saved, the request still succeeds: the user
is told the image will start in a few minutes (rather than seeing an error and
submitting, and paying, twice), and `pixelforge:sync` re-queues any generation that
has sat pending with no job touching it for 5 minutes. A duplicate job, should the
first only be slow, is harmless: it sends the same idempotency key.

### Status updates: webhooks, polling, and only moving forward

The create response, webhooks and polling all go through one method,
`GenerationSynchronizer::apply()`:

- It locks the generation row, so concurrent updates are applied one at a time.
- A generation only moves to a later status (pending → queued → processing →
  succeeded/failed) and never leaves a final one, so duplicated and out-of-order
  events can't undo anything.
- On success it records the token usage, cost and price, and charges the user in
  the same transaction. The ledger has a unique index on `generation_id`, so a
  generation can't be charged twice.
- If we gave up before Pixelforge confirmed it had accepted a generation (every
  attempt timed out), Pixelforge's word overrides our failure, since it may have
  been created after all.

Webhooks have their signature checked (HMAC-SHA256 of `timestamp.body`, compared in
constant time, at most 5 minutes old). Each event id is stored in `webhook_events`
with a unique index, in the same transaction as applying it: a redelivery is a no-op,
and if applying fails nothing is stored and Pixelforge retries.

Delivery isn't guaranteed, so `pixelforge:sync` runs every minute and polls any
generation that has been queued or processing without news for 2 minutes.

### Images outlive Pixelforge's links

Pixelforge's image links stop working 24 hours after an image is made. Once a
generation succeeds (and that commits), `StoreGenerationImage` copies the PNG to the
default filesystem disk, and the history serves it from
`/generations/{id}/image`, to its owner only. Until the copy is saved, or if it
goes missing, the history uses Pixelforge's link while it still works.

### Admin report

`/admin/report` shows credit spend, provider cost and profit per UTC day (by
completion date, the way Pixelforge bills), per user, and per day and user. Each day
is compared with `GET /v1/usage` for this environment's API key and flagged if it
doesn't match to the nanodollar.

## Tests

```bash
php artisan test
```

They cover the cost arithmetic (including the docs' worked examples), estimates, holds,
webhook signatures, duplicate and out-of-order events, retries and backoff, failure
handling, polling, image copies and the admin report. Pixelforge's test keywords (`[fail]`, `[slow]`,
`[duplicate]`, `[blocked]`, `[overloaded]`) exercise the same paths against the real API.
