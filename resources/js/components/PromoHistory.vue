<script setup>
import { onMounted, ref } from 'vue';
import api from '../api.js';
import { messageFrom } from '../errors.js';
import ConfirmDialog from './ConfirmDialog.vue';

const emit = defineEmits(['revoked']);

const FILTERS = [
    { value: '', label: 'Усі' },
    { value: 'applied', label: 'Застосовані' },
    { value: 'rejected', label: 'Відхилені' },
    { value: 'revoked', label: 'Скасовані' },
];

const STATUS_LABELS = {
    applied: { text: 'Застосовано', class: 'bg-emerald-50 text-emerald-700' },
    rejected: { text: 'Відхилено', class: 'bg-red-50 text-red-700' },
    revoked: { text: 'Скасовано', class: 'bg-amber-50 text-amber-800' },
};

const PER_PAGE = 5;

const rows = ref([]);
const meta = ref(null);
const status = ref('');
const page = ref(1);
const loading = ref(false);
const errorMessage = ref('');

// Kept apart from errorMessage: a failed revoke must not replace the list
// with an error, the rows are still valid and still worth seeing.
const revokeTarget = ref(null);
const revoking = ref(false);
const revokeError = ref('');

const dateFormat = new Intl.DateTimeFormat('uk-UA', {
    dateStyle: 'short',
    timeStyle: 'short',
});

function formatDate(iso) {
    return dateFormat.format(new Date(iso));
}

async function load() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await api.get('/promo/history', {
            params: {
                status: status.value || undefined,
                page: page.value,
                per_page: PER_PAGE,
            },
        });

        rows.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        errorMessage.value = messageFrom(error, 'Не вдалося завантажити історію.');
    } finally {
        loading.value = false;
    }
}

// Changing the filter has to return to page one: the current page may not
// exist in the filtered result, which would show an empty list.
function filterBy(value) {
    status.value = value;
    page.value = 1;
    load();
}

function goTo(target) {
    page.value = target;
    load();
}

function askToRevoke(row) {
    revokeError.value = '';
    revokeTarget.value = row;
}

async function confirmRevoke() {
    // Guarded here rather than only through `disabled`: this takes money off
    // a balance, so a second Enter on the focused button must not send it
    // twice while the first request is still in flight.
    if (revoking.value || revokeTarget.value === null) {
        return;
    }

    revoking.value = true;

    try {
        const { data } = await api.patch(`/promo/${revokeTarget.value.id}/revoke`);

        revokeTarget.value = null;

        // The balance comes back with the response, so the parent shows the
        // new figure without a second round trip.
        emit('revoked', data.balance);

        // The row's status and amount changed on the server, and under the
        // "applied" filter it no longer belongs here at all.
        await load();
    } catch (error) {
        revokeTarget.value = null;
        revokeError.value = messageFrom(error, 'Не вдалося скасувати нарахування.');

        // A refusal usually means our copy of the row is out of date — it was
        // already revoked elsewhere, or the balance moved. Refetch so the list
        // stops showing a button that cannot work.
        if (error.response) {
            await load();
        }
    } finally {
        revoking.value = false;
    }
}

onMounted(load);

// The parent calls this after a successful claim so the new row appears
// without a full page reload.
defineExpose({
    reload() {
        page.value = 1;

        return load();
    },
});
</script>

<template>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-medium">Історія</h2>

            <div class="flex flex-wrap gap-1">
                <button
                    v-for="filter in FILTERS"
                    :key="filter.value"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm transition"
                    :class="
                        status === filter.value
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-600 hover:bg-slate-100'
                    "
                    @click="filterBy(filter.value)"
                >
                    {{ filter.label }}
                </button>
            </div>
        </div>

        <p
            v-if="revokeError"
            class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"
            role="alert"
        >
            {{ revokeError }}
        </p>

        <p v-if="errorMessage" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
            {{ errorMessage }}
        </p>

        <p v-else-if="loading && rows.length === 0" class="mt-6 text-sm text-slate-500">
            Завантаження…
        </p>

        <p v-else-if="rows.length === 0" class="mt-6 text-sm text-slate-500">
            Записів немає.
        </p>

        <ul v-else class="mt-4 divide-y divide-slate-100" :class="{ 'opacity-50': loading }">
            <li v-for="row in rows" :key="row.id" class="flex items-center gap-4 py-3">
                <div class="min-w-0 flex-1">
                    <p class="font-mono text-sm">{{ row.code }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ formatDate(row.created_at) }}
                        <template v-if="row.rejection_message">
                            · {{ row.rejection_message }}
                        </template>
                    </p>
                </div>

                <span
                    class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="STATUS_LABELS[row.status].class"
                >
                    {{ STATUS_LABELS[row.status].text }}
                </span>

                <span class="w-20 shrink-0 text-right font-medium tabular-nums">
                    {{ row.amount ? row.amount.formatted : '—' }}
                </span>

                <!-- Fixed width so rows without a button still line up. The
                     server decides who gets one, via can_revoke. -->
                <span class="w-24 shrink-0 text-right">
                    <button
                        v-if="row.can_revoke"
                        type="button"
                        :disabled="loading || revoking"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm transition hover:bg-slate-50 disabled:opacity-40"
                        @click="askToRevoke(row)"
                    >
                        Скасувати
                    </button>
                </span>
            </li>
        </ul>

        <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-between">
            <button
                type="button"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm transition hover:bg-slate-50 disabled:opacity-40"
                :disabled="meta.current_page === 1 || loading"
                @click="goTo(meta.current_page - 1)"
            >
                Назад
            </button>

            <span class="text-sm text-slate-500">
                Сторінка {{ meta.current_page }} з {{ meta.last_page }}
            </span>

            <button
                type="button"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm transition hover:bg-slate-50 disabled:opacity-40"
                :disabled="meta.current_page === meta.last_page || loading"
                @click="goTo(meta.current_page + 1)"
            >
                Далі
            </button>
        </div>

        <ConfirmDialog
            :open="revokeTarget !== null"
            :busy="revoking"
            title="Скасувати нарахування?"
            :message="
                revokeTarget
                    ? `Бонус ${revokeTarget.amount.formatted} за промокодом ${revokeTarget.code} буде знято з балансу.`
                    : ''
            "
            confirm-label="Так, скасувати"
            cancel-label="Ні, залишити"
            @confirm="confirmRevoke"
            @cancel="revokeTarget = null"
        />
    </section>
</template>
