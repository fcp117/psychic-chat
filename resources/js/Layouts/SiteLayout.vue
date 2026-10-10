<script setup>
import SiteFooter from '@/Components/SiteFooter.vue';
import SiteAssistant from '@/Components/CorpusAssistant.vue';
import RecoveryNotice from '@/Components/RecoveryNotice.vue';
</script>

<template>
    <div>
        <slot />
        <SiteFooter />
        <SiteAssistant />
        <RecoveryNotice />
    </div>
</template>
