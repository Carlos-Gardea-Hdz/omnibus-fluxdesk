<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="h-full antialiased"
>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        <title>{{ $title ?? config('app.name') }}</title>

        {{--
            Theme FOUC guard: set the `.dark` class synchronously BEFORE first
            paint, from localStorage, falling back to the OS preference
            (ui-ux-tailwind4 §3). Never set theme from a client effect alone.
        --}}
        <script>
            (function () {
                try {
                    const stored = localStorage.getItem('fluxdesk.appearance');
                    const system = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const dark = stored === 'dark' || ((!stored || stored === 'system') && system);
                    document.documentElement.classList.toggle('dark', dark);
                } catch (e) {}
            })();
        </script>

        @fluxAppearance

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-full flex-col bg-surface text-text">
        {{-- Skip link for keyboard users (WCAG 2.4.1). --}}
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-accent focus:px-4 focus:py-2 focus:text-accent-contrast"
        >
            {{ __('a11y.skip_to_content') }}
        </a>

        <main id="main" class="flex-1" tabindex="-1">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>
