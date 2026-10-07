<script setup>
defineProps({ paused: { type: Boolean, default: false } });
</script>
<template><div class="hero-orbs" aria-hidden="true" :class="{ 'orbs-paused': paused }"><span class="hero-orb orb-violet"></span><span class="hero-orb orb-lilac"></span><span class="hero-orb orb-rose"></span></div></template>
<style scoped>
.hero-orbs { display: none; }
@media (min-width: 1024px) {
    .hero-orbs {
        display: block;
        position: absolute;
        inset: 0 auto 0 0;
        width: 52%;
        z-index: -1;
        overflow: hidden;
        pointer-events: none;
        mask-image: linear-gradient(to right, #000 65%, transparent);
    }
    .hero-orb {
        position: absolute;
        width: clamp(10rem, 20vw, 23rem);
        aspect-ratio: 1;
        border-radius: 50%;
        filter: blur(clamp(28px, 3vw, 54px));
        left: 5%; top: 18%;
        opacity: .27;
        animation: orb-drift 16s ease-in-out infinite;
    }
    .orb-violet {
        background: radial-gradient(circle at 35% 35%, #a78bfa, #7c3aed 55%, transparent 75%);
    }
    .orb-lilac {
        width: clamp(9rem, 16vw, 19rem);
        background: radial-gradient(circle at 40% 40%, #c4b5fd, #818cf8 55%, transparent 75%);
        animation-duration: 19s;
        animation-delay: -6s;
        animation-direction: reverse;
    }
    .orb-rose {
        background: radial-gradient(circle at 40% 35%, #e9a8d8, #a855f7 55%, transparent 75%);
        animation-duration: 17s;
        animation-delay: -11s;
    }
    .orbs-paused .hero-orb { animation-play-state: paused; }
}
@keyframes orb-drift {
    0%, 100% { transform: translate3d(0, 0, 0) scale(.9); }
    25% { transform: translate3d(17vw, 12vh, 0) scale(1.08); }
    50% { transform: translate3d(6vw, 38vh, 0) scale(.95); }
    75% { transform: translate3d(-5vw, 22vh, 0) scale(1.04); }
}
@media (prefers-reduced-motion: reduce) { .hero-orb { animation: none; } }
</style>
