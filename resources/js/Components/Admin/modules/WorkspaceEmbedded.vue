<script setup>
import { ArrowLeftIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
  src: { type: String, required: true },
  title: { type: String, default: 'Módulo' },
  showTorneoSwitcher: { type: Boolean, default: false },
  torneoOptions: {
    type: Array,
    default: () => [],
  },
  selectedTorneoId: { type: Number, default: 0 },
});

const emit = defineEmits(['back', 'update:selectedTorneoId']);

function onTorneoChange(e) {
  const el = e.target;
  if (!(el instanceof HTMLSelectElement)) {
    return;
  }
  const v = Number(el.value);
  if (!Number.isFinite(v) || v <= 0) {
    return;
  }
  emit('update:selectedTorneoId', v);
}
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-slate-100">
    <div
      class="flex min-h-[2.75rem] shrink-0 flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-2 py-1.5 shadow-sm sm:gap-3 sm:px-3"
    >
      <button
        type="button"
        class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50"
        aria-label="Volver a la rejilla del panel maestro"
        @click="emit('back')"
      >
        <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
        <span class="hidden sm:inline">Volver al panel</span>
      </button>
      <span class="min-w-0 flex-1 truncate text-sm font-bold text-slate-600">{{ title }}</span>
      <div
        v-if="showTorneoSwitcher && torneoOptions.length > 0"
        class="flex min-w-0 w-full shrink-0 sm:ml-auto sm:w-auto sm:max-w-[min(100%,22rem)]"
      >
        <label class="sr-only" for="fvd-iframe-torneo-switch">Torneo asociado al grupo</label>
        <select
          id="fvd-iframe-torneo-switch"
          class="w-full min-w-0 rounded-md border border-slate-300 bg-slate-50 py-1.5 pl-2 pr-8 text-sm font-bold text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          :value="selectedTorneoId"
          @change="onTorneoChange"
        >
          <option v-for="t in torneoOptions" :key="t.torneo" :value="t.torneo">
            {{ t.grupo_label }} — {{ t.nombre }}
          </option>
        </select>
      </div>
    </div>
    <iframe
      :key="src"
      :src="src"
      class="min-h-0 w-full flex-1 border-0 bg-white"
      title="Módulo embebido"
    />
  </div>
</template>
