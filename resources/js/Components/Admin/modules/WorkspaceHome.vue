<script setup>
/**
 * Navegación del workspace: las claves deben coincidir con `workspaceRoutes` en
 * `fvdmasteradmin/includes/master_panel_state.php` (stubs bajo cruds/, operaciones/, reportes/).
 */
import { computed, inject } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import {
  BuildingOffice2Icon,
  UserGroupIcon,
  TrophyIcon,
  EnvelopeIcon,
  LinkIcon,
  GlobeAltIcon,
  CurrencyDollarIcon,
  BuildingLibraryIcon,
  DocumentTextIcon,
  ChartBarIcon,
} from '@heroicons/vue/24/outline';
import { ExclamationTriangleIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  state: {
    type: Object,
    required: true,
  },
});

const workspace = inject('fvdWorkspace', null);

const stats = computed(() => ({
  pendingPayments: Number(props.state.pendingPayments ?? 0),
}));

const finanzasGrupo = computed(() => props.state.finanzasGrupo);

const resumenPagosGrupo = computed(() => {
  const f = finanzasGrupo.value;
  if (!f || typeof f !== 'object') {
    return '';
  }
  const n = Number(f.n_registros ?? 0);
  const bs = Number(f.sum_monto_total ?? 0);
  const usd = Number(f.sum_monto_dolares ?? 0);
  if (n <= 0 && bs <= 0 && usd <= 0) {
    return '';
  }
  return `Pagos grupo (SUM): ${n} reg. · Bs ${bs.toFixed(2)} · USD ${usd.toFixed(2)}`;
});

const eventos = computed(() => (Array.isArray(props.state.recentEvents) ? props.state.recentEvents : []));

function navigate(key) {
  if (workspace && typeof workspace.navigate === 'function') {
    workspace.navigate(key);
  }
}
</script>

<template>
  <div
    class="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden p-2 font-sans text-base font-medium md:gap-4 md:p-3"
  >
    <!-- Alertas: contraste alto frente al fondo -->
    <div v-if="(state.alerts || []).length" class="shrink-0 space-y-2" role="alert">
      <div
        v-for="a in state.alerts"
        :key="a.id"
        class="flex items-start gap-2 rounded-lg border-2 border-amber-500 bg-amber-100 px-3 py-2 text-base font-bold text-amber-950 shadow-sm"
      >
        <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-800" aria-hidden="true" />
        <span class="min-w-0 break-words leading-snug">{{ a.msg }}</span>
      </div>
    </div>

    <div
      class="grid min-h-0 flex-1 grid-cols-1 gap-3 overflow-hidden md:grid-cols-3 md:gap-3"
    >
      <!-- Servicios -->
      <div
        class="flex min-h-0 min-w-0 flex-col gap-2.5 overflow-y-auto rounded-xl border border-slate-300/90 bg-slate-100/80 p-3 shadow-inner"
      >
        <h2
          class="mb-2 border-b border-slate-300 pb-2 text-sm font-bold uppercase tracking-wider text-slate-800"
        >
          Servicios
        </h2>

        <button
          type="button"
          class="fvd-action-btn border-violet-300/90 bg-violet-50 text-violet-950 hover:border-violet-500 hover:bg-violet-100"
          @click="navigate('servicios/asociaciones')"
        >
          <span>Asociaciones</span>
          <BuildingOffice2Icon class="h-5 w-5 shrink-0 text-violet-600" aria-hidden="true" />
        </button>

        <button
          type="button"
          class="fvd-action-btn border-emerald-300/90 bg-emerald-50 text-emerald-950 hover:border-emerald-500 hover:bg-emerald-100"
          @click="navigate('servicios/atletas')"
        >
          <span>Atletas</span>
          <UserGroupIcon class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true" />
        </button>

        <button
          type="button"
          class="fvd-action-btn border-blue-400 bg-blue-50 text-blue-950 ring-1 ring-blue-200/80 hover:border-blue-600 hover:bg-blue-100"
          @click="navigate('servicios/torneos')"
        >
          <span>Torneos</span>
          <TrophyIcon class="h-5 w-5 shrink-0 text-blue-600" aria-hidden="true" />
        </button>

        <button
          type="button"
          class="fvd-action-btn border-teal-300/90 bg-teal-50 text-teal-950 hover:border-teal-500 hover:bg-teal-100"
          @click="navigate('servicios/atletas_reset')"
        >
          <span>Reiniciar atletas</span>
          <RefreshCw class="h-5 w-5 shrink-0 text-teal-600" aria-hidden="true" stroke-width="2" />
        </button>
      </div>

      <!-- Operaciones -->
      <div
        class="flex min-h-0 min-w-0 flex-col gap-2.5 overflow-y-auto rounded-xl border border-slate-300/90 bg-slate-100/80 p-3 shadow-inner"
      >
        <h2
          class="mb-2 border-b border-slate-300 pb-2 text-sm font-bold uppercase tracking-wider text-slate-800"
        >
          Operaciones
        </h2>

        <button
          type="button"
          class="fvd-action-btn border-blue-300/90 bg-blue-50 text-blue-950 hover:border-blue-500 hover:bg-blue-100"
          @click="navigate('operaciones/torneos')"
        >
          <span>Gestión de torneos</span>
          <TrophyIcon class="h-5 w-5 shrink-0 text-blue-600" aria-hidden="true" />
        </button>

        <p
          v-if="state.invitacionesMonitor"
          class="mb-1 rounded-lg border border-slate-300/90 bg-white/90 px-3 py-2 text-xs font-semibold leading-snug text-slate-700 shadow-sm"
        >
          {{ state.invitacionesMonitor }}
        </p>

        <button
          type="button"
          class="fvd-action-btn border-sky-300/90 bg-sky-50 text-sky-950 hover:border-sky-500 hover:bg-sky-100"
          title="Asocia dos o más campeonatos el mismo día para compartir grupo y cambiar de entorno sin salir del sistema"
          @click="navigate('operaciones/assoc_torneo')"
        >
          <span>Relación torneos (mismo día)</span>
          <LinkIcon class="h-5 w-5 shrink-0 text-sky-600" aria-hidden="true" />
        </button>

        <button
          type="button"
          class="fvd-action-btn border-indigo-300/90 bg-indigo-50 text-indigo-950 hover:border-indigo-500 hover:bg-indigo-100"
          @click="navigate('operaciones/invitaciones')"
        >
          <span>Invitaciones</span>
          <EnvelopeIcon class="h-5 w-5 shrink-0 text-indigo-600" aria-hidden="true" />
        </button>

        <button
          type="button"
          class="fvd-action-btn border-teal-300/90 bg-teal-50 text-teal-950 hover:border-teal-500 hover:bg-teal-100"
          @click="navigate('operaciones/portal_assoc')"
        >
          <span>Portal asociación</span>
          <GlobeAltIcon class="h-5 w-5 shrink-0 text-teal-600" aria-hidden="true" />
        </button>
      </div>

      <!-- Finanzas -->
      <div
        class="flex min-h-0 min-w-0 flex-col gap-2.5 overflow-y-auto rounded-xl border border-slate-300/90 bg-slate-100/80 p-3 shadow-inner"
      >
        <h2
          class="mb-2 border-b border-slate-300 pb-2 text-sm font-bold uppercase tracking-wider text-slate-800"
        >
          Finanzas
        </h2>

        <p
          v-if="resumenPagosGrupo"
          class="mb-1 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-bold leading-snug text-indigo-950 shadow-sm"
        >
          {{ resumenPagosGrupo }}
        </p>

        <button
          type="button"
          class="fvd-action-btn border-stone-300/90 bg-stone-100 text-stone-950 hover:border-stone-500 hover:bg-stone-200"
          @click="navigate('finanzas/general')"
        >
          <span>General</span>
          <CurrencyDollarIcon class="h-5 w-5 shrink-0 text-stone-700" aria-hidden="true" />
        </button>

        <button
          type="button"
          class="fvd-action-btn border-purple-300/90 bg-purple-50 text-purple-950 hover:border-purple-500 hover:bg-purple-100"
          @click="navigate('finanzas/por_torneo')"
        >
          <span>Por torneo</span>
          <BuildingLibraryIcon class="h-5 w-5 shrink-0 text-purple-600" aria-hidden="true" />
        </button>

        <button
          type="button"
          class="group fvd-action-btn border-emerald-300/90 bg-emerald-50 text-emerald-950 hover:border-emerald-500 hover:bg-emerald-100"
          @click="navigate('finanzas/deudas_pagos')"
        >
          <span>Cartera (deudas y pagos)</span>
          <span class="flex shrink-0 items-center gap-1.5">
            <DocumentTextIcon
              class="h-5 w-5 text-emerald-600 transition group-hover:text-emerald-700"
              aria-hidden="true"
            />
            <span
              v-if="stats.pendingPayments > 0"
              class="rounded-md bg-emerald-800 px-2 py-0.5 text-xs font-bold text-white"
            >{{ stats.pendingPayments }}</span>
          </span>
        </button>

        <button
          type="button"
          class="fvd-action-btn mt-1 border-slate-700 bg-slate-800 text-white shadow-md hover:border-slate-900 hover:bg-slate-900 focus-visible:ring-offset-slate-900"
          @click="navigate('finanzas/consolidado')"
        >
          <span>Consolidado</span>
          <ChartBarIcon class="h-5 w-5 shrink-0 text-sky-300" aria-hidden="true" />
        </button>
      </div>
    </div>

    <!-- Actividad reciente: tarjeta con borde explícito -->
    <div
      class="flex max-h-[min(220px,26vh)] min-h-0 shrink-0 flex-col overflow-hidden rounded-xl border-2 border-slate-300 bg-white shadow-sm"
    >
      <div
        class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-slate-100 px-3 py-2"
      >
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900">Eventos recientes</h2>
        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-800">{{
          eventos.length
        }}</span>
      </div>
      <ul class="hide-scrollbar list-none overflow-y-auto overflow-x-hidden p-2" aria-label="Eventos recientes">
        <li v-if="eventos.length === 0" class="px-2 py-4 text-center text-base font-semibold text-slate-700">
          Sin eventos recientes.
        </li>
        <li v-for="ev in eventos" :key="ev.id" class="border-b border-slate-100 last:border-0">
          <a
            :href="ev.href || '#'"
            class="flex min-w-0 items-start gap-2 rounded-lg px-2 py-2 text-left transition hover:bg-slate-50"
          >
            <div class="min-w-0 flex-1">
              <p class="truncate text-base font-bold text-slate-900">{{ ev.title }}</p>
              <p class="truncate text-sm font-medium text-slate-600">{{ ev.meta }}</p>
            </div>
            <ChevronRightIcon class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
          </a>
        </li>
      </ul>
    </div>
  </div>
</template>
