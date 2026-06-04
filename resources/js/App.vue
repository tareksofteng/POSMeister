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

    // Redirect to login when token expires mid-session. The axios interceptor
    // already cleared localStorage + the IndexedDB snapshot; we still need to
    // flush the in-memory Pinia state, otherwise the router guard sees
    // `isAuthenticated = true` and bounces the user back to /dashboard.
    window.addEventListener('auth:expired', () => {
        auth.token       = null;
        auth.user        = null;
        auth.permissions = [];
        router.push({ name: 'login' });
    });
});
</script>
