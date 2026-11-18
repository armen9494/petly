<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Until the app's JavaScript arrives, #app is empty and the page would be blank,
             which on a slow connection (or the ngrok tunnel) can last seconds. Show a small
             spinner instead. It's inline because the stylesheet comes over the same slow
             connection, and it fades in late so a fast load never shows it. --}}
        <style>
            #app:empty {
                display: grid;
                min-height: 100vh;
                place-items: center;
            }

            #app:empty::before {
                content: '';
                width: 2rem;
                height: 2rem;
                border: 3px solid #ddd6fe;
                border-top-color: #7c3aed;
                border-radius: 9999px;
                opacity: 0;
                animation: app-loading-appear 0.2s 0.4s forwards, app-loading-spin 0.8s linear infinite;
            }

            @media (prefers-reduced-motion: reduce) {
                #app:empty::before {
                    animation: app-loading-appear 0.2s 0.4s forwards;
                }
            }

            @keyframes app-loading-appear {
                to { opacity: 1; }
            }

            @keyframes app-loading-spin {
                to { transform: rotate(360deg); }
            }
        </style>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
