<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-white dark:bg-zinc-900 antialiased text-zinc-950 dark:text-white text-base/6 sm:text-sm/6">
        <header class="sticky top-0 bg-white dark:bg-zinc-900 z-10">
            <nav class="flex items-end flex-wrap py-5">
                <div class="lg:w-64 lg:text-right px-4 gap-x-3 text-zinc-500 dark:text-zinc-400">
                    <a href="{{ route('dashboard', ['current_team' => Auth::user()->currentTeam]) }}" wire:navigate>
                        {{ config('app.name') }}
                    </a>
                    (<flux:link :href="route('dashboard', ['current_team' => Auth::user()->currentTeam])" wire:navigate :accent="false">{{ Auth::user()->currentTeam->name }}</flux:link>)
                    <flux:button size="xs" variant="filled" :href="route('teams.switch')" wire:navigate class="lowercase">switch team</flux:button>
                </div>

                <div class="w-full lg:flex-1 flex-wrap flex px-4 gap-x-3 md:justify-between">
                    <div class="flex gap-x-3">
                        @if (Auth::user()->currentTeam)
                            <flux:link :href="route('rooms.index', ['current_team' => Auth::user()->currentTeam])" class="lowercase" wire:navigate :accent="false" :variant="request()->routeIs('rooms.*') ? null : 'ghost'">rooms</flux:link>
                            <flux:link :href="route('teams.settings', Auth::user()->currentTeam)" class="lowercase" wire:navigate :accent="false" :variant="request()->routeIs('teams.settings') ? null : 'ghost'">settings</flux:link>
                        @endif
                    </div>

                    <div aria-hidden="true" class="flex-1"></div>

                    <div>
                        logged in as <flux:link :href="route('settings')" class="lowercase" wire:navigate :accent="false">{{ Auth::user()->email }}</flux:link>
                        <span x-data="{ notificationStatus: 'loading' }" x-init="
                            if (window.getSubscriptionStatus) {
                                window.getSubscriptionStatus().then(function(status) { notificationStatus = status; });
                            }
                        ">
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
