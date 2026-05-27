<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-white dark:bg-zinc-900 antialiased text-zinc-950 dark:text-white text-base/6 sm:text-sm/6">
        <header>
            <nav class="flex items-end flex-wrap py-5">
                <div class="lg:w-64 lg:justify-end px-4 flex gap-x-3 flex-wrap">
                    <a href="{{ route('dashboard') }}" class="text-zinc-500 dark:text-zinc-400" wire:navigate>
                        {{ Str::of(config('app.name'))->explode('-', 4)->last() }}
                        <sup>{{ Str::of(config('app.name'))->explode('-', 4)->take(3)->join('-') }}</sup>
                    </a>
                    <a href="{{ route('dashboard') }}" class="text-blue-700 visited:text-purple-700 dark:text-blue-400 dark:visited:text-purple-400 hover:underline" wire:navigate>{{ Auth::user()->currentTeam->name }}</a>
                    <a href="{{ route('teams.switch') }}" class="text-blue-700 visited:text-purple-700 dark:text-blue-400 dark:visited:text-purple-400 hover:underline" wire:navigate>switch team</a>
                </div>

                <div class="flex-1 flex-wrap flex px-4">
                    <div class="flex gap-x-3">
                        <a href="{{ route('rooms.index', ['current_team' => Auth::user()->currentTeam->slug]) }}" class="text-base/6 sm:text-sm/6 hover:underline text-blue-700 visited:text-purple-700 dark:text-blue-400 dark:visited:text-purple-400 lowercase" wire:navigate>rooms</a>
                        <a href="{{ route('teams.settings', Auth::user()->currentTeam->slug) }}" class="text-base/6 sm:text-sm/6 hover:underline text-blue-700 visited:text-purple-700 dark:text-blue-400 dark:visited:text-purple-400 lowercase" wire:navigate>settings</a>
                    </div>

                    <div aria-hidden="true" class="flex-1"></div>

                    <div class="flex gap-x-1.5">
                        logged in as <a href="{{ route('settings') }}" class="hover:underline text-blue-700 visited:text-purple-700 dark:text-blue-400 dark:visited:text-purple-400 lowercase" wire:navigate>{{ Auth::user()->email }}</a>
                        <span x-data="{ notificationStatus: 'loading' }" x-init="
                            if (window.getSubscriptionStatus) {
                                window.getSubscriptionStatus().then(function(status) { notificationStatus = status; });
                            }
                        " class="flex gap-x-1.5">
                            <template x-if="notificationStatus === 'unsubscribed'">
                                <flux:button size="xs" variant="filled" @click="
                                    if (window.subscribeToPush) {
                                        window.subscribeToPush().then(function(success) {
                                            if (success) { notificationStatus = 'subscribed'; } else { notificationStatus = 'unsubscribed'; }
                                        });
                                    }
                                " class="lowercase">enable notifications</flux:button>
                            </template>
                            <template x-if="notificationStatus === 'subscribed'">
                                <flux:badge>notifications on</flux:badge>
                            </template>
                            <template x-if="notificationStatus === 'denied'">
                                <flux:badge color="red">notifications blocked</flux:badge>
                            </template>
                            <template x-if="notificationStatus === 'unsupported'">
                                <flux:badge color="zinc">push not supported</flux:badge>
                            </template>
                        </span>
                        <form method="POST" action="{{ route('logout') }}" class="inline-flex">
                            @csrf
                            <flux:button size="xs" variant="filled" type="submit" class="lowercase">logout</flux:button>
                        </form>
                    </div>
                </div>
            </nav>
        </header>

        <main class="lg:pl-64">
            <div class="p-4 pt-0">
                <div class="w-full max-w-6xl">
                    {{ $slot }}
                </div>
            </div>
        </main>

        @persist('toast')
            <flux:toast.group position="bottom center">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
