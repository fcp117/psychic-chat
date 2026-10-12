<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import PageBackdrop from '@/Components/PageBackdrop.vue';
import NotificationCenter from '@/Components/NotificationCenter.vue';
import ProfileMenu from '@/Components/ProfileMenu.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const navigation = computed(() => [
    { label: 'Home', route: 'home', match: 'home' },
    { label: 'Readings', route: 'chat.index', match: 'chat.*' },
    ...(page.props.features?.forecast ? [{ label: 'Moonoscope', route: 'forecast', match: 'forecast' }] : []),
    { label: 'Minutes', route: 'credits', match: 'credits' },
    { label: 'About', route: 'about', match: 'about' },
]);

const showingNavigationDropdown = ref(false);
const navigationToggle = ref(null);
let savedScroll = 0, savedStyle = null, desktopQuery;
const unlockPage = () => {
    if (!savedStyle) return;
    Object.assign(document.body.style, savedStyle);
    savedStyle = null;
    window.scrollTo({ top: savedScroll, behavior: 'instant' });
};
watch(showingNavigationDropdown, open => {
    if (!open) { unlockPage(); return; }
    savedScroll = window.scrollY;
    savedStyle = Object.fromEntries(['position','top','left','right','width','overflow'].map(key => [key, document.body.style[key]]));
    Object.assign(document.body.style, { position: 'fixed', top: `-${savedScroll}px`, left: '0', right: '0', width: '100%', overflow: 'hidden' });
});
const closeNavigation = () => { showingNavigationDropdown.value = false; };
const escapeNavigation = event => {
    if (event.key === 'Escape' && showingNavigationDropdown.value) {
        closeNavigation(); navigationToggle.value?.focus();
    }
};
const desktopNavigation = () => { if (desktopQuery.matches) closeNavigation(); };
watch(() => page.url, closeNavigation);
onMounted(() => {
    desktopQuery = window.matchMedia('(min-width: 1536px)');
    desktopQuery.addEventListener('change', desktopNavigation);
    document.addEventListener('keydown', escapeNavigation);
});
onUnmounted(() => {
    unlockPage();
    desktopQuery?.removeEventListener('change', desktopNavigation);
    document.removeEventListener('keydown', escapeNavigation);
});
</script>

<template>
    <div>
        <div class="app-shell relative isolate min-h-screen" :class="page.component === 'Dashboard' ? 'bg-page' : 'shared-portrait-shell'">
            <PageBackdrop v-if="page.component !== 'Dashboard'" />
            <nav class="z-50 bg-transparent px-3 py-4 sm:px-5" :class="[page.component.startsWith('Admin/') ? 'relative' : 'sticky top-0', { 'mobile-navigation-open': showingNavigationDropdown }]" aria-label="Main navigation">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto flex max-w-7xl items-center gap-2 sm:gap-3">
                    <div class="relative flex min-h-14 min-w-0 flex-1 items-center justify-between rounded-2xl border border-border bg-surface px-4 shadow-lg shadow-primary/10 sm:px-5">
                        <div class="flex min-w-0 flex-1 items-center gap-4">
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
                            <div class="hidden min-w-0 flex-1 items-center justify-center gap-1 2xl:flex">
                                <Link v-for="item in navigation" :key="item.route" :href="route(item.route)" class="shrink-0 whitespace-nowrap rounded-full px-2.5 py-2 text-sm font-medium transition" :class="route().current(item.match) || (item.route === 'chat.index' && (route().current('psychics.*') || route().current('bookings.*'))) ? 'bg-accent-soft text-accent-text' : 'text-muted hover:bg-surface-hover hover:text-content'">{{ item.label }}</Link>
                            </div>
                        </div>

                        <div class="hidden 2xl:ms-3 2xl:flex 2xl:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-surface px-3 py-2 text-sm font-medium leading-4 text-muted transition duration-150 ease-in-out hover:text-content focus:outline-none"
                                            >
                                                <UserAvatar :user="$page.props.auth.user" class="mr-2 h-8 w-8 shrink-0 text-xs align-middle" /><span class="max-w-28 truncate">{{ $page.props.auth.user.name }}</span>

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
                                        <ProfileMenu />
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center 2xl:hidden">
                            <button ref="navigationToggle" type="button" aria-label="Toggle navigation" aria-controls="mobile-navigation-menu" :aria-expanded="showingNavigationDropdown"
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
                    <NotificationCenter class="shrink-0" />
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    id="mobile-navigation-menu"
                    class="mobile-navigation-menu 2xl:hidden"
                    @click="event => { if (event.target.closest('a') || event.target.closest('button[type=submit]')) closeNavigation(); }"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink v-for="item in navigation" :key="item.route" :href="route(item.route)" :active="route().current(item.match) || (item.route === 'chat.index' && (route().current('psychics.*') || route().current('bookings.*')))">{{ item.label }}</ResponsiveNavLink>
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

                        <div class="mt-3"><ProfileMenu mobile /></div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="border-b border-border bg-surface"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 2xl:px-6 2xl:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="app-content" :inert="showingNavigationDropdown">
                <slot />
            </main>
        </div>
    </div>
</template>
