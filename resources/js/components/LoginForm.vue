<script setup>
import { ref } from 'vue';
import api, { setToken } from '../api.js';

const emit = defineEmits(['authenticated']);

const email = ref('');
const password = ref('');
const status = ref('idle'); // idle | loading | error
const errorMessage = ref('');

async function submit() {
    status.value = 'loading';
    errorMessage.value = '';

    try {
        const { data } = await api.post('/login', {
            email: email.value,
            password: password.value,
        });

        setToken(data.token);
        emit('authenticated', data.player);
    } catch (error) {
        status.value = 'error';
        // The API answers the same way for an unknown email and a wrong
        // password, so there is a single message to show here.
        errorMessage.value =
            error.response?.data?.message ?? 'Не вдалося зв’язатися з сервером.';
    }
}
</script>

<template>
    <form
        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
        @submit.prevent="submit"
    >
        <h2 class="text-lg font-medium">Вхід</h2>

        <div class="mt-4 space-y-4">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">Email</span>
                <input
                    v-model="email"
                    type="email"
                    autocomplete="username"
                    required
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900"
                />
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">Пароль</span>
                <input
                    v-model="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900"
                />
            </label>
        </div>

        <p
            v-if="status === 'error'"
            class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"
            role="alert"
        >
            {{ errorMessage }}
        </p>

        <button
            type="submit"
            :disabled="status === 'loading'"
            class="mt-5 w-full rounded-lg bg-slate-900 px-4 py-2 font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ status === 'loading' ? 'Вхід…' : 'Увійти' }}
        </button>
    </form>
</template>
