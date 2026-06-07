<template>
    <div class="app-shell flex h-full overflow-hidden bg-gray-50 dark:bg-slate-950">

        <!--
            Sidebar — behaves in two modes:
              - lg+ : in-flow, takes layout width, collapsible
              - <lg : off-canvas drawer with backdrop
        -->
        <Sidebar
            :collapsed="sidebarCollapsed"
            :mobile-open="mobileSidebarOpen"
            @close-mobile="mobileSidebarOpen = false"
        />

        <!-- Backdrop, only on mobile when the drawer is open -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileSidebarOpen"
                @click="mobileSidebarOpen = false"
                class="lg:hidden fixed inset-0 z-30 bg-slate-900/60 backdrop-blur-[1px]"
                aria-hidden="true"
            />
        </Transition>

        <!-- Content area -->
        <div class="flex flex-1 flex-col min-w-0 overflow-hidden">
            <OfflineBanner />
            <Topbar @toggle-sidebar="toggleSidebar" />

            <main class="flex-1 overflow-y-auto overflow-x-hidden overscroll-contain pb-safe scroll-smooth-mobile main-with-bottom-nav lg:main-no-bottom-nav">
                <!-- Plain RouterView. The slide-fade Transition that used to
                     wrap this was racing the chunk loader: when a lazy route
                     finished resolving AFTER its leave animation ended, the
                     incoming page mounted in `enter-from` state (opacity 0)
                     and stayed invisible until the user hit refresh. The
                     animation was a polish-only thing; correct content
                     beats subtle motion every time. We can bring it back
                     later via a `<Suspense>` wrapper that waits for the
                     chunk before triggering the transition. -->
                <RouterView />
            </main>
        </div>

        <!-- Mobile bottom tab bar — only renders <lg via its own breakpoint -->
        <BottomNav @open-more="mobileSidebarOpen = true" />

        <!-- Global command palette — always mounted, opens on Ctrl/Cmd+K -->
        <CommandPalette />
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from './Sidebar.vue';
import Topbar  from './Topbar.vue';
import BottomNav from './BottomNav.vue';
import CommandPalette from '../CommandPalette.vue';
import OfflineBanner  from '../OfflineBanner.vue';
import { useSettingsStore } from '@/stores/settings';
import { useThemeStore } from '@/stores/theme';

const sidebarCollapsed   = ref(false);     // desktop rail (w-16 vs w-64)
const mobileSidebarOpen  = ref(false);     // off-canvas drawer
const route = useRoute();

const MOBILE_BREAKPOINT = 1024;
const isMobile = () => typeof window !== 'undefined' && window.innerWidth < MOBILE_BREAKPOINT;

function toggleSidebar() {
    if (isMobile()) {
        mobileSidebarOpen.value = !mobileSidebarOpen.value;
    } else {
        sidebarCollapsed.value = !sidebarCollapsed.value;
    }
}

// Body scroll lock while the mobile drawer is open
watch(mobileSidebarOpen, (open) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = open ? 'hidden' : '';
});

// Auto-close drawer on route change (so navigation feels app-like)
watch(() => route.fullPath, () => {
    if (mobileSidebarOpen.value) mobileSidebarOpen.value = false;
});

// Resize handler — collapse mobile drawer when we cross into desktop territory
function onResize() {
    if (!isMobile() && mobileSidebarOpen.value) {
        mobileSidebarOpen.value = false;
        document.body.style.overflow = '';
    }
}

onMounted(() => {
    useSettingsStore().load();
    useThemeStore();
    window.addEventListener('resize', onResize);
});
onUnmounted(() => {
    window.removeEventListener('resize', onResize);
    document.body.style.overflow = '';
});
</script>

<style scoped>
@reference '../../../css/app.css';

/* Honour iOS safe-area on the bottom edge in standalone/PWA mode */
.pb-safe { padding-bottom: env(safe-area-inset-bottom, 0); }

/* Push the main scroll area up by the bottom-nav height on phones so the
   last list row isn't hidden under the floating tab bar. The bar is roughly
   56px + safe-area, so 72px gives a small visual gap on top. */
.main-with-bottom-nav {
    padding-bottom: calc(72px + env(safe-area-inset-bottom, 0));
}
@media (min-width: 1024px) {
    .lg\:main-no-bottom-nav { padding-bottom: 0; }
}

/* ── Page transition — slide in from a few px right, fade in/out ────────── */
.page-enter-active,
.page-leave-active {
    transition: opacity 180ms ease, transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
}
.page-enter-from { opacity: 0; transform: translate3d(8px, 0, 0); }
.page-leave-to   { opacity: 0; transform: translate3d(-4px, 0, 0); }
</style>
