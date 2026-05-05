/* empty css            *//* empty css                  */var e=typeof window<`u`&&window.FVD_GESTION_ATLETAS?window.FVD_GESTION_ATLETAS:{apiUrl:``},t=(e.referencialUrl||``).trim(),n=e.embeddedMaster===!0;function r(e){let t=document.createElement(`div`);return t.textContent=e,t.innerHTML}function i(e){let t=e.trim();if(!t)return`?`;let n=t.split(/\s+/).filter(Boolean);return n.length>=2?(n[0][0]+n[n.length-1][0]).toUpperCase():t.slice(0,2).toUpperCase()}async function a(e,t){let n=await fetch(e,{credentials:`include`,...t}),r;try{r=await n.json()}catch{r={ok:!1,error:`Respuesta no JSON.`}}return{res:n,body:r}}function o(e,t){let n=Number(e);return Number(t)===0?`Pendiente`:n===9?`Inactivo`:n===0||n===1?`Activo`:n===2?`Baja`:n===990?`Pend. adm.`:`—`}function s(e){let t=String(e||``).trim();if(!t)return`— (indique fecha de nac.)`;let n=t.split(`-`);if(n.length!==3)return`—`;let r=parseInt(n[0],10),i=parseInt(n[1],10)-1,a=parseInt(n[2],10);if(Number.isNaN(r)||Number.isNaN(i)||Number.isNaN(a))return`—`;let o=new Date(r,i,a),s=new Date;if(s.setHours(0,0,0,0),o.setHours(0,0,0,0),o>s)return`—`;let c=s.getFullYear()-o.getFullYear(),l=s.getMonth()-o.getMonth();return(l<0||l===0&&s.getDate()<o.getDate())&&--c,c>=18?`LIBRE`:c>=15?`SUB 18`:c>=12?`SUB 15`:`SUB 12`}function c(t,n,r){let i=(e.reportsBase||``).trim();if(!i)return`#`;let a=i.endsWith(`/`)?i:`${i}/`,o;try{o=new URL(t.replace(/^\//,``),a)}catch{return`#`}let s=new URL(window.location.href);return[`embedded`,`fvd_master_embed`,`torneo_id`,`ctx_torneo`,`campeonato_id`,`ret`,`return`,`fvd_ws`].forEach(e=>{let t=s.searchParams.get(e);t!==null&&t!==``&&o.searchParams.set(e,t)}),n&&r>0&&o.searchParams.set(`asociacion_id`,String(r)),o.searchParams.has(`ret`)||o.searchParams.set(`ret`,s.pathname+(s.search||``)),o.pathname+o.search}function l(e,t){if(!e)return``;let n=e?.foto_url?String(e.foto_url):``,a=n?`<img src="${r(n)}" alt="" class="mx-auto h-10 w-10 rounded-lg object-cover ring-1 ring-slate-200/80 shadow-sm" />`:`<span class="mx-auto flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-[10px] font-bold text-slate-500">${r(i(String(e?.nombre??``)))}</span>`,s=Number(e?.numfvd??0),c=s>0?String(s):`—`,l=e?.cedula!=null&&String(e?.cedula).trim()!==``?String(e?.cedula):`—`,u=r(l),d=`<div class="flex max-w-[13rem] items-center gap-1.5 whitespace-nowrap text-left text-[13px] font-semibold leading-tight text-slate-800" title="${e?.identidad_label!=null&&String(e?.identidad_label).trim()!==``?r(String(e?.identidad_label).replace(/\n/g,` · `)):`C.I. ${u}`}">
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
        </tr>`}function u(){let u=document.getElementById(`fvd-gestion-atletas-app`);if(!u||!e.apiUrl)return;u.addEventListener(`click`,e=>{let t=e.target;if(t instanceof Element){if(!n&&t.closest(`#fvd-atleta-regresar`)){e.preventDefault(),window.history.length>1&&window.history.back();return}t.closest(`#fvd-atleta-nuevo`)&&(e.preventDefault(),N(`create`,null))}});let d={rows:[],meta:{isFvdAdmin:!1,canCreate:!0,canToggle:!1,asociaciones:[]},total:0,page:1,perPage:10,pages:1,q:``,asociacionFilter:0,tipo:`normal`,loading:!1,error:``,personaHint:``,modal:null,formRow:null,saving:!1,modalCanEdit:!1,cedulaDuplicada:!1,referencialLoading:!1,duplicadoExistente:null},f=e.apiUrl,p=`fvd_atleta_ui`,m=`fvd_atleta_create_draft_v1`,h=[`cedula`,`nombre`,`sexo`,`numfvd`,`fechnac`,`profesion`,`direccion`,`celular`,`email`,`asociacion`,`foto_url`,`nombres`,`apellidos`],g=!1,_=``,v=null,y=null,b=!1,x=0,S=0;function C(e){d.error=e||``,P()}function w(){return{cedula:``,nombres:``,apellidos:``,nombre:``,sexo:0,numfvd:0,fechnac:``,profesion:``,direccion:``,celular:``,email:``,asociacion:d.meta.isFvdAdmin?d.asociacionFilter>0?String(d.asociacionFilter):``:d.meta.asociaciones&&d.meta.asociaciones[0]?String(d.meta.asociaciones[0].id):``,foto_url:``,foto:``}}function T(e){let t={};for(let n of h)e[n]!=null&&e[n]!==``&&(t[n]=e[n]);return t}function E(){try{let e=sessionStorage.getItem(m);if(!e)return{};let t=JSON.parse(e);return t&&typeof t==`object`?T(t):{}}catch{return{}}}function D(){try{sessionStorage.removeItem(m)}catch{}}function ee(){if(!(d.modal!==`create`||!d.formRow))try{sessionStorage.setItem(m,JSON.stringify(T(d.formRow)))}catch{}}function te(){try{let e=new URL(window.location.href);d.modal===`create`?e.searchParams.set(p,`create`):e.searchParams.delete(p);let t=`${e.pathname}${e.search}${e.hash}`;t!==`${window.location.pathname}${window.location.search}${window.location.hash}`&&window.history.replaceState(window.history.state,``,t)}catch{}}function O(){let e=u.querySelector(`#fvd-atleta-loading-dot`);e instanceof HTMLElement&&(e.classList.toggle(`opacity-0`,!d.loading),e.classList.toggle(`opacity-100`,d.loading),e.classList.toggle(`animate-pulse`,d.loading));let t=u.querySelector(`#fvd-atleta-scroll`);t instanceof HTMLElement&&(t.classList.toggle(`opacity-[0.72]`,d.loading),t.classList.toggle(`opacity-100`,!d.loading))}async function k(){d.loading=!0,d.error=``,O();let e=new URL(f,window.location.href);e.searchParams.set(`action`,`list`),e.searchParams.set(`page`,String(d.page)),e.searchParams.set(`per_page`,String(d.perPage)),e.searchParams.set(`q`,d.q),e.searchParams.set(`tipo`,d.tipo),d.meta.isFvdAdmin&&d.asociacionFilter>0&&e.searchParams.set(`id_asociacion`,String(d.asociacionFilter));let{res:t,body:n}=await a(e.toString());if(d.loading=!1,!t.ok||!n.ok){C(n.error||`No se pudo cargar el listado.`);return}let r=n.data;if(d.rows=Array.isArray(r.rows)?r.rows.filter(e=>typeof e==`object`&&!!e):[],d.total=r.total??0,d.page=r.page??1,d.perPage=r.per_page??10,d.pages=r.pages??1,r.meta&&(d.meta={...d.meta,...r.meta}),!g){g=!0;try{if(new URL(window.location.href).searchParams.get(p)===`create`)if(d.meta.canCreate){let e=E();d.modal=`create`,d.modalCanEdit=!0,d.personaHint=``,d.cedulaDuplicada=!1,d.duplicadoExistente=null,d.referencialLoading=!1,d.formRow={...w(),...e};let t=Number(d.formRow.sexo);d.formRow.sexo=Number.isNaN(t)?0:t;let n=Number(d.formRow.numfvd);d.formRow.numfvd=Number.isNaN(n)?0:n,_=String(d.formRow.cedula||``).trim()}else{let e=new URL(window.location.href);e.searchParams.delete(p),window.history.replaceState(window.history.state,``,`${e.pathname}${e.search}${e.hash}`),D()}}catch{}}if(P(),_){let e=_;_=``,j(e)}}async function A(e){d.error=``;let t=new URL(f,window.location.href);t.searchParams.set(`action`,`get`),t.searchParams.set(`id`,String(e));let{res:n,body:r}=await a(t.toString());if(!n.ok||!r.ok){C(r.error||`No se pudo cargar el atleta.`);return}let i=r.data.raw&&typeof r.data.raw==`object`?r.data.raw:{},o=r.data.row&&typeof r.data.row==`object`?r.data.row:{};d.formRow={...i,...o},d.modalCanEdit=r.data.meta&&r.data.meta.canEdit===!0,r.data.meta&&(d.meta={...d.meta,...r.data.meta}),P()}async function j(e){let n=String(e).trim();if(d.modal!==`create`||!d.formRow)return;if(!n){d.cedulaDuplicada=!1,d.referencialLoading=!1,d.personaHint=``,d.duplicadoExistente=null,P();return}d.referencialLoading=!0,P();let r=new URL(f,window.location.href);r.searchParams.set(`action`,`cedula_disponible`),r.searchParams.set(`cedula`,n);let i=a(r.toString()),o=t?a((()=>{let e=new URL(t,window.location.href);return e.searchParams.set(`cedula`,n),e.toString()})()):Promise.resolve({res:{ok:!1},body:{ok:!1}}),[s,c]=await Promise.all([i,o]);if(!(d.modal!==`create`||d.formRow==null)){if(d.referencialLoading=!1,s.res.ok&&s.body.ok&&s.body.data){let e=s.body.data;d.cedulaDuplicada=e.existe===!0,d.duplicadoExistente=e.existente&&typeof e.existente==`object`?e.existente:null}else d.cedulaDuplicada=!1,d.duplicadoExistente=null;if(d.cedulaDuplicada&&d.duplicadoExistente)d.personaHint=``;else if(t&&c.res.ok&&c.body.ok)if(c.body.found&&c.body.data&&typeof c.body.data==`object`){let e=c.body.data,t=String(e.nombre1??``),n=String(e.nombre2??``),r=String(e.apellido1??``),i=String(e.apellido2??``);d.formRow.nombres=[t,n].filter(Boolean).join(` `).trim(),d.formRow.apellidos=[r,i].filter(Boolean).join(` `).trim(),d.formRow.fechnac=String(e.fechnac||``).slice(0,10);let a=String(e.sexo||``).trim().toUpperCase();d.formRow.sexo=a===`F`?2:+(a===`M`);let o=[d.formRow.nombres,d.formRow.apellidos].filter(Boolean).join(` `).trim();d.formRow.nombre=o,d.personaHint=`Datos cargados desde referencial nacional.`}else d.personaHint=`Sin coincidencia en referencial nacional.`;else t?d.personaHint=`No se pudo consultar el referencial.`:d.personaHint=``;P()}}function M(){D(),d.modal=null,d.formRow=null,d.modalCanEdit=!1,d.personaHint=``,d.cedulaDuplicada=!1,d.referencialLoading=!1,d.duplicadoExistente=null,v!=null&&(clearTimeout(v),v=null),P()}async function N(e,t){if(d.modal=e,d.formRow=null,d.modalCanEdit=e===`create`,d.personaHint=``,d.cedulaDuplicada=!1,d.referencialLoading=!1,d.duplicadoExistente=null,e===`create`){d.formRow=w(),P();return}P(),t!=null&&await A(t)}async function ne(e){if(d.modal===`create`&&d.cedulaDuplicada){C(`Esta cédula ya está registrada como atleta.`);return}d.saving=!0,P();let t=new FormData(e);t.set(`action`,`save`);let{res:n,body:r}=await a(f,{method:`POST`,body:t});if(d.saving=!1,!n.ok||!r.ok){C(r.error||`No se pudo guardar.`);return}M(),await k()}async function re(e){let t=new FormData;t.set(`action`,`toggle_activo`),t.set(`id`,String(e));let{res:n,body:r}=await a(f,{method:`POST`,body:t});if(!n.ok||!r.ok){C(r.error||`No se pudo cambiar el estatus.`),await k();return}let i=r.data&&r.data.row;i&&i.id!=null&&Number(i.id)>0?d.rows=d.rows.map(e=>e?.id===i.id?i:e):await k(),P()}function ie(){let e=u.querySelector(`#fvd-atleta-form`);if(!e||!d.formRow||d.modal===null||d.modal===`view`)return;let t=t=>{let n=e.elements.namedItem(t);return n?(n instanceof RadioNodeList,n.value):``};d.formRow.cedula=t(`cedula`),d.modal===`create`?d.formRow.nombre=t(`nombre`):(d.formRow.nombres=t(`nombres`),d.formRow.apellidos=t(`apellidos`)),d.formRow.fechnac=t(`fechnac`),d.formRow.celular=t(`celular`),d.formRow.email=t(`email`),d.formRow.direccion=t(`direccion`),d.formRow.profesion=t(`profesion`);let n=parseInt(t(`sexo`)||`0`,10);Number.isNaN(n)||(d.formRow.sexo=n);let r=t(`asociacion`);r!==``&&(d.formRow.asociacion=r);let i=t(`estatus`);if(i!==``){let e=parseInt(i,10);Number.isNaN(e)||(d.formRow.estatus=e)}}function P(){ie();let t=d.meta,a=d.modal===`view`||d.modal===null,f=a?`readonly disabled`:``,p=`box-border w-full min-w-0 max-w-full min-h-[1.58rem] rounded-md border-2 border-white/30 bg-white px-1.5 py-[0.31rem] text-[0.75rem] font-semibold leading-tight tracking-tight text-[#0b1535] shadow-[0_2px_8px_rgba(0,0,0,0.18)] placeholder:text-slate-500 focus:border-[#fff200] focus:outline-none focus:ring-1 focus:ring-[#fff200]/45 disabled:border-white/20 disabled:bg-slate-200/90 disabled:text-slate-700`,m=`mb-0.5 block text-xs font-semibold uppercase tracking-wide text-[#e8ecff]`,h=`mt-1 flex w-full max-w-[5.75rem] cursor-pointer items-center justify-center self-center rounded border border-[#fff200]/70 bg-[#fff200] px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-[#0b1535] shadow hover:brightness-105`,g=`w-full max-w-[8.4rem] min-h-[2.97rem] rounded-lg border-2 border-slate-700 bg-white px-3 py-[0.675rem] text-sm font-semibold leading-snug text-slate-950 shadow-sm placeholder:text-slate-600 focus:border-[#2e3092] focus:outline-none focus:ring-2 focus:ring-[#2e3092]/35 disabled:border-slate-500 disabled:bg-slate-200 disabled:text-slate-800`,_=`mb-1 block text-[11px] font-black uppercase tracking-[0.06em] text-slate-950`,C=d.formRow,w=C?String(C.foto_url||``).trim():``,T=w!==``,E=i(C?String(C.nombre||``):``),D=d.referencialLoading?`Consultando referencial…`:d.cedulaDuplicada?``:d.personaHint,O=d.duplicadoExistente,A=d.modal===`create`&&d.cedulaDuplicada&&O?`<div class="rounded-2xl border-2 border-amber-400/55 bg-gradient-to-br from-amber-500/25 via-amber-900/40 to-[#0b1535]/80 p-4 text-[#fff8e7] shadow-[0_8px_32px_rgba(0,0,0,0.35)] backdrop-blur-sm">
            <p class="mb-3 font-[system-ui,sans-serif] text-[11px] font-black uppercase tracking-[0.18em] text-[#fff200]">Registro existente en sistema</p>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-2.5 text-[13px] sm:grid-cols-2">
              <div class="rounded-lg border border-white/10 bg-black/20 px-2.5 py-2"><dt class="text-[10px] font-bold uppercase tracking-wide text-amber-200/90">Nombre</dt><dd class="mt-0.5 font-semibold text-white">${r(String(O?.nombre||`—`))}</dd></div>
              <div class="rounded-lg border border-white/10 bg-black/20 px-2.5 py-2"><dt class="text-[10px] font-bold uppercase tracking-wide text-amber-200/90">Nº FVD</dt><dd class="mt-0.5 font-semibold tabular-nums text-white">${r(String(O?.numfvd??`—`))}</dd></div>
              <div class="rounded-lg border border-white/10 bg-black/20 px-2.5 py-2"><dt class="text-[10px] font-bold uppercase tracking-wide text-amber-200/90">Asociación</dt><dd class="mt-0.5 font-semibold text-white">${r(String(O?.asociacion||`—`))}</dd></div>
              <div class="rounded-lg border border-white/10 bg-black/20 px-2.5 py-2"><dt class="text-[10px] font-bold uppercase tracking-wide text-amber-200/90">Estatus</dt><dd class="mt-0.5 font-semibold text-white">${r(o(Number(O?.estatus),Number(O?.numfvd??0)))}</dd></div>
              <div class="rounded-lg border border-white/10 bg-black/20 px-2.5 py-2"><dt class="text-[10px] font-bold uppercase tracking-wide text-amber-200/90">Email</dt><dd class="mt-0.5 font-semibold text-white">${r(String(O?.email||`—`))}</dd></div>
              <div class="rounded-lg border border-white/10 bg-black/20 px-2.5 py-2"><dt class="text-[10px] font-bold uppercase tracking-wide text-amber-200/90">Celular</dt><dd class="mt-0.5 font-semibold text-white">${r(String(O?.celular||`—`))}</dd></div>
              <div class="rounded-lg border border-white/10 bg-black/20 px-2.5 py-2"><dt class="text-[10px] font-bold uppercase tracking-wide text-amber-200/90">F. nac.</dt><dd class="mt-0.5 font-semibold text-white">${r(String(O?.fechnac||`—`))}</dd></div>
              <div class="rounded-lg border border-white/10 bg-black/20 px-2.5 py-2"><dt class="text-[10px] font-bold uppercase tracking-wide text-amber-200/90">Profesión</dt><dd class="mt-0.5 font-semibold text-white">${r(String(O?.profesion||`—`))}</dd></div>
              <div class="rounded-lg border border-white/10 bg-black/20 px-2.5 py-2 sm:col-span-2"><dt class="text-[10px] font-bold uppercase tracking-wide text-amber-200/90">Dirección</dt><dd class="mt-0.5 font-semibold text-white">${r(String(O?.direccion||`—`))}</dd></div>
            </dl>
          </div>`:``,P=t.isFvdAdmin&&Array.isArray(t.asociaciones)?`<option value="0" ${d.asociacionFilter===0?`selected`:``}>Todas las asociaciones</option>${t.asociaciones.map(e=>`<option value="${e.id}" ${d.asociacionFilter===Number(e.id)?`selected`:``}>${r(e.nombre||``)}</option>`).join(``)}`:``,ae=C?d.modal===`view`?`<div class="min-w-0">
            <span class="${_}">Estatus</span>
            <div class="flex min-h-[2.97rem] max-w-[8.4rem] items-center rounded-lg border-2 border-slate-700 bg-white px-3 py-[0.675rem] text-sm font-semibold text-slate-950">${r(o(Number(C.estatus),Number(C.numfvd??0)))}</div>
          </div>`:d.modal===`edit`&&t.isFvdAdmin?`<label class="min-w-0">
            <span class="${_}">Estatus</span>
            <select name="estatus" class="${g}" ${f}>
              <option value="0" ${Number(C.estatus)===0?`selected`:``}>Pendiente</option>
              <option value="1" ${Number(C.estatus)===1?`selected`:``}>Activo</option>
              <option value="2" ${Number(C.estatus)===2?`selected`:``}>Baja</option>
              <option value="990" ${Number(C.estatus)===990?`selected`:``}>Pend. aprob. FVD</option>
            </select>
          </label>`:d.modal===`create`?``:`<input type="hidden" name="estatus" value="${r(String(C.estatus??0))}" />`:``,oe=C?s(String((C.fechnac||``).toString().slice(0,10))):`— (indique fecha de nac.)`,se=C&&d.modal===`create`?`
      <form id="fvd-atleta-form" class="fvd-atleta-create-form max-h-[min(78dvh,85vh)] w-full max-w-full overflow-y-auto rounded-2xl border-2 border-[#fff200]/45 bg-gradient-to-br from-[#060d22] via-[#0f1f4a] to-[#1a2d6e] p-3 font-[system-ui,-apple-system,Segoe_UI,sans-serif] text-[#f1f5ff] shadow-[0_24px_64px_rgba(0,0,0,0.45)] sm:p-4">
        <div class="grid grid-cols-1 gap-x-3 gap-y-8 lg:grid-cols-[minmax(0,4fr)_minmax(0,1fr)] lg:items-start">
          <div class="min-w-0 space-y-8">
        ${t.isFvdAdmin?`<label class="block min-w-0">
            <span class="${m}">Asociación</span>
            <select name="asociacion" required class="${p}" ${f}>
              <option value="">— Seleccione asociación —</option>
              ${(t.asociaciones||[]).map(e=>`<option value="${e.id}" ${Number(C.asociacion)===Number(e.id)?`selected`:``}>${r(e.nombre||``)}</option>`).join(``)}
            </select>
          </label>`:`<input type="hidden" name="asociacion" value="${r(String(C.asociacion??``))}" />`}

            <div class="grid grid-cols-1 items-end gap-x-8 gap-y-8 sm:grid-cols-[minmax(0,2fr)_minmax(0,5.5fr)_minmax(0,2.5fr)]">
              <div class="min-w-0">
                <label class="block min-w-0">
                  <span class="${m}">Cédula</span>
                  <input name="cedula" id="fvd-atleta-cedula" required class="${p}" value="${r(String(C.cedula??``))}" ${f} />
                </label>
                ${D?`<p class="mt-0.5 text-[10px] font-medium leading-tight text-white/90" role="status">${r(D)}</p>`:``}
              </div>
              <label class="block min-w-0">
                <span class="${m}">Nombre completo</span>
                <input name="nombre" id="fvd-atleta-nombre" required class="${p}" value="${r(String(C.nombre??``))}" ${f} />
              </label>
              <label class="block min-w-0">
                <span class="${m}">Sexo</span>
                <select name="sexo" class="${p}" ${f}>
                  <option value="0" ${Number(C.sexo)===0?`selected`:``}>— No indicado —</option>
                  <option value="1" ${Number(C.sexo)===1?`selected`:``}>Masculino</option>
                  <option value="2" ${Number(C.sexo)===2?`selected`:``}>Femenino</option>
                </select>
              </label>
            </div>

            ${A}

            <div class="grid grid-cols-1 items-end gap-x-8 gap-y-8 sm:grid-cols-[minmax(0,2.5fr)_minmax(0,3fr)_minmax(0,4.5fr)]">
              <label class="block min-w-0">
                <span class="${m}">Fecha nac.</span>
                <input type="date" name="fechnac" id="fvd-atleta-fechnac" class="${p}" value="${r(String((C.fechnac||``).toString().slice(0,10)))}" ${f} />
              </label>
              <label class="block min-w-0">
                <span class="${m}">Profesión</span>
                <input name="profesion" class="${p}" value="${r(String(C.profesion??``))}" ${f} />
              </label>
              <label class="block min-w-0 sm:col-span-1">
                <span class="${m}">Dirección</span>
                <input name="direccion" class="${p}" value="${r(String(C.direccion??``))}" ${f} />
              </label>
            </div>

            <div class="grid grid-cols-2 items-end gap-x-8 gap-y-8 sm:grid-cols-4">
              <label class="min-w-0">
                <span class="${m}">Celular</span>
                <input name="celular" class="${p}" value="${r(String(C.celular??``))}" ${f} />
              </label>
              <label class="min-w-0">
                <span class="${m}">Email</span>
                <input type="email" name="email" class="${p}" value="${r(String(C.email??``))}" ${f} />
              </label>
              <div class="min-w-0">
                <span class="${m}">Nº FVD</span>
                <div class="flex min-h-[1.58rem] items-center rounded-md border-2 border-[#fff200]/40 bg-[#050b1f]/90 px-1.5 text-xs font-bold tabular-nums text-white">${Number(C.numfvd)>0?r(String(C.numfvd)):`—`}</div>
              </div>
              <div class="min-w-0">
                <span class="${m}">Categoría</span>
                <p id="fvd-atleta-categ-hint-txt" class="m-0 flex min-h-[1.58rem] items-center rounded-md border border-white/25 bg-[#050b1f]/90 px-1.5 text-xs font-bold leading-tight text-[#fff200]">${r(oe)}</p>
              </div>
            </div>
          </div>

          <aside class="flex min-w-0 flex-col items-center gap-2 lg:items-stretch" aria-label="Foto y cédula">
            <div class="flex w-full flex-col items-center gap-1">
              <div class="relative flex aspect-[3/4] max-h-[4.5rem] w-full max-w-[5.25rem] shrink-0 items-center justify-center self-center overflow-hidden rounded-md border border-white/25 bg-[#040a18]/80 p-1 shadow-inner">
                <img id="fvd-atleta-foto-prev" src="${T?r(w):``}" alt="" class="max-h-full max-w-full rounded object-contain ${T?``:`hidden`}" decoding="async" />
                <span id="fvd-atleta-foto-ph" class="px-1 text-center text-[10px] font-medium text-[#94a8d4] ${T?`hidden`:``}">—</span>
              </div>
              ${a?``:`<label for="fvd-atleta-foto-inp" class="${h}">Elegir archivo</label>
              <input type="file" id="fvd-atleta-foto-inp" name="foto" accept="image/*" class="sr-only" tabindex="-1" />`}
            </div>
            <div class="flex w-full flex-col items-center gap-1">
              <div class="relative flex min-h-[3rem] w-full max-w-[6rem] shrink-0 items-center justify-center self-center overflow-hidden rounded-md border border-white/20 bg-[#040a18]/70 p-1">
                <img id="fvd-atleta-cedula-prev" src="" alt="" class="hidden max-h-full max-w-full rounded object-contain" decoding="async" />
                <span id="fvd-atleta-cedula-ph" class="px-1 text-center text-[10px] font-medium text-[#94a8d4]">—</span>
              </div>
              ${a?``:`<label for="fvd-atleta-cedula-inp" class="${h}">Elegir archivo</label>
              <input type="file" id="fvd-atleta-cedula-inp" name="cedula_img" accept="image/*" class="sr-only" tabindex="-1" />`}
            </div>
          </aside>
        </div>
      </form>
    `:``,ce=!C||d.modal===`create`?``:`<div class="grid grid-cols-1 gap-3 sm:grid-cols-12 sm:gap-x-3 sm:gap-y-2">
          <div class="sm:col-span-2">
            <span class="${_}">Foto</span>
            <div class="relative mx-auto flex aspect-[3/4] max-h-28 w-full max-w-[7rem] items-center justify-center overflow-hidden rounded-xl border-2 border-slate-700 bg-white shadow-sm sm:mx-0">
              <img id="fvd-atleta-foto-prev" src="${T?r(w):``}" alt="" class="max-h-full max-w-full object-cover ${T?``:`hidden`}" decoding="async" />
              <span id="fvd-atleta-foto-ph" class="text-lg font-bold text-slate-600 ${T?`hidden`:``}">${r(E)}</span>
            </div>
            ${a?``:`<input type="file" id="fvd-atleta-foto-inp" name="foto" accept="image/*" class="mt-1 w-full max-w-[8.4rem] text-xs font-medium text-slate-950 file:mr-2 file:rounded-md file:border-2 file:border-slate-700 file:bg-white file:px-2 file:py-[0.45rem] file:text-xs file:font-semibold" />`}
          </div>
          <div class="sm:col-span-5 min-w-0">
            <label class="block">
              <span class="${_}">Cédula</span>
              <input name="cedula" id="fvd-atleta-cedula" required class="${g}" value="${r(String(C?.cedula??``))}" ${f} />
            </label>
            ${D?`<p class="mt-1.5 text-xs font-semibold text-slate-950">${r(D)}</p>`:``}
          </div>
          <label class="sm:col-span-3 min-w-0 sm:col-start-auto">
            <span class="${_}">Nº FVD</span>
            <input name="numfvd_display" class="${g} bg-slate-100" readonly disabled value="${r(String(C?.numfvd??0))}" />
          </label>
        </div>`,le=C?d.modal===`create`?se:`
      <form id="fvd-atleta-form" class="w-full max-w-full space-y-4 rounded-xl border-2 border-slate-600 bg-slate-100 p-5 text-slate-950 shadow-md">
        <input type="hidden" name="id" value="${r(String(C.id??``))}" />

        ${ce}

        ${A}

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${_}">Nombres</span>
            <input name="nombres" id="fvd-atleta-nombres" class="${g}" value="${r(String(C.nombres??``))}" ${f} />
          </label>
          <label class="min-w-0">
            <span class="${_}">Apellidos</span>
            <input name="apellidos" id="fvd-atleta-apellidos" class="${g}" value="${r(String(C.apellidos??``))}" ${f} />
          </label>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${_}">Sexo</span>
            <select name="sexo" class="${g}" ${f}>
              <option value="0" ${Number(C.sexo)===0?`selected`:``}>—</option>
              <option value="1" ${Number(C.sexo)===1?`selected`:``}>Masculino</option>
              <option value="2" ${Number(C.sexo)===2?`selected`:``}>Femenino</option>
            </select>
          </label>
          <label class="min-w-0">
            <span class="${_}">Fecha nac.</span>
            <input type="date" name="fechnac" id="fvd-atleta-fechnac" class="${g}" value="${r(String((C.fechnac||``).toString().slice(0,10)))}" ${f} />
          </label>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${_}">Profesión</span>
            <input name="profesion" class="${g}" value="${r(String(C.profesion??``))}" ${f} />
          </label>
          ${ae}
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${_}">Dirección</span>
            <input name="direccion" class="${g} w-full max-w-full" value="${r(String(C.direccion??``))}" ${f} />
          </label>
          <div class="hidden sm:block" aria-hidden="true"></div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="min-w-0">
            <span class="${_}">Celular</span>
            <input name="celular" class="${g}" value="${r(String(C.celular??``))}" ${f} />
          </label>
          <label class="min-w-0">
            <span class="${_}">Email</span>
            <input type="email" name="email" class="${g}" value="${r(String(C.email??``))}" ${f} />
          </label>
        </div>

        ${t.isFvdAdmin?`<label class="block w-full">
            <span class="${_}">Asociación</span>
            <select name="asociacion" class="${g} w-full max-w-full" ${f}>
              <option value="">—</option>
              ${(t.asociaciones||[]).map(e=>`<option value="${e.id}" ${Number(C.asociacion)===e.id?`selected`:``}>${r(e.nombre||``)}</option>`).join(``)}
            </select>
          </label>`:`<input type="hidden" name="asociacion" value="${r(String(C.asociacion??``))}" />`}
      </form>
    `:`<p class="py-6 text-center text-sm font-bold text-[#2e3092]">Cargando…</p>`,ue=d.tipo===`bajas`?`Bajas`:d.tipo===`no_activos`?`Pendientes`:d.tipo===`ultimos`?`Últimos mov.`:`Activos`,F=`Todas`;if(t.isFvdAdmin&&d.asociacionFilter>0&&Array.isArray(t.asociaciones)){let e=t.asociaciones.find(e=>Number(e.id)===d.asociacionFilter);F=e&&e.nombre?String(e.nombre):`Asoc. #`+d.asociacionFilter}else !t.isFvdAdmin&&Array.isArray(t.asociaciones)&&t.asociaciones[0]&&(F=String(t.asociaciones[0].nombre||`Mi asociación`));let de=d.rows.map(e=>l(e,t)).join(``),I=d.modal!==null,fe=d.modal===`create`?`Nuevo atleta`:d.modal===`edit`?`Editar atleta`:d.modal===`view`?`Ficha atleta`:``,pe=t.canCreate&&!I?`<button type="button" id="fvd-atleta-nuevo" class="inline-flex h-8 shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-[#fff200] px-2.5 text-[10px] font-extrabold uppercase tracking-wide text-[#0b1535] shadow-md ring-1 ring-black/10 transition hover:brightness-105 sm:h-9 sm:px-3 sm:text-xs">Nuevo atleta</button>`:``,L=(e.returnUrl||``).trim(),R=`inline-flex h-8 shrink-0 items-center justify-center gap-0.5 whitespace-nowrap rounded-md border border-white/30 bg-white/10 px-2 text-[10px] font-extrabold uppercase tracking-wide text-white shadow hover:bg-white/20 sm:h-9 sm:gap-1 sm:px-2.5 sm:text-xs`,z=`${n||!L?``:`<a href="${r(L)}" class="${R}">← Regresar</a>`}${n||L?``:`<button type="button" id="fvd-atleta-regresar" class="${R}">← Regresar</button>`}${pe}`,me=z.trim()===``?``:`<div class="flex min-w-0 flex-nowrap items-center justify-end gap-1.5 overflow-x-auto border-b border-white/15 py-2 sm:gap-2">${z}</div>`,he=d.error?`<div class="border-t border-white/10 py-2"><div class="rounded-md border border-red-400/40 bg-red-950/40 px-3 py-2 text-xs font-medium text-red-100 shadow-inner" role="alert">${r(d.error)}</div></div>`:``,B=e.reportsEnabled===!0&&(e.reportsBase||``).trim()!==``,ge=B?c(`reporte_indicadores.php?marcador=afiliacion`,t.isFvdAdmin,d.asociacionFilter):`#`,_e=B?c(`reporte_carnets.php`,t.isFvdAdmin,d.asociacionFilter):`#`,ve=B?c(`reporte_indicadores.php?marcador=afiliacion_anualidad`,t.isFvdAdmin,d.asociacionFilter):`#`,ye=B?c(`reporte_traspasos.php`,t.isFvdAdmin,d.asociacionFilter):`#`,be=B?`<div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 border-t border-white/10 pt-2" role="navigation" aria-label="Informes HTML">
          <span class="w-full text-[9px] font-semibold uppercase tracking-[0.18em] text-slate-500 sm:mr-1 sm:w-auto">Informes</span>
          <a class="rounded border border-white/20 bg-white/5 px-2 py-1 text-[11px] font-semibold text-[#fff200] shadow-sm hover:bg-white/10" href="${r(ge)}" title="Filas con atletas.afiliacion = 1">Afiliación</a>
          <a class="rounded border border-white/20 bg-white/5 px-2 py-1 text-[11px] font-semibold text-[#fff200] shadow-sm hover:bg-white/10" href="${r(_e)}" title="Solo filas con atletas.carnet = 1">Carnets</a>
          <a class="rounded border border-white/20 bg-white/5 px-2 py-1 text-[11px] font-semibold text-[#fff200] shadow-sm hover:bg-white/10" href="${r(ve)}" title="Afiliación y anualidad">Afil.+anual.</a>
          <a class="rounded border border-white/20 bg-white/5 px-2 py-1 text-[11px] font-semibold text-[#fff200] shadow-sm hover:bg-white/10" href="${r(ye)}" title="Historial de traspasos">Traspasos</a>
        </div>`:``,V=u.querySelector(`#fvd-atleta-q`);V instanceof HTMLInputElement&&document.activeElement===V&&(b=!0,x=V.selectionStart??V.value.length,S=V.selectionEnd??V.value.length);let xe=`
      <div class="fvd-gestion-atletas-shell flex h-[100dvh] max-h-[100dvh] flex-col overflow-hidden bg-slate-200/90">
        <div class="fvd-gestion-atletas-sticky sticky top-0 z-40 shrink-0 border-b border-black/35 bg-gradient-to-br from-[#060d22] via-[#0c1a3d] to-[#122a4a] text-slate-100 shadow-[0_8px_32px_rgba(0,0,0,0.45)]">
          <div class="mx-auto w-full max-w-[min(100%,76.8rem)] px-3 sm:px-4">
            ${me}

            <div class="border-b border-white/10 py-2 sm:py-2.5">
              <div class="flex w-full min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                <div class="flex w-full min-w-0 flex-col gap-1.5 sm:w-1/2 sm:max-w-[50%] sm:shrink-0">
                  <label class="block text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-500" for="fvd-atleta-q">Búsqueda inteligente</label>
                  <input type="search" id="fvd-atleta-q" placeholder="Cédula · email · nombre…" value="${r(d.q)}" autocomplete="off" class="min-h-[2.35rem] w-full min-w-0 rounded-md border-0 bg-white/10 px-3 py-2 text-sm text-white shadow-inner outline-none ring-1 ring-inset ring-white/15 placeholder:text-slate-500 focus:bg-white/14 focus:ring-2 focus:ring-[#fff200]/45" />
                  <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                    ${t.isFvdAdmin?`<select id="fvd-atleta-asoc" class="fvd-atleta-select-header min-h-[2.1rem] min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-900 shadow-md outline-none focus-visible:border-[#2e3092] focus-visible:ring-2 focus-visible:ring-[#fff200]/60 sm:min-w-[9.5rem] sm:flex-none sm:text-xs">${P}</select>`:``}
                    <select id="fvd-atleta-tipo" class="fvd-atleta-select-header min-h-[2.1rem] min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-900 shadow-md outline-none focus-visible:border-[#2e3092] focus-visible:ring-2 focus-visible:ring-[#fff200]/60 sm:min-w-[7.5rem] sm:flex-none sm:text-xs">
                      <option value="normal" ${d.tipo===`normal`?`selected`:``}>Activos</option>
                      <option value="bajas" ${d.tipo===`bajas`?`selected`:``}>Bajas</option>
                      <option value="no_activos" ${d.tipo===`no_activos`?`selected`:``}>Pendientes</option>
                      <option value="ultimos" ${d.tipo===`ultimos`?`selected`:``}>Últimos</option>
                    </select>
                  </div>
                </div>
                <div class="flex w-full min-w-0 flex-1 flex-row flex-wrap items-center gap-x-2 gap-y-1 sm:w-1/2 sm:max-w-[50%] sm:justify-end">
                  <span class="inline-flex items-center gap-1.5 rounded-md border border-white/15 bg-black/30 px-2 py-0.5 shadow-inner" title="Total con filtro actual">
                    <span class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Total</span>
                    <span class="text-xs font-bold tabular-nums leading-none text-white">${d.total}</span>
                  </span>
                  <span class="inline-flex max-w-[min(100%,11rem)] items-center gap-1.5 truncate rounded-md border border-white/15 bg-black/30 px-2 py-0.5 shadow-inner sm:max-w-[13rem]" title="${r(F)}">
                    <span class="shrink-0 text-[9px] font-bold uppercase tracking-wide text-slate-400">Asoc.</span>
                    <span class="truncate text-xs font-semibold leading-none text-slate-100">${r(F)}</span>
                  </span>
                  <span class="inline-flex items-center gap-1.5 rounded-md border border-white/15 bg-black/30 px-2 py-0.5 shadow-inner">
                    <span class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Vista</span>
                    <span class="text-xs font-semibold leading-none text-slate-100">${r(ue)}</span>
                  </span>
                  <span id="fvd-atleta-loading-dot" class="${d.loading?`opacity-100 animate-pulse`:`opacity-0`} inline-block h-2 w-2 shrink-0 rounded-full bg-amber-300 shadow transition-opacity duration-150" aria-hidden="true"></span>
                </div>
              </div>
            </div>

            ${be}

            ${he}
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
                ${d.loading?`<tr><td colspan="6" class="px-3 py-12 text-center text-sm text-slate-500">Actualizando listado…</td></tr>`:d.rows.length===0?`<tr><td colspan="6" class="px-3 py-12 text-center text-sm text-slate-500">Sin resultados.</td></tr>`:de}
              </tbody>
            </table>
          </div>
          <footer class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200/80 bg-slate-100/95 px-2 py-2 text-[11px] text-slate-600 shadow-[0_-4px_12px_rgba(15,23,42,0.06)] sm:px-3 sm:text-xs">
            <span class="tabular-nums">Pág. ${d.page} / ${Math.max(1,d.pages)} · ${d.total} reg.</span>
            <div class="flex gap-2">
              <button type="button" id="fvd-atleta-prev" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 sm:px-3 sm:text-sm" ${d.page<=1?`disabled`:``}>Anterior</button>
              <button type="button" id="fvd-atleta-next" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 sm:px-3 sm:text-sm" ${d.page>=d.pages?`disabled`:``}>Siguiente</button>
            </div>
          </footer>
        </div>
      </div>

      ${I?`<div class="absolute inset-0 z-[99999] flex items-center justify-center p-3 sm:p-5" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" data-close-modal></div>
        <div class="relative z-10 flex max-h-[min(92dvh,90vh)] w-[90%] max-w-[90%] flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl">
          <header class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-[#1a1f5c] via-[#2e3092] to-[#3d42c4] px-4 py-3 sm:px-5">
            <div class="min-w-0">
              <h2 class="text-lg font-bold tracking-tight text-white">${r(fe)}</h2>
              <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-widest text-[#fff200]/95">Federación Venezolana de Dominó</p>
            </div>
            <button type="button" class="rounded-lg px-2 py-1 text-xl leading-none text-white/90 hover:bg-white/10" data-close-modal aria-label="Cerrar">✕</button>
          </header>
          <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden bg-gradient-to-b from-slate-50/80 to-white px-4 py-4 sm:px-6 sm:py-5">
            ${le}
          </div>
          <footer class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-slate-100 bg-white px-4 py-3 sm:px-6">
            ${d.modal===`view`&&C&&d.modalCanEdit?`<button type="button" id="fvd-atleta-to-edit" class="rounded-lg bg-[#2e3092] px-4 py-2 text-sm font-bold text-white shadow hover:bg-[#252f7a]">Editar</button>`:``}
            ${d.modal===`edit`||d.modal===`create`?`<button type="button" id="fvd-atleta-save" class="rounded-lg bg-[#2e3092] px-4 py-2 text-sm font-bold text-white shadow hover:bg-[#252f7a] disabled:opacity-50" ${d.saving||d.modal===`create`&&d.cedulaDuplicada?`disabled`:``}>${d.saving?`Guardando…`:`Guardar`}</button>`:``}
            <button type="button" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-close-modal>Cerrar</button>
          </footer>
        </div>
      </div>`:``}
    `;u.innerHTML=``,u.innerHTML=xe;function H(){let e=u.querySelector(`#fvd-atleta-q`),t=u.querySelector(`#fvd-atleta-tipo`),n=u.querySelector(`#fvd-atleta-asoc`);d.q=e?e.value.trim():``,d.tipo=t?t.value:`normal`,n&&(d.asociacionFilter=parseInt(n.value||`0`,10)||0)}let U=u.querySelector(`#fvd-atleta-q`),W=()=>{y!=null&&clearTimeout(y),y=window.setTimeout(()=>{y=null,H(),d.page=1,k()},400)};U?.addEventListener(`input`,W),U?.addEventListener(`change`,W),U?.addEventListener(`search`,()=>{H(),d.page=1,k()}),u.querySelector(`#fvd-atleta-tipo`)?.addEventListener(`change`,()=>{H(),d.page=1,k()}),u.querySelector(`#fvd-atleta-asoc`)?.addEventListener(`change`,()=>{H(),d.page=1,k()}),u.querySelector(`#fvd-atleta-prev`)?.addEventListener(`click`,()=>{d.page>1&&(--d.page,k())}),u.querySelector(`#fvd-atleta-next`)?.addEventListener(`click`,()=>{d.page<d.pages&&(d.page+=1,k())}),u.querySelectorAll(`[data-view]`).forEach(e=>{e.addEventListener(`click`,async()=>{let t=parseInt(e.getAttribute(`data-view`)||`0`,10);t&&await N(`view`,t)})}),u.querySelectorAll(`[data-edit]`).forEach(e=>{e.addEventListener(`click`,async()=>{let t=parseInt(e.getAttribute(`data-edit`)||`0`,10);t&&await N(`edit`,t)})}),u.querySelectorAll(`[data-toggle-atleta]`).forEach(e=>{e.addEventListener(`change`,e=>{let t=e.target,n=parseInt(t.getAttribute(`data-toggle-atleta`)||`0`,10);n&&re(n)})}),u.querySelectorAll(`[data-close-modal]`).forEach(e=>{e.addEventListener(`click`,()=>M())}),u.querySelector(`#fvd-atleta-to-edit`)?.addEventListener(`click`,async()=>{let e=d.formRow&&d.formRow.id?parseInt(String(d.formRow.id),10):0;e&&await N(`edit`,e)}),u.querySelector(`#fvd-atleta-save`)?.addEventListener(`click`,()=>{let e=u.querySelector(`#fvd-atleta-form`);e&&ne(e)});let G=u.querySelector(`#fvd-atleta-cedula`);G?.addEventListener(`input`,()=>{d.modal!==`create`||!G||(v!=null&&clearTimeout(v),v=window.setTimeout(()=>{v=null,j(G.value)},450))}),G?.addEventListener(`blur`,()=>{d.modal!==`create`||!G||(v!=null&&(clearTimeout(v),v=null),j(G.value))});let K=u.querySelector(`#fvd-atleta-foto-inp`),q=u.querySelector(`#fvd-atleta-foto-prev`),J=u.querySelector(`#fvd-atleta-foto-ph`);K?.addEventListener(`change`,()=>{let e=K.files&&K.files[0];if(!e||!q)return;let t=new FileReader;t.onload=()=>{q.src=typeof t.result==`string`?t.result:``,q.classList.remove(`hidden`),J?.classList.add(`hidden`)},t.readAsDataURL(e)});let Y=u.querySelector(`#fvd-atleta-cedula-inp`),X=u.querySelector(`#fvd-atleta-cedula-prev`),Se=u.querySelector(`#fvd-atleta-cedula-ph`);Y?.addEventListener(`change`,()=>{let e=Y.files&&Y.files[0];if(!e||!X)return;let t=new FileReader;t.onload=()=>{X.src=typeof t.result==`string`?t.result:``,X.classList.remove(`hidden`),Se?.classList.add(`hidden`)},t.readAsDataURL(e)});let Z=u.querySelector(`#fvd-atleta-fechnac`),Q=u.querySelector(`#fvd-atleta-categ-hint-txt`),$=()=>{d.modal!==`create`||!Z||!Q||(Q.textContent=s(Z.value))};Z?.addEventListener(`change`,$),Z?.addEventListener(`input`,$),b&&(b=!1,requestAnimationFrame(()=>{let e=u.querySelector(`#fvd-atleta-q`);if(!(e instanceof HTMLInputElement))return;e.focus();let t=e.value.length,n=Math.min(Math.max(0,x),t),r=Math.min(Math.max(0,S),t);try{e.setSelectionRange(n,r)}catch{}})),ee(),te()}P(),k()}u();