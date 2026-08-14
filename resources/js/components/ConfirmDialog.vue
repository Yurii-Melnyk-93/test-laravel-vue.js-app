<script setup>
import { nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Підтвердити' },
    cancelLabel: { type: String, default: 'Закрити' },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const confirmButton = ref(null);

function cancel() {
    if (!props.busy) {
        emit('cancel');
    }
}

// Escape is the expected way out of a dialog, and the native confirm() we
// are replacing had it. It is ignored while the request is in flight.
function onKeydown(event) {
    if (event.key === 'Escape') {
        cancel();
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            window.addEventListener('keydown', onKeydown);
            await nextTick();
            confirmButton.value?.focus();
        } else {
            window.removeEventListener('keydown', onKeydown);
        }
    },
    // Immediate so a dialog mounted already open behaves like one opened later.
    { immediate: true },
);

onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
        @click.self="cancel"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="confirm-dialog-title"
            class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl"
        >
            <h3 id="confirm-dialog-title" class="text-lg font-medium">{{ title }}</h3>

            <p v-if="message" class="mt-2 text-sm text-slate-600">{{ message }}</p>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    :disabled="busy"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm transition hover:bg-slate-50 disabled:opacity-40"
                    @click="cancel"
                >
                    {{ cancelLabel }}
                </button>

                <button
                    ref="confirmButton"
                    type="button"
                    :disabled="busy"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-500 disabled:opacity-50"
                    @click="emit('confirm')"
                >
                    {{ busy ? 'Виконується…' : confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
