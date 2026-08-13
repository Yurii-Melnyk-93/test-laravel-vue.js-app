<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import api, { clearToken, getToken } from './api.js';
import BalanceCard from './components/BalanceCard.vue';
import LoginForm from './components/LoginForm.vue';
import PromoClaimForm from './components/PromoClaimForm.vue';

const player = ref(null);
const booting = ref(true);
const loggingOut = ref(false);

// A token in localStorage survives a reload, so the session is restored
// from the server rather than trusted blindly.
onMounted(async () => {
    if (getToken()) {
        try {
            const { data } = await api.get('/me');
            player.value = data.player;
        } catch {
            clearToken();
        }
    }

    booting.value = false;
});

// api.js raises this when any request comes back 401.
function onAuthExpired() {
    player.value = null;
}

onMounted(() => window.addEventListener('auth:expired', onAuthExpired));
onUnmounted(() => window.removeEventListener('auth:expired', onAuthExpired));

// The claim response already carries the new balance, so there is nothing
// to re-fetch here.
function onClaimed(balance) {
    player.value = { ...player.value, balance };
}

async function logout() {
    loggingOut.value = true;

    try {
        await api.post('/logout');
    } finally {
        clearToken();
        player.value = null;
        loggingOut.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <header class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight">Промокоди та бонуси</h1>
            <p class="mt-1 text-sm text-slate-500">Баланс гравця та бонусні нарахування</p>
        </header>

        <main>
            <p v-if="booting" class="text-sm text-slate-500">Завантаження…</p>

            <LoginForm v-else-if="!player" @authenticated="player = $event" />

            <div v-else class="space-y-6">
                <BalanceCard :player="player" :busy="loggingOut" @logout="logout" />
                <PromoClaimForm @claimed="onClaimed" />
            </div>
        </main>
    </div>
</template>
