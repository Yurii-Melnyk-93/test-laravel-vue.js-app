<script setup>
import { computed, ref } from 'vue';
import api from '../api.js';

const emit = defineEmits(['claimed']);

const code = ref('');
const status = ref('idle'); // idle | loading | success | error
const errorMessage = ref('');
const credited = ref(null);

const canSubmit = computed(() => code.value.trim().length > 0 && status.value !== 'loading');

/**
 * The API answers with a machine readable `reason` next to a human `message`.
 * The message is displayed as-is: parsing it, or keeping a second copy of the
 * wording here, would let the two drift apart.
 */
function messageFrom(error) {
    const data = error.response?.data;

    if (!data) {
        return 'Не вдалося зв’язатися з сервером.';
    }

    return data.errors?.code?.[0] ?? data.message ?? 'Не вдалося застосувати промокод.';
}

async function submit() {
    // The disabled button is not enough on its own: Enter in the input
    // submits the form without going through it. Crediting money must not
    // depend on the button being in the right visual state.
    if (status.value === 'loading') {
        return;
    }

    status.value = 'loading';
    errorMessage.value = '';
    credited.value = null;

    try {
        const { data } = await api.post('/promo/claim', { code: code.value });

        status.value = 'success';
        credited.value = { amount: data.bonus_amount.formatted, code: data.claim.code };
        code.value = '';

        // The balance comes back with the claim, so the parent does not need
        // a second round trip to show the new figure.
        emit('claimed', data.balance);
    } catch (error) {
        status.value = 'error';
        errorMessage.value = messageFrom(error);
    }
}
</script>

<template>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-medium">Промокод</h2>
        <p class="mt-1 text-sm text-slate-500">6–12 латинських літер або цифр</p>

        <form class="mt-4 flex gap-3" @submit.prevent="submit">
            <input
                v-model="code"
                type="text"
                maxlength="12"
                placeholder="WELCOME100"
                aria-label="Промокод"
                :disabled="status === 'loading'"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono tracking-wider uppercase outline-none focus:border-slate-900"
                @input="code = code.toUpperCase()"
            />

            <button
                type="submit"
                :disabled="!canSubmit"
                class="shrink-0 rounded-lg bg-slate-900 px-5 py-2 font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ status === 'loading' ? 'Застосування…' : 'Застосувати' }}
            </button>
        </form>

        <p
            v-if="status === 'success'"
            class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
            role="status"
        >
            Промокод <strong>{{ credited.code }}</strong> застосовано.
            Нараховано <strong>{{ credited.amount }}</strong>.
        </p>

        <p
            v-else-if="status === 'error'"
            class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"
            role="alert"
        >
            {{ errorMessage }}
        </p>
    </section>
</template>
