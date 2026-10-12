<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeSwitcher from '@/Components/ThemeSwitcher.vue';
defineProps({ mobile: Boolean });
const page = usePage();
const workspace = computed(() => page.props.auth.user.role === 'admin'
    ? [{ label: 'Admin Settings', route: 'admin.settings' }]
    : page.props.auth.user.role === 'counselor' ? [{ label: 'Earnings', route: 'earnings' }] : []);
const readings = computed(() => [
    { label: 'Daily card', route: 'tarot.index' },
    ...(page.props.features?.birthChart ? [{ label: 'Birth chart', route: 'birth-chart' }] : []),
    { label: 'Reading feedback', route: 'reviews.index' },
]);
</script>
<template>
    <div class="profile-menu max-h-[calc(100dvh-7rem)] overflow-y-auto overscroll-contain text-sm">
        <section aria-label="My account" class="py-1">
            <p class="px-4 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-muted">My account</p>
            <component :is="mobile ? ResponsiveNavLink : DropdownLink" :href="route('profile.edit')">Profile</component>
            <details @click.stop>
                <summary class="cursor-pointer px-4 py-2 text-sm text-content hover:bg-surface-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">Appearance</summary>
                <ThemeSwitcher />
            </details>
            <component v-if="page.props.auth.user.role === 'user' && page.props.coachSite?.applications_open" :is="mobile ? ResponsiveNavLink : DropdownLink" :href="route('counselor.apply')">Become a Spiritual Coach</component>
        </section>
        <section aria-label="My readings" class="border-t border-border py-1">
            <p class="px-4 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-muted">My readings</p>
            <component v-for="item in readings" :key="item.route" :is="mobile ? ResponsiveNavLink : DropdownLink" :href="route(item.route)">{{ item.label }}</component>
        </section>
        <section v-if="workspace.length" aria-label="Workspace" class="border-t border-border py-1">
            <p class="px-4 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-muted">Workspace</p>
            <component v-for="item in workspace" :key="item.route" :is="mobile ? ResponsiveNavLink : DropdownLink" :href="route(item.route)">{{ item.label }}</component>
        </section>
        <section aria-label="Help" class="border-t border-border py-1">
            <p class="px-4 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-widest text-muted">Help</p>
            <component :is="mobile ? ResponsiveNavLink : DropdownLink" :href="route('support.index')">Support</component>
        </section>
        <div class="border-t border-border py-1"><component :is="mobile ? ResponsiveNavLink : DropdownLink" :href="route('logout')" method="post" as="button">Log out</component></div>
    </div>
</template>
