<script setup>
import { ref, computed } from 'vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import NotificationCenter from '@/Components/NotificationCenter.vue';
import ThemeSwitcher from '@/Components/ThemeSwitcher.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const navigation = computed(() => [
    { label: 'Home', route: 'home', match: 'home' },
    { label: 'Chat', route: 'chat.index', match: 'chat.*' },
    { label: 'Forecast', route: 'forecast', match: 'forecast' },
    { label: 'Credits', route: 'credits', match: 'credits' },
    { label: 'About', route: 'about', match: 'about' },
    ...(page.props.auth.user.role === 'counselor' ? [{ label: 'Earnings', route: 'earnings', match: 'earnings' }] : []),
    ...(page.props.auth.user.role === 'admin' ? [{ label: 'Admin Settings', route: 'admin.settings', match: 'admin.*' }] : []),
]);

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="app-shell min-h-screen bg-page">
            <nav class="sticky top-0 z-50 bg-transparent px-3 py-4 sm:px-5" aria-label="Main navigation">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl">
                    <div class="relative flex min-h-14 items-center justify-between rounded-2xl border border-border bg-surface px-4 shadow-lg shadow-primary/10 sm:px-5">
                        <div class="flex shrink-0">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('home')" class="flex items-center gap-2" aria-label="Intuition Island home">
                                    <ApplicationLogo
                                        class="block h-8 w-8 text-primary"
                                    />
                                    <span class="font-serif text-lg tracking-wide text-content">Intuition Island</span>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="absolute left-1/2 hidden -translate-x-1/2 items-center gap-1 lg:flex">
                                <Link v-for="item in navigation" :key="item.route" :href="route(item.route)" class="rounded-full px-3 py-2 text-sm font-medium transition" :class="route().current(item.match) || (item.route === 'chat.index' && route().current('psychics.*')) ? 'bg-accent-soft text-accent-text' : 'text-muted hover:bg-surface-hover hover:text-content'">{{ item.label }}</Link>
                            </div>
                        </div>

                        <div class="ml-auto mr-2 flex shrink-0 items-center gap-3 lg:mr-0 lg:pl-4">
                            <NotificationCenter />
                        </div>
                        <div class="hidden lg:ms-3 lg:flex lg:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-surface px-3 py-2 text-sm font-medium leading-4 text-muted transition duration-150 ease-in-out hover:text-content focus:outline-none"
                                            >
                                                <UserAvatar :user="$page.props.auth.user" class="mr-2 h-8 w-8 text-xs align-middle" />{{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink v-if="page.props.auth.user.role === 'user'" :href="route('counselor.apply')">Become a Spiritual Advisor</DropdownLink>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink :href="route('terms')">Terms of Service</DropdownLink>
                                        <DropdownLink :href="route('privacy')">Privacy Notice</DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                        <ThemeSwitcher />
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center lg:hidden">
                            <button type="button" aria-label="Toggle navigation" :aria-expanded="showingNavigationDropdown"
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-muted transition duration-150 ease-in-out hover:bg-surface-hover hover:text-muted focus:bg-surface-hover focus:text-muted focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="lg:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink v-for="item in navigation" :key="item.route" :href="route(item.route)" :active="route().current(item.match) || (item.route === 'chat.index' && route().current('psychics.*'))">{{ item.label }}</ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-border pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-content "
                            >
                                <UserAvatar :user="$page.props.auth.user" class="mr-2 h-8 w-8 text-xs align-middle" />{{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-muted ">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink v-if="page.props.auth.user.role === 'user'" :href="route('counselor.apply')">Become a Spiritual Advisor</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('terms')">Terms of Service</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('privacy')">Privacy Notice</ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                            <ThemeSwitcher />
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="border-b border-border bg-surface"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="app-content">
                <slot />
            </main>
        </div>
    </div>
</template>
