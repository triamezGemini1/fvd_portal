/* empty css            *//* empty css                  */var e=typeof window<`u`&&window.FVD_GESTION_ATLETAS?window.FVD_GESTION_ATLETAS:{apiUrl:``},t=(e.referencialUrl||``).trim(),n=e.embeddedMaster===!0;function r(e){let t=document.createElement(`div`);return t.textContent=e,t.innerHTML}function i(e){let t=e.trim();if(!t)return`?`;let n=t.split(/\s+/).filter(Boolean);return n.length>=2?(n[0][0]+n[n.length-1][0]).toUpperCase():t.slice(0,2).toUpperCase()}async function a(e,t){let n=await fetch(e,{credentials:`include`,...t}),r;try{r=await n.json()}catch{r={ok:!1,error:`Respuesta no JSON.`}}return{res:n,body:r}}function o(e,t){let n=Number(e);return Number(t)===0?`Pendiente`:n===9?`Inactivo`:n===0||n===1?`Activo`:n===2?`Baja`:n===990?`Pend. adm.`:`—`}function s(t,n,r){let i=(e.reportsBase||``).trim();if(!i)return`#`;let a=i.endsWith(`/`)?i:`${i}/`,o;try{o=new URL(t.replace(/^\//,``),a)}catch{return`#`}let s=new URL(window.location.href);return[`embedded`,`fvd_master_embed`,`torneo_id`,`ctx_torneo`,`campeonato_id`,`ret`,`return`,`fvd_ws`].forEach(e=>{let t=s.searchParams.get(e);t!==null&&t!==``&&o.searchParams.set(e,t)}),n&&r>0&&o.searchParams.set(`asociacion_id`,String(r)),o.searchParams.has(`ret`)||o.searchParams.set(`ret`,s.pathname+(s.search||``)),o.pathname+o.search}function c(e,t){if(!e)return``;let n=e?.foto_url?String(e.foto_url):``,a=n?`<img src="${r(n)}" alt="" class="mx-auto h-10 w-10 rounded-lg object-cover ring-1 ring-slate-200/80 shadow-sm" />`:`<span class="mx-auto flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-[10px] font-bold text-slate-500">${r(i(String(e?.nombre??``)))}</span>`,s=Number(e?.numfvd??0),c=s>0?String(s):`—`,l=e?.cedula!=null&&String(e?.cedula).trim()!==``?String(e?.cedula):`—`,u=r(l),d=`<div class="flex max-w-[13rem] items-center gap-1.5 whitespace-nowrap text-left text-[13px] font-semibold leading-tight text-slate-800" title="${e?.identidad_label!=null&&String(e?.identidad_label).trim()!==``?r(String(e?.identidad_label).replace(/\n/g,` · `)):`C.I. ${u}`}">
            <span class="shrink-0 tabular-nums text-slate-700">Nº ${r(c)}</span>
            <span class="shrink-0 text-slate-300" aria-hidden="true">·</span>
            <span class="min-w-0 truncate text-slate-800" title="C.I. ${u}">CI ${r(l)}</span>
          </div>`,f=Number(e?.estatus??0),p=Number(e?.numfvd??0),m=f===1||f===0&&p>0,h=!!t?.canToggle&&![2,9].includes(f),g=Number(e?.id??0),_=h?`<label class="relative inline-flex cursor-pointer items-center justify-center" title="Activo (1) / pendiente (0)">
            <input type="checkbox" class="peer sr-only" data-toggle-atleta="${g}" ${m?`checked`:``} />
            <span class="relative inline-block h-6 w-11 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-emerald-500 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-300 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition after:content-[''] peer-checked:after:translate-x-5"></span>
          </label>`:`<span class="block text-center text-[11px] font-bold leading-tight tracking-tight text-slate-600">${r(o(f,p))}</span>`;return`<tr class="border-b border-slate-100/90 transition-colors hover:bg-slate-50/95">
          <td class="w-[3.58rem] max-w-[3.58rem] px-1 py-2 align-middle">${a}</td>
          <td class="w-[13rem] max-w-[13rem] px-1.5 py-2 align-middle">${d}</td>
          <td class="min-w-0 px-1.5 py-2 align-middle font-medium text-slate-800"><span class="block min-w-0 truncate" title="${r(String(e?.nombre??``))}">${r(String(e?.nombre??``))}</span></td>
          <td class="hidden w-[7.35rem] max-w-[7.35rem] px-1 py-2 align-middle text-slate-600 lg:table-cell"><span class="line-clamp-2 text-[11px] font-medium leading-snug tracking-tight" title="${r(String(e?.asociacion_nombre??``))}">${r(String(e?.asociacion_nombre??`—`))}</span></td>
          <td class="w-[3.5rem] max-w-[3.5rem] px-0.5 py-2 align-middle">${_}</td>
          <td class="w-[7.5rem] max-w-[7.5rem] px-1 py-2 align-middle">
            <div class="flex flex-nowrap items-center justify-center gap-1.5">
              <button type="button" data-view="${g}" class="inline-flex shrink-0 items-center gap-0.5 rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold leading-none text-slate-700 shadow-sm hover:bg-slate-50" title="Ver"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 opacity-95" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg><span>Ver</span></button>
              <button type="button" data-edit="${g}" class="inline-flex shrink-0 items-center gap-0.5 rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-[10px] font-bold leading-none text-indigo-900 shadow-sm hover:bg-indigo-100" title="Editar"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 opacity-95" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg><span>Editar</span></button>
            </div>
          </td>
        </tr>`}function l(){let l=document.getElementById(`fvd-gestion-atletas-app`);if(!l||!e.apiUrl)return;l.addEventListener(`click`,e=>{let t=e.target;if(t instanceof Element){if(!n&&t.closest(`#fvd-atleta-regresar`)){e.preventDefault(),window.history.length>1&&window.history.back();return}t.closest(`#fvd-atleta-nuevo`)&&(e.preventDefault(),C(`create`,null))}});let u={rows:[],meta:{isFvdAdmin:!1,canCreate:!0,canToggle:!1,asociaciones:[]},total:0,page:1,perPage:10,pages:1,q:``,asociacionFilter:0,tipo:`normal`,loading:!1,error:``,personaHint:``,modal:null,formRow:null,saving:!1,modalCanEdit:!1,cedulaDuplicada:!1,referencialLoading:!1,duplicadoExistente:null},d=e.apiUrl,f=null,p=null,m=!1,h=0,g=0;function _(e){u.error=e||``,T()}function v(){let e=l.querySelector(`#fvd-atleta-loading-dot`);e instanceof HTMLElement&&(e.classList.toggle(`opacity-0`,!u.loading),e.classList.toggle(`opacity-100`,u.loading),e.classList.toggle(`animate-pulse`,u.loading));let t=l.querySelector(`#fvd-atleta-scroll`);t instanceof HTMLElement&&(t.classList.toggle(`opacity-[0.72]`,u.loading),t.classList.toggle(`opacity-100`,!u.loading))}async function y(){u.loading=!0,u.error=``,v();let e=new URL(d,window.location.href);e.searchParams.set(`action`,`list`),e.searchParams.set(`page`,String(u.page)),e.searchParams.set(`per_page`,String(u.perPage)),e.searchParams.set(`q`,u.q),e.searchParams.set(`tipo`,u.tipo),u.meta.isFvdAdmin&&u.asociacionFilter>0&&e.searchParams.set(`id_asociacion`,String(u.asociacionFilter));let{res:t,body:n}=await a(e.toString());if(u.loading=!1,!t.ok||!n.ok){_(n.error||`No se pudo cargar el listado.`);return}let r=n.data;u.rows=Array.isArray(r.rows)?r.rows.filter(e=>typeof e==`object`&&!!e):[],u.total=r.total??0,u.page=r.page??1,u.perPage=r.per_page??10,u.pages=r.pages??1,r.meta&&(u.meta={...u.meta,...r.meta}),T()}async function b(e){u.error=``;let t=new URL(d,window.location.href);t.searchParams.set(`action`,`get`),t.searchParams.set(`id`,String(e));let{res:n,body:r}=await a(t.toString());if(!n.ok||!r.ok){_(r.error||`No se pudo cargar el atleta.`);return}let i=r.data.raw&&typeof r.data.raw==`object`?r.data.raw:{},o=r.data.row&&typeof r.data.row==`object`?r.data.row:{};u.formRow={...i,...o},u.modalCanEdit=r.data.meta&&r.data.meta.canEdit===!0,r.data.meta&&(u.meta={...u.meta,...r.data.meta}),T()}async function x(e){let n=String(e).trim();if(u.modal!==`create`||!u.formRow)return;if(!n){u.cedulaDuplicada=!1,u.referencialLoading=!1,u.personaHint=``,u.duplicadoExistente=null,T();return}u.referencialLoading=!0,T();let r=new URL(d,window.location.href);r.searchParams.set(`action`,`cedula_disponible`),r.searchParams.set(`cedula`,n);let i=a(r.toString()),o=t?a((()=>{let e=new URL(t,window.location.href);return e.searchParams.set(`cedula`,n),e.toString()})()):Promise.resolve({res:{ok:!1},body:{ok:!1}}),[s,c]=await Promise.all([i,o]);if(!(u.modal!==`create`||u.formRow==null)){if(u.referencialLoading=!1,s.res.ok&&s.body.ok&&s.body.data){let e=s.body.data;u.cedulaDuplicada=e.existe===!0,u.duplicadoExistente=e.existente&&typeof e.existente==`object`?e.existente:null}else u.cedulaDuplicada=!1,u.duplicadoExistente=null;if(u.cedulaDuplicada&&u.duplicadoExistente)u.personaHint=``;else if(t&&c.res.ok&&c.body.ok)if(c.body.found&&c.body.data&&typeof c.body.data==`object`){let e=c.body.data,t=String(e.nombre1??``),n=String(e.nombre2??``),r=String(e.apellido1??``),i=String(e.apellido2??``);u.formRow.nombres=[t,n].filter(Boolean).join(` `).trim(),u.formRow.apellidos=[r,i].filter(Boolean).join(` `).trim(),u.formRow.fechnac=String(e.fechnac||``).slice(0,10);let a=String(e.sexo||``).trim().toUpperCase();u.formRow.sexo=a===`F`?2:+(a===`M`);let o=[u.formRow.nombres,u.formRow.apellidos].filter(Boolean).join(` `).trim();u.formRow.nombre=o,u.personaHint=`Datos cargados desde referencial nacional.`}else u.personaHint=`Sin coincidencia en referencial nacional.`;else t?u.personaHint=`No se pudo consultar el referencial.`:u.personaHint=``;T()}}function S(){u.modal=null,u.formRow=null,u.modalCanEdit=!1,u.personaHint=``,u.cedulaDuplicada=!1,u.referencialLoading=!1,u.duplicadoExistente=null,f!=null&&(clearTimeout(f),f=null),T()}async function C(e,t){if(u.modal=e,u.formRow=null,u.modalCanEdit=e===`create`,u.personaHint=``,u.cedulaDuplicada=!1,u.referencialLoading=!1,u.duplicadoExistente=null,e===`create`){u.formRow={cedula:``,nombres:``,apellidos:``,nombre:``,sexo:1,numfvd:0,fechnac:``,profesion:``,direccion:``,celular:``,email:``,asociacion:u.meta.isFvdAdmin?u.asociacionFilter>0?String(u.asociacionFilter):``:u.meta.asociaciones&&u.meta.asociaciones[0]?String(u.meta.asociaciones[0].id):``,foto_url:``,foto:``},T();return}T(),t!=null&&await b(t)}async function ee(e){if(u.modal===`create`&&u.cedulaDuplicada){_(`Esta cédula ya está registrada como atleta.`);return}u.saving=!0,T();let t=new FormData(e);t.set(`action`,`save`);let{res:n,body:r}=await a(d,{method:`POST`,body:t});if(u.saving=!1,!n.ok||!r.ok){_(r.error||`No se pudo guardar.`);return}S(),await y()}async function te(e){let t=new FormData;t.set(`action`,`toggle_activo`),t.set(`id`,String(e));let{res:n,body:r}=await a(d,{method:`POST`,body:t});if(!n.ok||!r.ok){_(r.error||`No se pudo cambiar el estatus.`),await y();return}let i=r.data&&r.data.row;i&&i.id!=null&&Number(i.id)>0?u.rows=u.rows.map(e=>e?.id===i.id?i:e):await y(),T()}function w(){let e=l.querySelector(`#fvd-atleta-form`);if(!e||!u.formRow||u.modal===null||u.modal===`view`)return;let t=t=>{let n=e.elements.namedItem(t);return n?(n instanceof RadioNodeList,n.value):``};u.formRow.cedula=t(`cedula`),u.formRow.nombres=t(`nombres`),u.formRow.apellidos=t(`apellidos`),u.formRow.fechnac=t(`fechnac`),u.formRow.celular=t(`celular`),u.formRow.email=t(`email`),u.formRow.direccion=t(`direccion`),u.formRow.profesion=t(`profesion`);let n=parseInt(t(`sexo`)||`0`,10);Number.isNaN(n)||(u.formRow.sexo=n);let r=t(`asociacion`);r!==``&&(u.formRow.asociacion=r);let i=t(`estatus`);if(i!==``){let e=parseInt(i,10);Number.isNaN(e)||(u.formRow.estatus=e)}}function T(){w();let t=u.meta,a=u.modal===`view`||u.modal===null,d=a?`readonly disabled`:``,_=u.modal===`create`?`w-full min-h-[2.85rem] rounded-lg border-2 border-slate-600 bg-white px-3 py-2.5 text-sm font-semibold leading-snug text-slate-950 shadow-sm placeholder:text-slate-500 focus:border-[#2e3092] focus:outline-none focus:ring-2 focus:ring-[#2e3092]/35 disabled:border-slate-400 disabled:bg-slate-100 disabled:text-slate-700`:`w-full max-w-[12rem] min-h-[3.3rem] rounded-lg border-2 border-slate-700 bg-white px-3 py-3 text-sm font-semibold leading-snug text-slate-950 shadow-sm placeholder:text-slate-600 focus:border-[#2e3092] focus:outline-none focus:ring-2 focus:ring-[#2e3092]/35 disabled:border-slate-500 disabled:bg-slate-200 disabled:text-slate-800`,v=`mb-1 block text-[11px] font-black uppercase tracking-[0.06em] text-slate-950`,b=u.formRow,T=b?String(b.foto_url||``).trim():``,E=T!==``,D=i(b?String(b.nombre||``):``),O=u.referencialLoading?`Consultando referencial…`:u.cedulaDuplicada?``:u.personaHint,k=u.duplicadoExistente,A=u.modal===`create`&&u.cedulaDuplicada&&k?`<div class="rounded-lg border-2 border-amber-800 bg-amber-100 p-3 text-slate-950 shadow-md">
            <p class="mb-2 text-xs font-black uppercase tracking-wide text-amber-950">Registro existente en sistema</p>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
              <div><dt class="font-bold text-slate-950">Nombre</dt><dd class="font-semibold text-slate-950">${r(String(k?.nombre||`—`))}</dd></div>
              <div><dt class="font-bold text-slate-950">Nº FVD</dt><dd class="font-semibold tabular-nums text-slate-950">${r(String(k?.numfvd??`—`))}</dd></div>
              <div><dt class="font-bold text-slate-950">Asociación</dt><dd class="font-semibold text-slate-950">${r(String(k?.asociacion||`—`))}</dd></div>
              <div><dt class="font-bold text-slate-950">Estatus</dt><dd class="font-semibold text-slate-950">${r(o(Number(k?.estatus),Number(k?.numfvd??0)))}</dd></div>
              <div><dt class="font-bold text-slate-950">Email</dt><dd class="font-semibold text-slate-950">${r(String(k?.email||`—`))}</dd></div>
              <div><dt class="font-bold text-slate-950">Celular</dt><dd class="font-semibold text-slate-950">${r(String(k?.celular||`—`))}</dd></div>
              <div><dt class="font-bold text-slate-950">F. nac.</dt><dd class="font-semibold text-slate-950">${r(String(k?.fechnac||`—`))}</dd></div>
              <div><dt class="font-bold text-slate-950">Profesión</dt><dd class="font-semibold text-slate-950">${r(String(k?.profesion||`—`))}</dd></div>
              <div class="sm:col-span-2"><dt class="font-bold text-slate-950">Dirección</dt><dd class="font-semibold text-slate-950">${r(String(k?.direccion||`—`))}</dd></div>
            </dl>
          </div>`:``,j=t.isFvdAdmin&&Array.isArray(t.asociaciones)?`<option value="0" ${u.asociacionFilter===0?`selected`:``}>Todas las asociaciones</option>${t.asociaciones.map(e=>`<option value="${e.id}" ${u.asociacionFilter===Number(e.id)?`selected`:``}>${r(e.nombre||``)}</option>`).join(``)}`:``,M=b?u.modal===`view`?`<div class="min-w-0">
            <span class="${v}">Estatus</span>
            <div class="flex min-h-[3.3rem] max-w-[12rem] items-center rounded-lg border-2 border-slate-700 bg-white px-3 text-sm font-semibold text-slate-950">${r(o(Number(b.estatus),Number(b.numfvd??0)))}</div>
          </div>`:u.modal===`edit`&&t.isFvdAdmin?`<label class="min-w-0">
            <span class="${v}">Estatus</span>
            <select name="estatus" class="${_}" ${d}>
              <option value="0" ${Number(b.estatus)===0?`selected`:``}>Pendiente</option>
              <option value="1" ${Number(b.estatus)===1?`selected`:``}>Activo</option>
              <option value="2" ${Number(b.estatus)===2?`selected`:``}>Baja</option>
              <option value="990" ${Number(b.estatus)===990?`selected`:``}>Pend. aprob. FVD</option>
            </select>
          </label>`:u.modal===`create`?`<div class="min-w-0">
            <span class="${v}">Estatus</span>
            <div class="flex min-h-[2.85rem] max-w-full items-center rounded-lg border-2 border-slate-500 bg-slate-200/90 px-3 text-sm font-semibold text-slate-900">Al guardar se aplican las reglas FVD (pendiente / activo).</div>
          </div>`:`<input type="hidden" name="estatus" value="${r(String(b.estatus??0))}" />`:``,N=u.modal===`create`?`relative mx-auto flex aspect-[3/4] max-h-[7.7rem] w-full max-w-[7.7rem] items-center justify-center overflow-hidden rounded-xl border-2 border-slate-600 bg-white shadow-sm sm:mx-0`:`relative mx-auto flex aspect-[3/4] max-h-28 w-full max-w-[7rem] items-center justify-center overflow-hidden rounded-xl border-2 border-slate-700 bg-white shadow-sm sm:mx-0`,ne=b?u.modal===`create`?`<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-6">
          <div class="shrink-0">
            <span class="${v}">Foto</span>
            <div class="${N}">
              <img id="fvd-atleta-foto-prev" src="${E?r(T):``}" alt="" class="max-h-full max-w-full object-cover ${E?``:`hidden`}" decoding="async" />
              <span id="fvd-atleta-foto-ph" class="text-lg font-bold text-slate-600 ${E?`hidden`:``}">${r(D)}</span>
            </div>
            ${a?``:`<input type="file" id="fvd-atleta-foto-inp" name="foto" accept="image/*" class="mt-2 w-full max-w-[7.7rem] text-xs font-medium text-slate-950 file:mr-2 file:rounded-md file:border-2 file:border-slate-600 file:bg-white file:px-2 file:py-2 file:text-xs file:font-semibold" />`}
          </div>
          <div class="min-w-0 flex-1 space-y-3">
            <label class="block">
              <span class="${v}">Cédula</span>
              <input name="cedula" id="fvd-atleta-cedula" required class="${_}" value="${r(String(b?.cedula??``))}" ${d} />
            </label>
            ${O?`<p class="mt-1 text-xs font-semibold text-slate-800">${r(O)}</p>`:``}
            <label class="block">
              <span class="${v}">Nº FVD (asignación)</span>
              <input name="numfvd_display" class="${_} bg-slate-100" readonly disabled value="${r(String(b?.numfvd??0))}" />
            </label>
          </div>
        </div>`:`<div class="grid grid-cols-1 gap-3 sm:grid-cols-12 sm:gap-x-3 sm:gap-y-2">
          <div class="sm:col-span-2">
            <span class="${v}">Foto</span>
            <div class="${N}">
              <img id="fvd-atleta-foto-prev" src="${E?r(T):``}" alt="" class="max-h-full max-w-full object-cover ${E?``:`hidden`}" decoding="async" />
              <span id="fvd-atleta-foto-ph" class="text-lg font-bold text-slate-600 ${E?`hidden`:``}">${r(D)}</span>
            </div>
            ${a?``:`<input type="file" id="fvd-atleta-foto-inp" name="foto" accept="image/*" class="mt-1 w-full max-w-[12rem] text-xs font-medium text-slate-950 file:mr-2 file:rounded-md file:border-2 file:border-slate-700 file:bg-white file:px-2 file:py-2 file:text-xs file:font-semibold" />`}
          </div>
          <div class="sm:col-span-5 min-w-0">
            <label class="block">
              <span class="${v}">Cédula</span>
              <input name="cedula" id="fvd-atleta-cedula" required class="${_}" value="${r(String(b?.cedula??``))}" ${d} />
            </label>
            ${O?`<p class="mt-1.5 text-xs font-semibold text-slate-950">${r(O)}</p>`:``}
          </div>
          <label class="sm:col-span-3 min-w-0 sm:col-start-auto">
            <span class="${v}">Nº FVD</span>
            <input name="numfvd_display" class="${_} bg-slate-100" readonly disabled value="${r(String(b?.numfvd??0))}" />
          </label>
        </div>`:``,re=b?`
      <form id="fvd-atleta-form" class="${u.modal===`create`?`max-w-3xl`:`max-w-2xl`} space-y-4 rounded-xl border-2 border-slate-600 bg-slate-100 p-5 text-slate-950 shadow-md">
        ${u.modal===`create`?``:`<input type="hidden" name="id" value="${r(String(b.id??``))}" />`}

        ${ne}

        ${A}

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${v}">Nombres</span>
            <input name="nombres" id="fvd-atleta-nombres" class="${_}" value="${r(String(b.nombres??``))}" ${d} />
          </label>
          <label class="min-w-0">
            <span class="${v}">Apellidos</span>
            <input name="apellidos" id="fvd-atleta-apellidos" class="${_}" value="${r(String(b.apellidos??``))}" ${d} />
          </label>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${v}">Sexo</span>
            <select name="sexo" class="${_}" ${d}>
              <option value="0" ${Number(b.sexo)===0?`selected`:``}>—</option>
              <option value="1" ${Number(b.sexo)===1?`selected`:``}>Masculino</option>
              <option value="2" ${Number(b.sexo)===2?`selected`:``}>Femenino</option>
            </select>
          </label>
          <label class="min-w-0">
            <span class="${v}">Fecha nac.</span>
            <input type="date" name="fechnac" id="fvd-atleta-fechnac" class="${_}" value="${r(String((b.fechnac||``).toString().slice(0,10)))}" ${d} />
          </label>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${v}">Profesión</span>
            <input name="profesion" class="${_}" value="${r(String(b.profesion??``))}" ${d} />
          </label>
          ${M}
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${v}">Dirección</span>
            <input name="direccion" class="${_} w-full max-w-full" value="${r(String(b.direccion??``))}" ${d} />
          </label>
          <div class="hidden sm:block" aria-hidden="true"></div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${v}">Celular</span>
            <input name="celular" class="${_}" value="${r(String(b.celular??``))}" ${d} />
          </label>
          <label class="min-w-0">
            <span class="${v}">Email</span>
            <input type="email" name="email" class="${_}" value="${r(String(b.email??``))}" ${d} />
          </label>
        </div>

        ${t.isFvdAdmin?`<label class="block ${u.modal===`create`?`max-w-3xl`:`max-w-2xl`}">
            <span class="${v}">Asociación</span>
            <select name="asociacion" class="${_} w-full max-w-full sm:max-w-[min(28rem,100%)]" ${d}>
              <option value="">—</option>
              ${(t.asociaciones||[]).map(e=>`<option value="${e.id}" ${Number(b.asociacion)===e.id?`selected`:``}>${r(e.nombre||``)}</option>`).join(``)}
            </select>
          </label>`:`<input type="hidden" name="asociacion" value="${r(String(b.asociacion??``))}" />`}
      </form>
    `:`<p class="py-6 text-center text-sm font-bold text-[#2e3092]">Cargando…</p>`,ie=u.tipo===`bajas`?`Bajas`:u.tipo===`no_activos`?`Pendientes`:u.tipo===`ultimos`?`Últimos mov.`:`Activos`,P=`Todas`;if(t.isFvdAdmin&&u.asociacionFilter>0&&Array.isArray(t.asociaciones)){let e=t.asociaciones.find(e=>Number(e.id)===u.asociacionFilter);P=e&&e.nombre?String(e.nombre):`Asoc. #`+u.asociacionFilter}else !t.isFvdAdmin&&Array.isArray(t.asociaciones)&&t.asociaciones[0]&&(P=String(t.asociaciones[0].nombre||`Mi asociación`));let ae=u.rows.map(e=>c(e,t)).join(``),F=u.modal!==null,oe=u.modal===`create`?`Nuevo afiliado`:u.modal===`edit`?`Editar atleta`:u.modal===`view`?`Ficha atleta`:``,se=t.canCreate&&!F?`<button type="button" id="fvd-atleta-nuevo" class="inline-flex h-8 shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-[#fff200] px-2.5 text-[10px] font-extrabold uppercase tracking-wide text-[#0b1535] shadow-md ring-1 ring-black/10 transition hover:brightness-105 sm:h-9 sm:px-3 sm:text-xs">Nuevo atleta</button>`:``,I=(e.returnUrl||``).trim(),L=`inline-flex h-8 shrink-0 items-center justify-center gap-0.5 whitespace-nowrap rounded-md border border-white/30 bg-white/10 px-2 text-[10px] font-extrabold uppercase tracking-wide text-white shadow hover:bg-white/20 sm:h-9 sm:gap-1 sm:px-2.5 sm:text-xs`,R=`${n||!I?``:`<a href="${r(I)}" class="${L}">← Regresar</a>`}${n||I?``:`<button type="button" id="fvd-atleta-regresar" class="${L}">← Regresar</button>`}${se}`,z=R.trim()===``?``:`<div class="flex min-w-0 flex-nowrap items-center justify-end gap-1.5 overflow-x-auto border-b border-white/15 py-2 sm:gap-2">${R}</div>`,B=u.error?`<div class="border-t border-white/10 py-2"><div class="rounded-md border border-red-400/40 bg-red-950/40 px-3 py-2 text-xs font-medium text-red-100 shadow-inner" role="alert">${r(u.error)}</div></div>`:``,V=e.reportsEnabled===!0&&(e.reportsBase||``).trim()!==``,H=V?s(`reporte_indicadores.php?marcador=afiliacion`,t.isFvdAdmin,u.asociacionFilter):`#`,U=V?s(`reporte_carnets.php`,t.isFvdAdmin,u.asociacionFilter):`#`,W=V?s(`reporte_indicadores.php?marcador=afiliacion_anualidad`,t.isFvdAdmin,u.asociacionFilter):`#`,G=V?s(`reporte_traspasos.php`,t.isFvdAdmin,u.asociacionFilter):`#`,ce=V?`<div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 border-t border-white/10 pt-2" role="navigation" aria-label="Informes HTML">
          <span class="w-full text-[9px] font-semibold uppercase tracking-[0.18em] text-slate-500 sm:mr-1 sm:w-auto">Informes</span>
          <a class="rounded border border-white/20 bg-white/5 px-2 py-1 text-[11px] font-semibold text-[#fff200] shadow-sm hover:bg-white/10" href="${r(H)}" title="Filas con atletas.afiliacion = 1">Afiliación</a>
          <a class="rounded border border-white/20 bg-white/5 px-2 py-1 text-[11px] font-semibold text-[#fff200] shadow-sm hover:bg-white/10" href="${r(U)}" title="Solo filas con atletas.carnet = 1">Carnets</a>
          <a class="rounded border border-white/20 bg-white/5 px-2 py-1 text-[11px] font-semibold text-[#fff200] shadow-sm hover:bg-white/10" href="${r(W)}" title="Afiliación y anualidad">Afil.+anual.</a>
          <a class="rounded border border-white/20 bg-white/5 px-2 py-1 text-[11px] font-semibold text-[#fff200] shadow-sm hover:bg-white/10" href="${r(G)}" title="Historial de traspasos">Traspasos</a>
        </div>`:``,K=l.querySelector(`#fvd-atleta-q`);K instanceof HTMLInputElement&&document.activeElement===K&&(m=!0,h=K.selectionStart??K.value.length,g=K.selectionEnd??K.value.length);let le=`
      <div class="fvd-gestion-atletas-shell flex h-[100dvh] max-h-[100dvh] flex-col overflow-hidden bg-slate-200/90">
        <div class="fvd-gestion-atletas-sticky sticky top-0 z-40 shrink-0 border-b border-black/35 bg-gradient-to-br from-[#060d22] via-[#0c1a3d] to-[#122a4a] text-slate-100 shadow-[0_8px_32px_rgba(0,0,0,0.45)]">
          <div class="mx-auto w-full max-w-[min(100%,76.8rem)] px-3 sm:px-4">
            ${z}

            <div class="border-b border-white/10 py-2 sm:py-2.5">
              <div class="flex w-full min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                <div class="flex w-full min-w-0 flex-col gap-1.5 sm:w-1/2 sm:max-w-[50%] sm:shrink-0">
                  <label class="block text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-500" for="fvd-atleta-q">Búsqueda inteligente</label>
                  <input type="search" id="fvd-atleta-q" placeholder="Cédula · email · nombre…" value="${r(u.q)}" autocomplete="off" class="min-h-[2.35rem] w-full min-w-0 rounded-md border-0 bg-white/10 px-3 py-2 text-sm text-white shadow-inner outline-none ring-1 ring-inset ring-white/15 placeholder:text-slate-500 focus:bg-white/14 focus:ring-2 focus:ring-[#fff200]/45" />
                  <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                    ${t.isFvdAdmin?`<select id="fvd-atleta-asoc" class="fvd-atleta-select-header min-h-[2.1rem] min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-900 shadow-md outline-none focus-visible:border-[#2e3092] focus-visible:ring-2 focus-visible:ring-[#fff200]/60 sm:min-w-[9.5rem] sm:flex-none sm:text-xs">${j}</select>`:``}
                    <select id="fvd-atleta-tipo" class="fvd-atleta-select-header min-h-[2.1rem] min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-900 shadow-md outline-none focus-visible:border-[#2e3092] focus-visible:ring-2 focus-visible:ring-[#fff200]/60 sm:min-w-[7.5rem] sm:flex-none sm:text-xs">
                      <option value="normal" ${u.tipo===`normal`?`selected`:``}>Activos</option>
                      <option value="bajas" ${u.tipo===`bajas`?`selected`:``}>Bajas</option>
                      <option value="no_activos" ${u.tipo===`no_activos`?`selected`:``}>Pendientes</option>
                      <option value="ultimos" ${u.tipo===`ultimos`?`selected`:``}>Últimos</option>
                    </select>
                  </div>
                </div>
                <div class="flex w-full min-w-0 flex-1 flex-row flex-wrap items-center gap-x-2 gap-y-1 sm:w-1/2 sm:max-w-[50%] sm:justify-end">
                  <span class="inline-flex items-center gap-1.5 rounded-md border border-white/15 bg-black/30 px-2 py-0.5 shadow-inner" title="Total con filtro actual">
                    <span class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Total</span>
                    <span class="text-xs font-bold tabular-nums leading-none text-white">${u.total}</span>
                  </span>
                  <span class="inline-flex max-w-[min(100%,11rem)] items-center gap-1.5 truncate rounded-md border border-white/15 bg-black/30 px-2 py-0.5 shadow-inner sm:max-w-[13rem]" title="${r(P)}">
                    <span class="shrink-0 text-[9px] font-bold uppercase tracking-wide text-slate-400">Asoc.</span>
                    <span class="truncate text-xs font-semibold leading-none text-slate-100">${r(P)}</span>
                  </span>
                  <span class="inline-flex items-center gap-1.5 rounded-md border border-white/15 bg-black/30 px-2 py-0.5 shadow-inner">
                    <span class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Vista</span>
                    <span class="text-xs font-semibold leading-none text-slate-100">${r(ie)}</span>
                  </span>
                  <span id="fvd-atleta-loading-dot" class="${u.loading?`opacity-100 animate-pulse`:`opacity-0`} inline-block h-2 w-2 shrink-0 rounded-full bg-amber-300 shadow transition-opacity duration-150" aria-hidden="true"></span>
                </div>
              </div>
            </div>

            ${ce}

            ${B}
          </div>
        </div>

        <div id="fvd-atleta-scroll" class="mx-auto flex min-h-0 w-full max-w-[min(100%,76.8rem)] flex-1 flex-col px-3 opacity-100 transition-opacity duration-150 sm:px-4" style="min-height:0">
          <div class="fvd-atleta-tbody-scroll min-h-[min(12rem,calc(100dvh-17.5rem))] flex-1 overflow-auto rounded-b-lg border border-t-0 border-slate-300/90 bg-white shadow-[inset_0_1px_0_rgba(255,255,255,0.65)]" style="max-height:calc(100dvh - 18.25rem)">
            <table class="min-w-[28.8rem] w-full table-fixed border-collapse text-left text-sm">
              <thead>
                <tr class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">
                  <th class="sticky top-0 z-[17] w-[3.58rem] max-w-[3.58rem] bg-white px-1 py-2.5 shadow-[0_1px_0_rgba(15,23,42,0.12)]">Foto</th>
                  <th class="sticky top-0 z-[17] w-[13rem] bg-white px-1.5 py-2.5 shadow-[0_1px_0_rgba(15,23,42,0.12)]">Nº FVD · CI</th>
                  <th class="sticky top-0 z-[17] min-w-0 bg-white px-1.5 py-2.5 shadow-[0_1px_0_rgba(15,23,42,0.12)]">Nombre</th>
                  <th class="sticky top-0 z-[17] hidden w-[7.35rem] max-w-[7.35rem] bg-white px-1 py-2.5 shadow-[0_1px_0_rgba(15,23,42,0.12)] lg:table-cell">Asoc.</th>
                  <th class="sticky top-0 z-[17] w-[3.5rem] max-w-[3.5rem] bg-white px-0.5 py-2.5 text-center shadow-[0_1px_0_rgba(15,23,42,0.12)]">Est.</th>
                  <th class="sticky top-0 z-[17] w-[7.5rem] max-w-[7.5rem] bg-white px-1 py-2.5 text-center shadow-[0_1px_0_rgba(15,23,42,0.12)]">Acc.</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white text-slate-800">
                ${u.loading?`<tr><td colspan="6" class="px-3 py-12 text-center text-sm text-slate-500">Actualizando listado…</td></tr>`:u.rows.length===0?`<tr><td colspan="6" class="px-3 py-12 text-center text-sm text-slate-500">Sin resultados.</td></tr>`:ae}
              </tbody>
            </table>
          </div>
          <footer class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200/80 bg-slate-100/95 px-2 py-2 text-[11px] text-slate-600 shadow-[0_-4px_12px_rgba(15,23,42,0.06)] sm:px-3 sm:text-xs">
            <span class="tabular-nums">Pág. ${u.page} / ${Math.max(1,u.pages)} · ${u.total} reg.</span>
            <div class="flex gap-2">
              <button type="button" id="fvd-atleta-prev" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 sm:px-3 sm:text-sm" ${u.page<=1?`disabled`:``}>Anterior</button>
              <button type="button" id="fvd-atleta-next" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 sm:px-3 sm:text-sm" ${u.page>=u.pages?`disabled`:``}>Siguiente</button>
            </div>
          </footer>
        </div>
      </div>

      ${F?`<div class="absolute inset-0 z-[99999] flex items-center justify-center p-3 sm:p-5" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" data-close-modal></div>
        <div class="relative z-10 flex max-h-[min(92dvh,90vh)] w-full max-w-[min(96vw,80vw)] flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl">
          <header class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-[#1a1f5c] via-[#2e3092] to-[#3d42c4] px-4 py-3 sm:px-5">
            <div class="min-w-0">
              <h2 class="text-lg font-bold tracking-tight text-white">${r(oe)}</h2>
              <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-widest text-[#fff200]/95">Federación Venezolana de Dominó</p>
            </div>
            <button type="button" class="rounded-lg px-2 py-1 text-xl leading-none text-white/90 hover:bg-white/10" data-close-modal aria-label="Cerrar">✕</button>
          </header>
          <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden bg-gradient-to-b from-slate-50/80 to-white px-4 py-4 sm:px-6 sm:py-5">
            ${re}
          </div>
          <footer class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-slate-100 bg-white px-4 py-3 sm:px-6">
            ${u.modal===`view`&&b&&u.modalCanEdit?`<button type="button" id="fvd-atleta-to-edit" class="rounded-lg bg-[#2e3092] px-4 py-2 text-sm font-bold text-white shadow hover:bg-[#252f7a]">Editar</button>`:``}
            ${u.modal===`edit`||u.modal===`create`?`<button type="button" id="fvd-atleta-save" class="rounded-lg bg-[#2e3092] px-4 py-2 text-sm font-bold text-white shadow hover:bg-[#252f7a] disabled:opacity-50" ${u.saving||u.modal===`create`&&u.cedulaDuplicada?`disabled`:``}>${u.saving?`Guardando…`:`Guardar`}</button>`:``}
            <button type="button" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-close-modal>Cerrar</button>
          </footer>
        </div>
      </div>`:``}
    `;l.innerHTML=``,l.innerHTML=le;function q(){let e=l.querySelector(`#fvd-atleta-q`),t=l.querySelector(`#fvd-atleta-tipo`),n=l.querySelector(`#fvd-atleta-asoc`);u.q=e?e.value.trim():``,u.tipo=t?t.value:`normal`,n&&(u.asociacionFilter=parseInt(n.value||`0`,10)||0)}let J=l.querySelector(`#fvd-atleta-q`),Y=()=>{p!=null&&clearTimeout(p),p=window.setTimeout(()=>{p=null,q(),u.page=1,y()},400)};J?.addEventListener(`input`,Y),J?.addEventListener(`change`,Y),J?.addEventListener(`search`,()=>{q(),u.page=1,y()}),l.querySelector(`#fvd-atleta-tipo`)?.addEventListener(`change`,()=>{q(),u.page=1,y()}),l.querySelector(`#fvd-atleta-asoc`)?.addEventListener(`change`,()=>{q(),u.page=1,y()}),l.querySelector(`#fvd-atleta-prev`)?.addEventListener(`click`,()=>{u.page>1&&(--u.page,y())}),l.querySelector(`#fvd-atleta-next`)?.addEventListener(`click`,()=>{u.page<u.pages&&(u.page+=1,y())}),l.querySelectorAll(`[data-view]`).forEach(e=>{e.addEventListener(`click`,async()=>{let t=parseInt(e.getAttribute(`data-view`)||`0`,10);t&&await C(`view`,t)})}),l.querySelectorAll(`[data-edit]`).forEach(e=>{e.addEventListener(`click`,async()=>{let t=parseInt(e.getAttribute(`data-edit`)||`0`,10);t&&await C(`edit`,t)})}),l.querySelectorAll(`[data-toggle-atleta]`).forEach(e=>{e.addEventListener(`change`,e=>{let t=e.target,n=parseInt(t.getAttribute(`data-toggle-atleta`)||`0`,10);n&&te(n)})}),l.querySelectorAll(`[data-close-modal]`).forEach(e=>{e.addEventListener(`click`,()=>S())}),l.querySelector(`#fvd-atleta-to-edit`)?.addEventListener(`click`,async()=>{let e=u.formRow&&u.formRow.id?parseInt(String(u.formRow.id),10):0;e&&await C(`edit`,e)}),l.querySelector(`#fvd-atleta-save`)?.addEventListener(`click`,()=>{let e=l.querySelector(`#fvd-atleta-form`);e&&ee(e)});let X=l.querySelector(`#fvd-atleta-cedula`);X?.addEventListener(`input`,()=>{u.modal!==`create`||!X||(f!=null&&clearTimeout(f),f=window.setTimeout(()=>{f=null,x(X.value)},450))}),X?.addEventListener(`blur`,()=>{u.modal!==`create`||!X||(f!=null&&(clearTimeout(f),f=null),x(X.value))});let Z=l.querySelector(`#fvd-atleta-foto-inp`),Q=l.querySelector(`#fvd-atleta-foto-prev`),$=l.querySelector(`#fvd-atleta-foto-ph`);Z?.addEventListener(`change`,()=>{let e=Z.files&&Z.files[0];if(!e||!Q)return;let t=new FileReader;t.onload=()=>{Q.src=typeof t.result==`string`?t.result:``,Q.classList.remove(`hidden`),$?.classList.add(`hidden`)},t.readAsDataURL(e)}),m&&(m=!1,requestAnimationFrame(()=>{let e=l.querySelector(`#fvd-atleta-q`);if(!(e instanceof HTMLInputElement))return;e.focus();let t=e.value.length,n=Math.min(Math.max(0,h),t),r=Math.min(Math.max(0,g),t);try{e.setSelectionRange(n,r)}catch{}}))}T(),y()}l();