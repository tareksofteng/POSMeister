<template>
    <RouterView />
</template>

<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';

const auth   = useAuthStore();
const router = useRouter();

// Refresh user profile on every app boot (keeps data fresh after page reload)
onMounted(() => {
    if (auth.isAuthenticated) {
        auth.fetchMe();
    }

    // Redirect to login when token expires mid-session
    window.addEventListener('auth:expired', () => {
        router.push({ name: 'login' });
    });
});
</script>
