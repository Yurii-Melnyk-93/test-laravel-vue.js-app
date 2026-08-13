<script setup>
defineProps({
    player: { type: Object, required: true },
    busy: { type: Boolean, default: false },
});

defineEmits(['logout']);
</script>

<template>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">{{ player.name }}</p>
                <p class="text-xs text-slate-400">{{ player.email }}</p>
            </div>

            <button
                type="button"
                :disabled="busy"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm transition hover:bg-slate-50 disabled:opacity-50"
                @click="$emit('logout')"
            >
                Вийти
            </button>
        </div>

        <div class="mt-4">
            <p class="text-sm text-slate-500">Баланс</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">
                {{ player.balance.formatted }}
            </p>
        </div>
    </section>
</template>
