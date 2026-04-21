<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import {
  UserGroupIcon,
  IdentificationIcon,
  ArrowsRightLeftIcon,
  TrophyIcon,
  ClipboardDocumentListIcon,
  CurrencyDollarIcon,
  BanknotesIcon,
  ChartBarIcon,
  BellAlertIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  initialState: {
    type: Object,
    default: () => ({}),
  },
});

const state = ref({ ...props.initialState });

/** Misma idea que delegadoActions en especificación: claves → URL resuelta en PHP */
const routes = computed(() => state.value.delegadoRoutes || {});

const liveUnread = ref(Number(props.initialState.notificationsUnread ?? 0));
const livePendingAccept = ref(Boolean(props.initialState.inscripcionBloqueadaPorInvitacion));

const notifUnread = computed(() => {
  const n = liveUnread.value;
  return Number.isFinite(n) ? n : 0;
});

const delegadoAccess = computed(() => state.value.delegadoAccess || null);

const inscripcionBloqueada = computed(() => {
  if (livePendingAccept.value) {
    return true;
  }
  const a = delegadoAccess.value;
  if (!a) {
    return false;
  }
  if (a.nomina_solo_lectura) {
    return true;
  }
  if (a.inscripciones_carnets === false) {
    return true;
  }
  return false;
});

const cambiosNominaBloqueados = computed(() => {
  const a = delegadoAccess.value;
  return Boolean(a && a.nomina_solo_lectura);
});

const fechaLimiteCambiosLabel = computed(() => {
  const a = delegadoAccess.value;
  if (!a || !a.nomina_solo_lectura) {
    return '';
  }
  const s = String(a.fecha_limite_cambios_formato || '').trim();
  return s !== '' ? s : '—';
});

const notifPollUrl = computed(() => String(state.value.notifPollUrl || ''));
const notifAceptarUrl = computed(() => String(state.value.notifAceptarUrl || ''));
const torneoInvitacionId = computed(() => Number(state.value.torneoInvitacionId ?? 0));

const showTorneoWelcome = ref(false);
let pollTimer = null;

function welcomeStorageKey() {
  const tid = torneoInvitacionId.value;
  return tid > 0 ? `fvd_welcome_torneo_${tid}` : '';
}

function syncWelcomeModal() {
  const tid = torneoInvitacionId.value;
  if (tid <= 0 || !livePendingAccept.value) {
    showTorneoWelcome.value = false;
    return;
  }
  const k = welcomeStorageKey();
  if (k && typeof sessionStorage !== 'undefined' && sessionStorage.getItem(k) === '1') {
    showTorneoWelcome.value = false;
    return;
  }
  showTorneoWelcome.value = true;
}

async function pollNotificaciones() {
  const u = notifPollUrl.value;
  if (!u) {
    return;
  }
  try {
    const res = await fetch(u, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    if (data && data.ok) {
      liveUnread.value = Number(data.unread ?? 0);
      livePendingAccept.value = Boolean(data.pendingAccept);
      state.value.notificationsUnread = liveUnread.value;
      syncWelcomeModal();
    }
  } catch (e) {
    console.error('[delegado notif poll]', e);
  }
}

function dismissWelcomeLater() {
  const k = welcomeStorageKey();
  if (k && typeof sessionStorage !== 'undefined') {
    sessionStorage.setItem(k, '1');
  }
  showTorneoWelcome.value = false;
}

async function aceptarInvitacionTorneo() {
  const url = notifAceptarUrl.value;
  const tid = torneoInvitacionId.value;
  if (!url || tid <= 0) {
    return;
  }
  const body = new FormData();
  body.set('torneo_id', String(tid));
  try {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      body,
      headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    if (data && data.ok) {
      livePendingAccept.value = false;
      const k = welcomeStorageKey();
      if (k && typeof sessionStorage !== 'undefined') {
        sessionStorage.setItem(k, '1');
      }
      showTorneoWelcome.value = false;
      await pollNotificaciones();
    } else {
      window.alert((data && data.error) || 'No se pudo registrar la aceptación.');
    }
  } catch (e) {
    console.error('[delegado aceptar invitación]', e);
    window.alert('Error de red al aceptar la invitación.');
  }
}

const contextOptions = computed(() => {
  const raw = state.value.contextTorneoOptions;
  if (Array.isArray(raw) && raw.length > 0) {
    return raw.filter((o) => o && Number(o.torneo_id) > 0);
  }
  return [];
});

const selectedTorneoId = ref(Number(state.value.currentTorneoId ?? 0));

watch(
  () => state.value.currentTorneoId,
  (v) => {
    const n = Number(v ?? 0);
    if (n > 0) {
      selectedTorneoId.value = n;
    }
  },
);

const gestionandoLabel = computed(() => {
  const tid = Number(selectedTorneoId.value);
  if (tid <= 0) {
    return state.value.campeonatoNombre || 'Sin torneo en contexto';
  }
  const opt = contextOptions.value.find((o) => Number(o.torneo_id) === tid);
  if (opt && opt.label) {
    return opt.label;
  }
  return state.value.currentTorneoNombre || 'Torneo #' + tid;
});

const inscripcionApiUrl = computed(() => String(state.value.inscripcionApiUrl || ''));
const campeonatoId = computed(() => Number(state.value.campeonatoId ?? 0));

async function onContextTorneoChange() {
  const tid = Number(selectedTorneoId.value);
  const camp = campeonatoId.value;
  const api = inscripcionApiUrl.value;
  if (tid <= 0 || camp <= 0 || !api) {
    return;
  }
  try {
    const sep = api.includes('?') ? '&' : '?';
    const u = `${api}${sep}action=delegado_inscripcion_panel&torneo_id=${encodeURIComponent(String(tid))}&campeonato_id=${encodeURIComponent(String(camp))}`;
    const res = await fetch(u, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    if (data && data.ok) {
      window.location.reload();
      return;
    }
    window.alert((data && data.error) || 'No se pudo cambiar el torneo en contexto.');
  } catch (e) {
    console.error('[delegado context]', e);
    window.alert('Error de red al actualizar el contexto.');
  }
}

function href(key) {
  const u = routes.value[key];
  return typeof u === 'string' && u !== '' ? u : '#';
}

onMounted(() => {
  selectedTorneoId.value = Number(state.value.currentTorneoId ?? 0);
  syncWelcomeModal();
  void pollNotificaciones();
  pollTimer = window.setInterval(() => {
    void pollNotificaciones();
  }, 15000);
});

onUnmounted(() => {
  if (pollTimer !== null) {
    window.clearInterval(pollTimer);
    pollTimer = null;
  }
});

watch(
  () => [torneoInvitacionId.value, livePendingAccept.value],
  () => {
    syncWelcomeModal();
  },
);
</script>

<template>
  <div
    class="relative flex min-h-0 flex-col gap-3 overflow-hidden rounded-xl border border-slate-200/90 bg-slate-50 p-2 font-sans text-base font-medium text-slate-900 md:gap-4 md:p-3"
  >
    <!-- Periodo de cambios de nómina cerrado (regla de tiempo) -->
    <div
      v-if="cambiosNominaBloqueados"
      class="rounded-lg border border-amber-200/90 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-950 shadow-sm"
      role="status"
    >
      ⚠️ Periodo de cambios finalizado el {{ fechaLimiteCambiosLabel }} — Vista de consulta solamente.
    </div>

    <!-- Badge notificaciones (esquina superior derecha) -->
    <div
      class="pointer-events-none absolute right-2 top-2 z-10 md:right-3 md:top-3"
      role="status"
      :aria-label="notifUnread > 0 ? notifUnread + ' notificaciones sin leer' : 'Sin notificaciones nuevas'"
    >
      <div class="pointer-events-auto relative inline-flex">
        <BellAlertIcon
          class="h-8 w-8 text-amber-500 drop-shadow-sm"
          :class="notifUnread > 0 ? 'animate-pulse' : 'text-slate-400'"
        />
        <span
          v-if="notifUnread > 0"
          class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-xs font-bold text-white shadow-md ring-2 ring-white"
        >
          {{ notifUnread > 99 ? '99+' : notifUnread }}
        </span>
        <span
          v-if="notifUnread > 0"
          class="absolute -right-0.5 -top-0.5 inline-flex h-3 w-3"
        >
          <span
            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"
          />
          <span class="relative inline-flex h-3 w-3 rounded-full bg-rose-600" />
        </span>
      </div>
    </div>

    <!-- Contexto textual: sin h1 duplicado (título en topbar + bloques inferiores) -->
    <div class="flex flex-wrap items-start justify-between gap-2 pr-14 md:pr-16">
      <p
        v-if="state.campeonatoNombre"
        class="text-sm font-semibold text-slate-600"
      >
        {{ state.campeonatoNombre }}
      </p>
    </div>

    <!-- Contexto: torneo activo + selector -->
    <div
      class="rounded-lg border-2 border-indigo-200 bg-indigo-50 px-3 py-2.5 shadow-sm md:px-4 md:py-3"
      role="region"
      aria-label="Contexto del torneo"
    >
      <p class="text-base font-bold leading-snug text-slate-900">
        Gestionando:
        <span class="text-indigo-950">{{ gestionandoLabel }}</span>
      </p>
      <div
        v-if="contextOptions.length > 1 && campeonatoId > 0"
        class="mt-2 flex flex-wrap items-center gap-2"
      >
        <label class="text-sm font-bold text-slate-700" for="fvd-deleg-ctx-torneo">Cambiar rama / torneo</label>
        <select
          id="fvd-deleg-ctx-torneo"
          v-model.number="selectedTorneoId"
          class="max-w-full rounded-md border border-indigo-300 bg-white py-1.5 pl-2 pr-8 text-sm font-bold text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 md:max-w-md"
          @change="onContextTorneoChange"
        >
          <option v-for="opt in contextOptions" :key="opt.torneo_id" :value="Number(opt.torneo_id)">
            {{ opt.label }}
          </option>
        </select>
      </div>
      <p v-else-if="contextOptions.length === 1" class="mt-1 text-sm font-semibold text-slate-600">
        Rama única en este campeonato.
      </p>
      <p
        v-if="delegadoAccess && delegadoAccess.etiqueta"
        class="mt-2 text-xs font-semibold leading-snug text-slate-600"
      >
        {{ delegadoAccess.etiqueta }}
      </p>
    </div>

    <!-- Tres columnas (misma familia de botones que el panel Admin: .fvd-action-btn) -->
    <div
      class="grid min-h-0 flex-1 grid-cols-1 gap-3 overflow-hidden md:grid-cols-3 md:gap-3"
    >
      <!-- Atletas -->
      <div
        class="flex min-h-0 min-w-0 flex-col gap-2.5 overflow-y-auto rounded-xl border border-slate-300/90 bg-slate-100/80 p-3 shadow-inner"
      >
        <h2
          class="mb-1 border-b border-slate-300 pb-2 text-sm font-bold uppercase tracking-wider text-slate-800"
        >
          Atletas
        </h2>
        <a
          :href="href('atleta/afiliacion')"
          class="fvd-action-btn border-violet-300/90 bg-violet-50 text-violet-950 hover:border-violet-500 hover:bg-violet-100"
        >
          <span>Afiliación</span>
          <UserGroupIcon class="h-5 w-5 shrink-0 text-violet-600" aria-hidden="true" />
        </a>
        <a
          :href="href('atleta/carnet')"
          class="fvd-action-btn border-amber-300/90 bg-amber-50 text-amber-950 hover:border-amber-500 hover:bg-amber-100"
        >
          <span>Carnet</span>
          <IdentificationIcon class="h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />
        </a>
        <a
          :href="href('atleta/traspaso')"
          class="fvd-action-btn border-orange-300/90 bg-orange-50 text-orange-950 hover:border-orange-500 hover:bg-orange-100"
        >
          <span>Traspaso</span>
          <ArrowsRightLeftIcon class="h-5 w-5 shrink-0 text-orange-600" aria-hidden="true" />
        </a>
      </div>

      <!-- Torneo -->
      <div
        class="flex min-h-0 min-w-0 flex-col gap-2.5 overflow-y-auto rounded-xl border border-slate-300/90 bg-slate-100/80 p-3 shadow-inner"
      >
        <h2
          class="mb-1 border-b border-slate-300 pb-2 text-sm font-bold uppercase tracking-wider text-slate-800"
        >
          Torneo
        </h2>

        <div
          v-if="inscripcionBloqueada && torneoInvitacionId > 0 && livePendingAccept"
          class="rounded-lg border-2 border-amber-400 bg-amber-50 px-3 py-2 text-sm font-bold text-amber-950 shadow-sm"
          role="status"
        >
          <p class="leading-snug">Nuevo torneo: confirme la invitación para habilitar la inscripción de su asociación.</p>
          <button
            type="button"
            class="mt-2 w-full rounded-md border border-amber-600 bg-amber-600 px-3 py-2 text-center text-white shadow hover:bg-amber-700"
            @click="aceptarInvitacionTorneo"
          >
            Aceptar invitación
          </button>
        </div>
        <div
          v-if="delegadoAccess && delegadoAccess.nomina_solo_lectura && !livePendingAccept"
          class="rounded-lg border border-slate-400 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-800"
          role="status"
        >
          <p class="leading-snug">Nómina del torneo en solo consulta (fecha límite de cambios superada).</p>
        </div>

        <a
          v-if="!inscripcionBloqueada"
          :href="href('torneo/inscripcion')"
          class="fvd-action-btn border-[#2e3092]/35 bg-[#fff200]/22 text-[#1e1b4b] hover:border-[#2e3092]/55 hover:bg-[#fff200]/38"
        >
          <span>Inscripción</span>
          <TrophyIcon class="h-5 w-5 shrink-0 text-[#2e3092]" aria-hidden="true" />
        </a>
        <div
          v-else
          class="fvd-action-btn pointer-events-none cursor-not-allowed border-slate-300/90 bg-slate-200/90 text-slate-500 opacity-80"
          aria-disabled="true"
        >
          <span>Inscripción</span>
          <TrophyIcon class="h-5 w-5 shrink-0 text-slate-400" aria-hidden="true" />
        </div>
        <a
          v-if="!cambiosNominaBloqueados"
          :href="href('torneo/cambios')"
          class="fvd-action-btn border-[#2e3092]/40 bg-white text-[#2e3092] hover:border-[#be123c]/45 hover:bg-[#e8e9f4]"
        >
          <span>Cambios de nómina</span>
          <ClipboardDocumentListIcon class="h-5 w-5 shrink-0 text-[#be123c]" aria-hidden="true" />
        </a>
        <div
          v-else
          class="fvd-action-btn pointer-events-none cursor-not-allowed border-slate-300/90 bg-slate-200/90 text-slate-500 opacity-80"
          aria-disabled="true"
        >
          <span>Cambios de nómina</span>
          <ClipboardDocumentListIcon class="h-5 w-5 shrink-0 text-slate-400" aria-hidden="true" />
        </div>
      </div>

      <!-- Finanzas -->
      <div
        class="flex min-h-0 min-w-0 flex-col gap-2.5 overflow-y-auto rounded-xl border border-slate-300/90 bg-slate-100/80 p-3 shadow-inner"
      >
        <h2
          class="mb-1 border-b border-slate-300 pb-2 text-sm font-bold uppercase tracking-wider text-slate-800"
        >
          Finanzas
        </h2>
        <a
          :href="href('finanzas/deuda')"
          class="fvd-action-btn border-stone-300/90 bg-stone-100 text-stone-950 hover:border-stone-500 hover:bg-stone-200"
        >
          <span>Estado de cuenta</span>
          <CurrencyDollarIcon class="h-5 w-5 shrink-0 text-stone-700" aria-hidden="true" />
        </a>
        <a
          :href="href('finanzas/pagos')"
          class="fvd-action-btn border-emerald-300/90 bg-emerald-50 text-emerald-950 hover:border-emerald-500 hover:bg-emerald-100"
        >
          <span>Reportar pago</span>
          <BanknotesIcon class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true" />
        </a>
        <a
          :href="href('finanzas/consolidado')"
          class="fvd-action-btn border-slate-700 bg-slate-800 text-white shadow-md hover:border-slate-900 hover:bg-slate-900 focus-visible:ring-offset-slate-900"
        >
          <span>Balance / consolidado</span>
          <ChartBarIcon class="h-5 w-5 shrink-0 text-sky-300" aria-hidden="true" />
        </a>
      </div>
    </div>

    <p class="text-center text-xs font-semibold text-slate-500">
      Las rutas se resuelven en el servidor (módulos reales bajo <code class="rounded bg-slate-200 px-1">fvdmasteradmin</code> y
      <code class="rounded bg-slate-200 px-1">/modules/</code>).
    </p>

    <div
      v-if="showTorneoWelcome && torneoInvitacionId > 0"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="fvd-welcome-torneo-title"
    >
      <div class="max-w-md rounded-2xl border-2 border-indigo-300 bg-white p-6 shadow-2xl">
        <h3 id="fvd-welcome-torneo-title" class="text-lg font-bold text-slate-900">
          Bienvenida al torneo
        </h3>
        <p class="mt-2 text-base font-medium text-slate-700">
          Hay una invitación pendiente para
          <span class="font-bold text-indigo-900">{{ state.campeonatoNombre || state.currentTorneoNombre || 'su campeonato' }}</span
          >. Acepte para continuar con la inscripción (vista ampliada sin menú lateral).
        </p>
        <div class="mt-5 flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow hover:bg-indigo-700"
            @click="aceptarInvitacionTorneo"
          >
            Aceptar invitación
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"
            @click="dismissWelcomeLater"
          >
            Más tarde
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
