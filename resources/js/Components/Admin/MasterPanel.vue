<script setup>
import { ref, computed, onMounted, onUnmounted, provide } from 'vue';
import { MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/solid';
import WorkspaceHome from './modules/WorkspaceHome.vue';
import WorkspaceEmbedded from './modules/WorkspaceEmbedded.vue';

/** Ajuste de altura dinámica (cabecera doble + franja búsqueda — ver --fvd-master-chrome-offset en app.css) */
const workspaceContentHeight = 'calc(100dvh - 120px)';

const props = defineProps({
  initialState: {
    type: Object,
    default: () => ({}),
  },
});

const dashboardState = ref({
  pendingApprovals: 0,
  pendingAffiliations: 0,
  pendingSolicitudes: 0,
  pendingPayments: 0,
  pendingAthletesLabel: '0',
  activeTournaments: 0,
  alerts: [],
  recentEvents: [],
  actionUrls: {},
  workspaceRoutes: {},
  contextTorneos: [],
  showContextSelector: false,
  selectedContextTorneoId: 0,
  contextGrupoEventoId: null,
  finanzasGrupo: null,
  masterPanelApiUrl: '',
  perfilUrl: '',
  logoutUrl: '',
  userLabel: '',
  fvdLogoUrl: '',
  partnerLogos: [],
  ...props.initialState,
});

const selectedContextTorneoId = ref(0);

const WORKSPACE_LABELS = {
  'servicios/asociaciones': 'Asociaciones',
  'servicios/atletas': 'Atletas',
  'servicios/torneos': 'Torneos',
  'servicios/atletas_reset': 'Reiniciar atletas',
  'operaciones/torneos': 'Gestión de torneos',
  'operaciones/assoc_torneo': 'Relación entre torneos (mismo día)',
  'operaciones/invitaciones': 'Invitaciones',
  'operaciones/portal_assoc': 'Portal asociación',
  'operaciones/torneos/editar': 'Editar torneos',
  'operaciones/inscripciones': 'Inscripciones',
  'operaciones/asociar/torneo': 'Asociar torneo',
  'operaciones/fichaje/social': 'Fichaje / social',
  'finanzas/general': 'Finanzas generales',
  'finanzas/por_torneo': 'Finanzas por torneo',
  'finanzas/por-torneo': 'Finanzas por torneo',
  'finanzas/deudas_pagos': 'Cartera (deudas y pagos)',
  afiliaciones: 'Afiliaciones',
  traspasos: 'Traspasos',
  carnets: 'Carnets',
  'torneos/crear': 'Crear torneo',
  'torneos/asociar': 'Inscripciones al torneo',
  'torneos/invitar': 'Invitaciones',
  'operaciones/enlace-simultaneo': 'Asociar torneos (simultáneos)',
  'operaciones/portal-asociacion': 'Portal asociación',
  'finanzas/deudas': 'Deudas',
  'finanzas/pagos': 'Pagos',
  'finanzas/consolidado': 'Consolidado financiero',
};

const showGrid = ref(true);
const embeddedSrc = ref('');
const embeddedTitle = ref('');
/** Clave de ruta del workspace activo (p. ej. `operaciones/torneos`); vacío en la rejilla inicial. */
const currentView = ref('');

function isOperacionesOrFinanzasView(key) {
  return typeof key === 'string' && (key.startsWith('operaciones/') || key.startsWith('finanzas/'));
}

const torneosAsociadosAlContexto = computed(() => {
  const list = dashboardState.value.contextTorneos || [];
  const tid = Number(selectedContextTorneoId.value);
  if (tid <= 0 || !Array.isArray(list) || list.length === 0) {
    return [];
  }
  const cur = list.find((t) => Number(t.torneo) === tid);
  if (!cur) {
    return [];
  }
  const gid = Number(cur.grupo_evento_id ?? 0);
  if (gid <= 0) {
    return [];
  }
  return list
    .filter((t) => Number(t.grupo_evento_id) === gid)
    .sort((a, b) => Number(a.torneo) - Number(b.torneo));
});

const showIframeTorneoSwitcher = computed(
  () =>
    !showGrid.value &&
    isOperacionesOrFinanzasView(currentView.value) &&
    torneosAsociadosAlContexto.value.length > 1,
);

const partnerLogos = computed(() => {
  const list = dashboardState.value.partnerLogos;
  return Array.isArray(list) ? list : [];
});

const hasAssociatedTournaments = computed(
  () =>
    Boolean(dashboardState.value.showContextSelector) &&
    Array.isArray(dashboardState.value.contextTorneos) &&
    dashboardState.value.contextTorneos.length > 0,
);

const hasAccountMenu = computed(
  () =>
    (typeof dashboardState.value.perfilUrl === 'string' && dashboardState.value.perfilUrl !== '') ||
    (typeof dashboardState.value.logoutUrl === 'string' && dashboardState.value.logoutUrl !== ''),
);

const adminInitials = computed(() => {
  const raw = String(dashboardState.value.userLabel || '').trim();
  if (!raw) {
    return 'FVD';
  }
  if (raw.includes('@')) {
    const local = (raw.split('@')[0] || '').replace(/[^a-zA-Z0-9áéíóúñü]/gi, ' ');
    const parts = local.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase().slice(0, 2);
    }
    return local.slice(0, 2).toUpperCase() || 'FVD';
  }
  const words = raw.split(/\s+/).filter(Boolean);
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase().slice(0, 2);
  }
  return raw.slice(0, 2).toUpperCase();
});

const accountMenuOpen = ref(false);
const accountMenuRoot = ref(null);

function toggleAccountMenu() {
  accountMenuOpen.value = !accountMenuOpen.value;
}

function closeAccountMenu() {
  accountMenuOpen.value = false;
}

function onDocumentPointerDown(e) {
  const root = accountMenuRoot.value;
  const t = e.target;
  if (!root || !(t instanceof Node) || !accountMenuOpen.value) {
    return;
  }
  if (!root.contains(t)) {
    closeAccountMenu();
  }
}

function applyContextToModuleUrl(baseUrl) {
  try {
    const u = new URL(baseUrl, window.location.origin);
    const tid = Number(dashboardState.value.selectedContextTorneoId ?? 0);
    const gid = dashboardState.value.contextGrupoEventoId;
    if (tid > 0) {
      u.searchParams.set('torneo_id', String(tid));
    }
    if (gid != null && Number(gid) > 0) {
      u.searchParams.set('campeonato_id', String(gid));
    }
    u.searchParams.set('embedded', '1');
    u.searchParams.set('fvd_master_embed', '1');
    return u.pathname + u.search + u.hash;
  } catch {
    return baseUrl;
  }
}

function reapplyEmbeddedUrl() {
  const key = currentView.value;
  if (!key) {
    return;
  }
  const routes = dashboardState.value.workspaceRoutes || {};
  const url = routes[key];
  if (!url || typeof url !== 'string') {
    return;
  }
  embeddedSrc.value = applyContextToModuleUrl(url);
}

function navigateToModule(key) {
  const routes = dashboardState.value.workspaceRoutes || {};
  const url = routes[key];
  if (!url || typeof url !== 'string') {
    return;
  }
  currentView.value = key;
  embeddedSrc.value = applyContextToModuleUrl(url);
  embeddedTitle.value = WORKSPACE_LABELS[key] || key;
  showGrid.value = false;
}

function goHome() {
  showGrid.value = true;
  embeddedSrc.value = '';
  embeddedTitle.value = '';
  currentView.value = '';
}

async function onContextTorneoChange() {
  const api = dashboardState.value.masterPanelApiUrl;
  const id = Number(selectedContextTorneoId.value);
  if (!api || id <= 0) {
    return;
  }
  try {
    const sep = api.includes('?') ? '&' : '?';
    const res = await fetch(`${api}${sep}torneo_id=${id}`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) {
      return;
    }
    const data = await res.json();
    dashboardState.value = { ...dashboardState.value, ...data };
    selectedContextTorneoId.value = Number(data.selectedContextTorneoId ?? id);
    if (!showGrid.value && currentView.value) {
      reapplyEmbeddedUrl();
    }
  } catch (e) {
    console.error('[master_panel context]', e);
  }
}

async function onEmbeddedTorneoSelect(id) {
  selectedContextTorneoId.value = Number(id);
  reapplyEmbeddedUrl();
  await onContextTorneoChange();
}

provide('fvdWorkspace', {
  navigate: navigateToModule,
  goHome,
  applyContextToUrl: applyContextToModuleUrl,
});

const searchRef = ref(null);

function onGlobalKeydown(e) {
  if (e.key === 'Escape') {
    closeAccountMenu();
    return;
  }
  if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
    e.preventDefault();
    searchRef.value?.focus?.();
  }
}

onMounted(() => {
  selectedContextTorneoId.value = Number(dashboardState.value.selectedContextTorneoId ?? 0);
  window.addEventListener('keydown', onGlobalKeydown);
  document.addEventListener('pointerdown', onDocumentPointerDown, true);
});
onUnmounted(() => {
  window.removeEventListener('keydown', onGlobalKeydown);
  document.removeEventListener('pointerdown', onDocumentPointerDown, true);
});

defineExpose({ dashboardState, navigateToModule, goHome });
</script>

<template>
  <main
    class="font-sans flex min-h-0 flex-1 flex-col overflow-hidden bg-slate-50 text-base font-medium tracking-tight text-slate-900"
  >
    <header
      class="fvd-header-vue fvd-header-main flex w-full flex-col shadow-sm"
      aria-label="Navegación principal institucional FVD"
    >
      <div
        class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-medium text-slate-500 sm:px-4"
      >
        <span class="min-w-0 truncate">Federación Venezolana de Dominó - Órgano Rector Nacional</span>
        <div class="flex shrink-0 items-center gap-3 opacity-70 sm:gap-4">
          <img
            v-for="(p, idx) in partnerLogos"
            :key="idx"
            :src="p.src"
            :alt="p.alt"
            class="h-4 w-auto max-w-[4rem] object-contain object-center sm:max-w-[5rem]"
            loading="lazy"
            decoding="async"
          />
        </div>
      </div>

      <div
        class="flex flex-wrap items-center justify-between gap-2 border-b-4 border-[#ffcc00] bg-[#00247d] px-3 py-2 text-white sm:flex-nowrap sm:px-4"
      >
        <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
          <img
            v-if="dashboardState.fvdLogoUrl"
            :src="dashboardState.fvdLogoUrl"
            alt="Federación Venezolana de Dominó"
            class="h-10 w-auto shrink-0 object-contain object-left"
            width="180"
            height="40"
            loading="eager"
            decoding="async"
          />
          <h1 class="hidden min-w-0 truncate text-lg font-black leading-none tracking-tighter text-white md:block">
            FEDERACIÓN VENEZOLANA DE DOMINÓ
          </h1>
        </div>

        <div class="flex shrink-0 items-center gap-2 sm:gap-4">
          <div
            v-if="hasAssociatedTournaments"
            class="rounded border border-white/20 bg-white/10 px-2 py-1"
          >
            <label class="sr-only" for="fvd-ctx-torneo">Torneo asociado al contexto</label>
            <select
              id="fvd-ctx-torneo"
              v-model.number="selectedContextTorneoId"
              class="max-w-[min(100vw-8rem,22rem)] cursor-pointer bg-transparent text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-[#ffcc00] focus:ring-offset-0 rounded-sm"
              @change="onContextTorneoChange"
            >
              <option
                v-for="t in dashboardState.contextTorneos"
                :key="t.torneo"
                :value="t.torneo"
                class="text-black"
              >
                {{ t.grupo_label }} — {{ t.nombre }}
              </option>
            </select>
          </div>

          <div v-if="hasAccountMenu" ref="accountMenuRoot" class="relative">
            <button
              type="button"
              class="flex items-center gap-2 rounded p-1 transition hover:bg-white/10"
              :aria-expanded="accountMenuOpen"
              aria-haspopup="true"
              :aria-label="(dashboardState.userLabel || 'Cuenta') + ' — menú'"
              @click.stop="toggleAccountMenu"
            >
              <div
                class="flex h-8 w-8 items-center justify-center rounded-full bg-[#ffcc00] text-xs font-bold text-[#00247d]"
              >
                {{ adminInitials }}
              </div>
            </button>
            <div
              v-show="accountMenuOpen"
              role="menu"
              class="absolute right-0 top-[calc(100%+6px)] z-[200] min-w-[12rem] rounded-lg border border-white/20 bg-slate-900 py-1 shadow-xl ring-1 ring-black/30"
              @click.stop
            >
              <a
                v-if="dashboardState.perfilUrl"
                role="menuitem"
                class="block px-3 py-2 text-sm font-semibold text-white hover:bg-white/10"
                :href="dashboardState.perfilUrl"
                @click="closeAccountMenu"
              >Mi perfil</a>
              <a
                v-if="dashboardState.logoutUrl"
                role="menuitem"
                class="block px-3 py-2 text-sm font-semibold text-rose-200 hover:bg-rose-950/40"
                :class="dashboardState.perfilUrl ? 'border-t border-white/10' : ''"
                :href="dashboardState.logoutUrl"
                @click="closeAccountMenu"
              >Cerrar sesión</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Franja acción rápida (búsqueda) -->
    <div
      class="flex h-9 shrink-0 items-center gap-2 border-b border-black/10 bg-[color:var(--fvd-azul-venezuela)] px-3 sm:px-4"
      aria-label="Acción rápida"
    >
      <div class="relative min-w-0 flex-1">
        <span
          class="pointer-events-none absolute left-2.5 top-1/2 flex size-4 -translate-y-1/2 items-center justify-center text-slate-300 [&>svg]:size-4 [&>svg]:shrink-0"
          aria-hidden="true"
        >
          <MagnifyingGlassIcon class="block" />
        </span>
        <input
          ref="searchRef"
          type="search"
          placeholder="Acción rápida (Ctrl + K)"
          class="w-full min-w-0 rounded border border-white/20 bg-white/10 py-1 pl-8 pr-2 text-xs font-semibold text-white placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-[color:var(--fvd-amarillo-oro)]"
          autocomplete="off"
        />
      </div>
      <div class="hidden shrink-0 items-center gap-2 sm:flex">
        <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400" aria-hidden="true" />
        <span class="text-xs font-semibold text-slate-200">Online</span>
      </div>
    </div>

    <section
      class="flex min-h-0 flex-1 flex-col overflow-hidden"
      :style="{ '--fvd-workspace-ideal-height': workspaceContentHeight }"
    >
      <WorkspaceHome v-if="showGrid" :state="dashboardState" />
      <WorkspaceEmbedded
        v-else
        :src="embeddedSrc"
        :title="embeddedTitle"
        :show-torneo-switcher="showIframeTorneoSwitcher"
        :torneo-options="torneosAsociadosAlContexto"
        :selected-torneo-id="selectedContextTorneoId"
        @update:selected-torneo-id="onEmbeddedTorneoSelect"
        @back="goHome"
      />
    </section>

    <footer
      class="shrink-0 border-t border-black/20 bg-slate-900 px-3 py-1.5 text-center text-[10px] leading-snug text-slate-400"
      role="contentinfo"
    >
      Órgano Rector del Dominó Nacional. Reconocido por el Ministerio del Poder Popular para el Deporte.
      Registro Nacional del Deporte #XXXXX
    </footer>

    <button
      v-show="showGrid"
      type="button"
      class="fixed bottom-14 right-4 z-10 flex h-11 w-11 items-center justify-center rounded-full border-2 border-[color:var(--fvd-amarillo-oro)] bg-[color:var(--fvd-amarillo-oro)] p-0 text-[color:var(--fvd-azul-venezuela)] shadow-lg transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[color:var(--fvd-amarillo-oro)] sm:bottom-16 sm:right-5 sm:h-12 sm:w-12"
      title="Acción rápida"
      aria-label="Acción rápida"
    >
      <span class="flex size-6 items-center justify-center [&>svg]:size-6 [&>svg]:shrink-0">
        <PlusIcon class="block" aria-hidden="true" />
      </span>
    </button>
  </main>
</template>

<style>
#fvd-master-app {
  margin: 0 !important;
  padding: 0 !important;
}

#fvd-shell,
.fvd-main-column,
.fvd-main {
  padding-top: 0 !important;
  margin-top: 0 !important;
}

html.fvd-master-html-reset,
body.fvd-master-body-reset {
  padding-top: 0 !important;
  margin-top: 0 !important;
}

.fvd-header-main {
  position: sticky;
  top: 0;
  z-index: 100;
  width: 100%;
}
</style>
