<template>
    <div class="max-w-4xl mx-auto p-4 flex flex-col h-screen">
        <div class="bg-white p-4 shadow mb-4">
            <h2 class="text-xl font-bold">Live Reading</h2>
        </div>

        <!-- Chat History -->
        <div class="flex-1 overflow-y-auto bg-gray-100 p-4 rounded-lg shadow-inner flex flex-col space-y-3" ref="messagesContainer">
            <div 
                v-for="msg in messages" 
                :key="msg.id" 
                :class="[
                    'max-w-[70%] p-3 rounded-lg',
                    msg.sender_id === currentUser.id 
                        ? 'bg-blue-500 text-white self-end rounded-br-none' 
                        : 'bg-white text-gray-800 self-start rounded-bl-none shadow-sm'
                ]"
            >
                <div class="text-xs opacity-75 mb-1">{{ msg.sender.name }}</div>
                <div>{{ msg.content }}</div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="mt-4 flex gap-2">
            <input 
                v-model="newMessage" 
                @keyup.enter="sendMessage"
                type="text" 
                class="flex-1 border rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Type your message..."
                :disabled="isSending"
            />
            <button 
                @click="sendMessage" 
                :disabled="isSending || !newMessage.trim()"
                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
                Send
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios'; // We use axios here to avoid full Inertia page reloads when typing

const props = defineProps({
    session: Object,
    initialMessages: Array,
    currentUser: Object,
});

const messages = ref([...props.initialMessages]);
const newMessage = ref('');
const isSending = ref(false);
const messagesContainer = ref(null);

// Helper to auto-scroll to the bottom of the chat
const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

onMounted(() => {
    scrollToBottom();

    // Listen to the private chat channel
    window.Echo.private(`chat.${props.session.id}`)
        .listen('.MessageSent', (e) => {
            messages.value.push(e.message);
            scrollToBottom();
        });
});

onUnmounted(() => {
    window.Echo.leave(`chat.${props.session.id}`);
});

const sendMessage = async () => {
    if (!newMessage.value.trim() || isSending.value) return;

    const content = newMessage.value;
    isSending.value = true;
    newMessage.value = ''; // Clear input immediately for better UX

    try {
        const response = await axios.post(`/chat/${props.session.id}/message`, {
            content: content
        });
        
        // Add our own message to the screen (Echo's toOthers() prevents duplicates)
        messages.value.push(response.data.message);
        scrollToBottom();
    } catch (error) {
        console.error("Failed to send message", error);
        newMessage.value = content; // Restore the text if it failed
    } finally {
        isSending.value = false;
    }
};
</script>