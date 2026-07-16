<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const onlineCounselors = ref([]);

onMounted(() => {
    // Join the presence channel
    window.Echo.join('counselors.online')
        // .here() fires immediately with an array of everyone currently in the channel
        .here((users) => {
            onlineCounselors.value = users;
        })
        // .joining() fires when a new counselor logs in
        .joining((user) => {
            onlineCounselors.value.push(user);
        })
        // .leaving() fires when a counselor closes their browser or logs out
        .leaving((user) => {
            onlineCounselors.value = onlineCounselors.value.filter(u => u.id !== user.id);
        });
});

// Clean up the listener when the component unmounts to prevent memory leaks
onUnmounted(() => {
    window.Echo.leave('counselors.online');
});
</script>

<template>
    <div>
        <h2>Available Counselors</h2>
        <ul>
            <li v-for="counselor in onlineCounselors" :key="counselor.id">
                {{ counselor.name }} is Online!
            </li>
        </ul>
    </div>
</template>