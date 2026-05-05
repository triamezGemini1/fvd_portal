/* empty css            *//* empty css                  */var e=typeof window<`u`&&window.FVD_GESTION_ASOC?window.FVD_GESTION_ASOC:{apiUrl:``};function t(e){let t=document.createElement(`div`);return t.textContent=e,t.innerHTML}function n(e){let t=e.trim();if(!t)return`?`;let n=t.split(/\s+/).filter(Boolean);return n.length>=2?(n[0][0]+n[n.length-1][0]).toUpperCase():t.slice(0,2).toUpperCase()}async function r(e,t){let n=await fetch(e,{credentials:`include`,...t}),r;try{r=await n.json()}catch{r={ok:!1,error:`Respuesta no JSON.`}}return{res:n,body:r}}function i(){let i=document.getElementById(`fvd-gestion-asoc-app`);if(!i||!e.apiUrl)return;let a={rows:[],meta:{isFvdAdmin:!1,canCreate:!1,canToggleEstatus:!1},total:0,page:1,perPage:25,pages:1,q:``,estado:`todas`,loading:!1,error:``,modal:null,formRow:null,saving:!1,modalCanEdit:!1},o=e.apiUrl;function s(e){a.error=e||``,m()}async function c(){a.loading=!0,a.error=``,m();let e=new URL(o,window.location.origin);e.searchParams.set(`action`,`list`),e.searchParams.set(`page`,String(a.page)),e.searchParams.set(`per_page`,String(a.perPage)),e.searchParams.set(`q`,a.q),e.searchParams.set(`estado`,a.estado);let{res:t,body:n}=await r(e.toString());if(a.loading=!1,!t.ok||!n.ok){s(n.error||`No se pudo cargar el listado.`);return}let i=n.data;a.rows=i.rows||[],a.total=i.total??0,a.page=i.page??1,a.perPage=i.per_page??25,a.pages=i.pages??1,i.meta&&(a.meta={...a.meta,...i.meta}),m()}async function l(e){a.error=``;let t=new URL(o,window.location.origin);t.searchParams.set(`action`,`get`),t.searchParams.set(`id`,String(e));let{res:n,body:i}=await r(t.toString());if(!n.ok||!i.ok){s(i.error||`No se pudo cargar la asociación.`);return}let c=i.data.raw&&typeof i.data.raw==`object`?i.data.raw:{},l=i.data.row&&typeof i.data.row==`object`?i.data.row:{};a.formRow={...c,logo_url:l.logo_url==null?c.logo_url:l.logo_url},a.modalCanEdit=i.data.meta&&i.data.meta.canEdit===!0,i.data.meta&&(a.meta={...a.meta,...i.data.meta}),m()}function u(){a.modal=null,a.formRow=null,a.modalCanEdit=!1,m()}async function d(e,t){if(a.modal=e,a.formRow=null,a.modalCanEdit=e===`create`,m(),e===`create`){a.formRow={nombre:``,delegado:``,telefono:``,email:``,numreg:``,providencia:``,direccion:``,fechreg:``,fechprovi:``,ultelECC:``},m();return}t!=null&&await l(t)}async function f(e){a.saving=!0,m();let t=new FormData(e);t.set(`action`,`save`);let{res:n,body:i}=await r(o,{method:`POST`,body:t});if(a.saving=!1,!n.ok||!i.ok){s(i.error||`No se pudo guardar.`);return}u(),await c()}async function p(e){let t=new FormData;t.set(`action`,`toggle`),t.set(`id`,String(e));let{res:n,body:i}=await r(o,{method:`POST`,body:t});if(!n.ok||!i.ok){s(i.error||`No se pudo cambiar el estatus.`),await c();return}let l=i.data&&i.data.row;l?a.rows=a.rows.map(e=>e.id===l.id?l:e):await c(),m()}function m(){let e=a.meta,r=a.modal===`view`||a.modal===null,o=r?`readonly disabled`:``,s=`w-full min-w-0 rounded-md border-2 border-slate-400 bg-white px-2 py-[0.3375rem] text-sm font-semibold leading-tight text-slate-950 antialiased shadow-sm placeholder:text-slate-500 focus:border-[#2e3092] focus:outline-none focus:ring-2 focus:ring-[#2e3092]/35 focus:ring-offset-1 focus:ring-offset-white disabled:border-slate-300 disabled:bg-slate-100 disabled:text-slate-800 disabled:shadow-none`,l=`mb-0.5 block text-[11px] font-extrabold uppercase leading-tight tracking-[0.1em] text-[#1a1d4d] antialiased`,m=a.formRow?String(a.formRow.logo_url||``).trim():``,h=a.formRow?String(a.formRow.nombre??``):``,g=n(h),_=m!==``,v=a.formRow?`
      <form id="fvd-asoc-form" class="space-y-2 text-slate-950 md:space-y-3">
        ${a.modal===`create`?``:`<input type="hidden" name="id" value="${t(String(a.formRow.id??``))}" />`}

        <!-- Fila 1: Logo + nombre -->
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
          <div class="flex shrink-0 flex-col gap-2 lg:w-[12rem]">
            <span class="${l}">Logo</span>
            <div class="relative flex aspect-square w-full max-w-[12rem] items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-[#2e3092]/50 bg-gradient-to-br from-white to-[#eef0ff] shadow-inner">
              <img id="fvd-asoc-logo-preview" src="${_?t(m):``}" alt="" class="max-h-full max-w-full object-contain p-1 ${_?``:`hidden`}" decoding="async" />
              <span id="fvd-asoc-logo-placeholder" class="absolute inset-0 flex items-center justify-center text-sm font-extrabold text-[#2e3092] ${_?`hidden`:``}">${t(g)}</span>
            </div>
            ${r?``:`<input type="file" id="fvd-asoc-logo-input" name="logo" accept="image/*" class="w-full max-w-[12rem] cursor-pointer text-[11px] font-semibold text-[#1a1d4d] file:mr-1 file:rounded-md file:border-2 file:border-[#2e3092]/40 file:bg-[#fff200]/25 file:px-2 file:py-1 file:text-[11px] file:font-bold file:text-[#252f7a] hover:file:bg-[#fff200]/45" />`}
          </div>
          <div class="min-w-0 flex-1">
            <label class="block min-w-0">
              <span class="${l}">Nombre de la asociación</span>
              <input name="nombre" required class="${s}" value="${t(h)}" ${o} />
            </label>
          </div>
        </div>

        <!-- Fila 2: Delegado + teléfono -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${l}">Delegado</span>
            <input name="delegado" class="${s}" value="${t(String(a.formRow.delegado??``))}" ${o} />
          </label>
          <label class="min-w-0">
            <span class="${l}">Teléfono</span>
            <input name="telefono" type="tel" class="${s}" value="${t(String(a.formRow.telefono??``))}" ${o} />
          </label>
        </div>

        <!-- Fila 3: Dirección + email -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${l}">Dirección</span>
            <input name="direccion" class="${s}" value="${t(String(a.formRow.direccion??``))}" ${o} />
          </label>
          <label class="min-w-0">
            <span class="${l}">Email</span>
            <input type="email" name="email" class="${s}" value="${t(String(a.formRow.email??``))}" ${o} />
          </label>
        </div>

        <!-- Fila 4: bloque legal en una línea -->
        <div class="rounded-xl border border-slate-200/90 bg-slate-50/90 px-3 py-3">
          <p class="mb-2 text-[10px] font-extrabold uppercase tracking-widest text-slate-500">Bloque legal</p>
          <div class="flex flex-wrap items-end gap-x-3 gap-y-3 lg:flex-nowrap lg:gap-x-3">
            <label class="min-w-[6.5rem] flex-1 basis-[8rem] lg:min-w-0">
              <span class="${l}">Nro. registro</span>
              <input name="numreg" class="${s}" value="${t(String(a.formRow.numreg??``))}" ${o} />
            </label>
            <label class="min-w-[8.5rem] flex-1 basis-[8rem] lg:min-w-0">
              <span class="${l}">Fecha registro</span>
              <input type="date" name="fechreg" class="${s}" value="${t(String((a.formRow.fechreg||``).toString().slice(0,10)))}" ${o} />
            </label>
            <label class="min-w-[7rem] flex-1 basis-[7rem] lg:min-w-0">
              <span class="${l}">Providencia</span>
              <input name="providencia" class="${s}" value="${t(String(a.formRow.providencia??``))}" ${o} />
            </label>
            <label class="min-w-[8.5rem] flex-1 basis-[8rem] lg:min-w-0">
              <span class="${l}">Fecha providencia</span>
              <input type="date" name="fechprovi" class="${s}" value="${t(String((a.formRow.fechprovi||``).toString().slice(0,10)))}" ${o} />
            </label>
            <label class="min-w-[8.5rem] flex-1 basis-[8rem] lg:min-w-0">
              <span class="${l}">Fecha última elección</span>
              <input type="date" name="ultelECC" class="${s}" value="${t(String((a.formRow.ultelECC||``).toString().slice(0,10)))}" ${o} />
            </label>
          </div>
        </div>
      </form>
    `:`<p class="py-6 text-center text-sm font-bold text-[#2e3092]">Cargando…</p>`,y=a.rows.map(r=>{let i=r.logo_url||``,a=n(r.nombre||``),o=i?`<img src="${t(i)}" alt="" class="h-10 w-10 rounded-full object-cover ring-2 ring-white shadow" />`:`<span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-slate-600 to-slate-800 text-xs font-semibold text-white shadow ring-2 ring-white">${t(a)}</span>`,s=e.canToggleEstatus?`<label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" class="peer sr-only" data-toggle-id="${r.id}" ${r.activa?`checked`:``} />
            <span class="relative inline-block h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-emerald-500 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-300 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition after:content-[''] peer-checked:after:translate-x-5"></span>
          </label>`:`<span class="text-xs text-slate-400">${r.activa?`Activa`:`Inactiva`}</span>`,c=!r.activa;return`<tr class="${c?`border-b border-slate-200/80 bg-slate-100/80 text-slate-500 hover:bg-slate-200/50`:`border-b border-slate-100 hover:bg-slate-50/80`}">
          <td class="whitespace-nowrap px-3 py-2">${o}</td>
          <td class="px-3 py-2 font-medium ${c?`text-slate-600`:`text-slate-800`}">${t(r.nombre||``)}</td>
          <td class="px-3 py-2 ${c?`text-slate-500`:`text-slate-600`}">${t(r.email||`—`)}</td>
          <td class="px-3 py-2 ${c?`text-slate-500`:`text-slate-600`}">${t(r.telefono||`—`)}</td>
          <td class="px-3 py-2">${s}</td>
          <td class="px-3 py-2 text-right">
            <button type="button" data-view="${r.id}" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">Ver</button>
            <button type="button" data-edit="${r.id}" class="rounded-lg px-2 py-1 text-xs font-bold text-[#2e3092] hover:bg-[#eef0ff]">Editar</button>
          </td>
        </tr>`}).join(``),b=a.modal!==null,x=a.modal===`create`?`Nueva asociación`:a.modal===`edit`?`Editar asociación`:a.modal===`view`?`Detalle`:``;i.innerHTML=`
      <div class="fvd-l13-app mx-auto max-w-6xl px-4 py-6">
        <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">Gestión de asociaciones</h1>
            <p class="mt-1 text-sm text-slate-500">Catálogo de clubes · sin recargar la página</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <input type="search" id="fvd-asoc-q" placeholder="Buscar…" value="${t(a.q)}" class="min-w-[12rem] rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm" />
            <select id="fvd-asoc-estado" class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm">
              <option value="todas" ${a.estado===`todas`?`selected`:``}>Todas</option>
              <option value="activas" ${a.estado===`activas`?`selected`:``}>Activas</option>
              <option value="inactivas" ${a.estado===`inactivas`?`selected`:``}>Inactivas</option>
            </select>
            <button type="button" id="fvd-asoc-apply" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow hover:bg-slate-800">Aplicar</button>
          </div>
        </header>

        ${a.error?`<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">${t(a.error)}</div>`:``}

        <div class="relative rounded-xl border border-slate-200 bg-white shadow-sm" data-fvd-section="asociaciones-list">
          <div class="max-h-[min(60vh,32rem)] overflow-auto overscroll-contain sm:max-h-[60vh]">
            <table class="min-w-full border-collapse text-left text-sm" aria-label="Listado de asociaciones">
              <thead class="sticky top-0 z-20 border-b border-slate-200 bg-slate-100 shadow-sm">
                <tr>
                  <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold text-slate-700">Logo</th>
                  <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold text-slate-700">Nombre</th>
                  <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold text-slate-700">Email</th>
                  <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold text-slate-700">Teléfono</th>
                  <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold text-slate-700">Activa</th>
                  <th scope="col" class="whitespace-nowrap px-3 py-3 text-right font-semibold text-slate-700">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                ${a.loading?`<tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">Cargando…</td></tr>`:a.rows.length===0?`<tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">Sin resultados.</td></tr>`:y}
              </tbody>
            </table>
          </div>
          <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-3 py-2 text-xs text-slate-500">
            <span>Total: ${a.total} · Página ${a.page} / ${Math.max(1,a.pages)}</span>
            <div class="flex gap-2">
              <button type="button" id="fvd-asoc-prev" class="rounded border border-slate-200 px-2 py-1 hover:bg-slate-50" ${a.page<=1?`disabled`:``}>Anterior</button>
              <button type="button" id="fvd-asoc-next" class="rounded border border-slate-200 px-2 py-1 hover:bg-slate-50" ${a.page>=a.pages?`disabled`:``}>Siguiente</button>
            </div>
          </footer>
        </div>
      </div>

      ${e.canCreate&&e.isFvdAdmin&&!b?`<button type="button" id="fvd-asoc-fab" class="fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[#2e3092] text-3xl font-light text-white shadow-lg ring-4 ring-[#fff200]/40 transition hover:bg-[#252f7a] hover:shadow-xl" title="Agregar nueva asociación">+</button>`:``}

      ${b?`<div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4">
        <div class="absolute inset-0 bg-[#0a0d24]/80 backdrop-blur-md" data-close-modal></div>
        <div class="relative z-10 flex min-h-0 w-[min(80vw,calc(100vw-2rem))] max-h-[min(82dvh,calc(100dvh-3rem))] flex-col overflow-hidden rounded-2xl shadow-2xl ring-2 ring-[#fff200]/35">
          <header class="flex shrink-0 items-start justify-between gap-3 border-b border-white/20 bg-gradient-to-r from-[#1a1f5c] via-[#2e3092] to-[#3d42c4] px-4 py-2.5 sm:px-5 sm:py-3">
            <div class="min-w-0">
              <h2 class="text-base font-extrabold tracking-tight text-white drop-shadow-sm sm:text-lg">${t(x)}</h2>
              <p class="mt-0.5 text-[10px] font-bold uppercase tracking-[0.2em] text-[#fff200] sm:text-[11px]">Federación Venezolana de Dominó</p>
            </div>
            <button type="button" class="shrink-0 rounded-lg px-2 py-1 text-xl leading-none text-white/90 transition hover:bg-white/15 hover:text-[#fff200]" data-close-modal aria-label="Cerrar">✕</button>
          </header>
          <div class="fvd-asoc-modal-body min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain bg-gradient-to-b from-[#f4f6ff] via-white to-white px-4 py-2.5 sm:px-5 sm:py-3">
          ${v}
          </div>
          <footer class="flex shrink-0 flex-wrap justify-end gap-2 border-t-2 border-[#2e3092]/20 bg-gradient-to-r from-white via-[#fafbff] to-[#eef0ff] px-4 py-2.5 shadow-[0_-4px_12px_rgba(30,41,120,0.08)] sm:px-5">
            ${a.modal===`view`&&a.formRow&&a.modalCanEdit?`<button type="button" id="fvd-asoc-to-edit" class="rounded-lg bg-[#2e3092] px-4 py-2 text-sm font-bold text-white shadow-md transition hover:bg-[#252f7a] hover:shadow-lg">Editar</button>`:``}
            ${a.modal===`edit`||a.modal===`create`?`<button type="button" id="fvd-asoc-save" class="rounded-lg bg-[#2e3092] px-4 py-2 text-sm font-bold text-white shadow-md transition hover:bg-[#252f7a] enabled:hover:ring-2 enabled:hover:ring-[#fff200]/50 disabled:opacity-60" ${a.saving?`disabled`:``}>${a.saving?`Guardando…`:`Guardar`}</button>`:``}
            <button type="button" class="rounded-lg border-2 border-[#2e3092]/50 bg-white px-4 py-2 text-sm font-bold text-[#252f7a] shadow-sm transition hover:border-[#2e3092] hover:bg-[#f4f6ff]" data-close-modal>Cerrar</button>
          </footer>
        </div>
      </div>`:``}
    `,i.querySelector(`#fvd-asoc-apply`)?.addEventListener(`click`,()=>{let e=i.querySelector(`#fvd-asoc-q`),t=i.querySelector(`#fvd-asoc-estado`);a.q=e?e.value.trim():``,a.estado=t?t.value:`todas`,a.page=1,c()}),i.querySelector(`#fvd-asoc-prev`)?.addEventListener(`click`,()=>{a.page>1&&(--a.page,c())}),i.querySelector(`#fvd-asoc-next`)?.addEventListener(`click`,()=>{a.page<a.pages&&(a.page+=1,c())}),i.querySelectorAll(`[data-view]`).forEach(e=>{e.addEventListener(`click`,async()=>{let t=parseInt(e.getAttribute(`data-view`)||`0`,10);t&&await d(`view`,t)})}),i.querySelectorAll(`[data-edit]`).forEach(e=>{e.addEventListener(`click`,async()=>{let t=parseInt(e.getAttribute(`data-edit`)||`0`,10);t&&await d(`edit`,t)})}),i.querySelectorAll(`[data-toggle-id]`).forEach(e=>{e.addEventListener(`change`,e=>{let t=e.target,n=parseInt(t.getAttribute(`data-toggle-id`)||`0`,10);n&&p(n)})}),i.querySelector(`#fvd-asoc-fab`)?.addEventListener(`click`,async()=>{await d(`create`,null)}),i.querySelectorAll(`[data-close-modal]`).forEach(e=>{e.addEventListener(`click`,()=>u())}),i.querySelector(`#fvd-asoc-to-edit`)?.addEventListener(`click`,async()=>{let e=a.formRow&&a.formRow.id?parseInt(String(a.formRow.id),10):0;e&&await d(`edit`,e)}),i.querySelector(`#fvd-asoc-save`)?.addEventListener(`click`,()=>{let e=i.querySelector(`#fvd-asoc-form`);e&&f(e)});let S=i.querySelector(`#fvd-asoc-logo-input`),C=i.querySelector(`#fvd-asoc-logo-preview`),w=i.querySelector(`#fvd-asoc-logo-placeholder`);S?.addEventListener(`change`,()=>{let e=S.files&&S.files[0];if(!e||!C)return;let t=new FileReader;t.onload=()=>{C.src=typeof t.result==`string`?t.result:``,C.classList.remove(`hidden`),w?.classList.add(`hidden`)},t.readAsDataURL(e)})}m(),c()}i();