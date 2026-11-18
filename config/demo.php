<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Accounts
    |--------------------------------------------------------------------------
    |
    | The accounts `php artisan db:seed` creates, so each environment can list
    | its own: comma-separated "email:name:credits" entries, where credits are
    | whole dollars, with ":admin" appended for admins. Every account gets the
    | same password. When no accounts are listed, seeding creates none.
    |
    */

    'users' => env('DEMO_USERS', ''),

    'password' => env('DEMO_USERS_PASSWORD'),

];
