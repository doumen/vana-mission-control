/**
 * VanaHariKathaController.js
 * Sprint 1 — Modo Foco (passage isolado com prev/next)
 * Depends on window.vanaHKConfig injected by hari-katha.php
 */
;(function () {
  'use strict';

  var CFG = window.vanaHKConfig;
  if (!CFG || !CFG.visitId) return;

  var API    = String(CFG.restBase).replace(/\/$/, '');
  var LANG   = CFG.lang || 'pt';
  var I18N   = CFG.i18n || {};
  var NONCE  = CFG.restNonce || '';

  var state = { kathas: [], activeKatha: null, passages: [], page:1, hasMore:false, loading:false, focusIndex:-1 };

  var root, zone;
  var panelList, panelPassages, panelFocus;
  var introEl, kathaListEl; var kathaTitleEl, kathaMetaEl, passageListEl, paginationEl; var focusPosEl, focusContentEl, prevBtn, nextBtn, seeFullLink;

  function init() {
    root = document.getElementById('vana-section-hari-katha'); if (!root) return;
    zone = document.getElementById('vana-hk-zone'); if (!zone) return;
    panelList = zone.querySelector('[data-panel="list"]'); panelPassages = zone.querySelector('[data-panel="passages"]'); panelFocus = zone.querySelector('[data-panel="focus"]');
    introEl = panelList.querySelector('[data-role="hk-intro"]'); kathaListEl = panelList.querySelector('[data-role="katha-list"]');
    kathaTitleEl = panelPassages.querySelector('[data-role="katha-title"]'); kathaMetaEl = panelPassages.querySelector('[data-role="katha-meta"]'); passageListEl = panelPassages.querySelector('[data-role="passage-list"]'); paginationEl = panelPassages.querySelector('[data-role="pagination"]');
    focusPosEl = panelFocus.querySelector('[data-role="focus-position"]'); focusContentEl = panelFocus.querySelector('[data-role="focus-content"]'); prevBtn = panelFocus.querySelector('[data-action="prev"]'); nextBtn = panelFocus.querySelector('[data-action="next"]'); seeFullLink = panelFocus.querySelector('[data-action="see-full"]');
    zone.addEventListener('click', onZoneClick); document.addEventListener('keydown', onKeydown); fetchKathas();
  }

  function switchPanel(name) { var panels = zone.querySelectorAll('.vana-hk-panel'); for (var i=0;i<panels.length;i++){ var p=panels[i]; var isTarget = p.getAttribute('data-panel')===name; p.hidden = !isTarget; p.setAttribute('aria-hidden', isTarget?'false':'true'); } zone.setAttribute('data-state', name); root.scrollIntoView({ behavior: 'smooth', block: 'start' }); }

  function fetchKathas(){ var url = API + '/kathas?visit_id=' + enc(CFG.visitId) + '&day=' + enc(CFG.day); apiFetch(url).then(function(json){ if(!json || !json.success || !json.data || !json.data.items || !json.data.items.length) { introEl.textContent = I18N.empty || 'Nenhuma kathā registrada.'; return; } state.kathas = json.data.items; renderKathaList(json.data.items); }).catch(function(){ introEl.textContent = I18N.err_kathas || 'Erro ao carregar.'; }); }

  function renderKathaList(kathas){ introEl.hidden = true; kathaListEl.innerHTML = ''; var periodIcon={morning:'🌅',midday:'☀️',night:'🌙',other:'📌'}; var periodLabel={morning:I18N.morning,midday:I18N.midday,night:I18N.night,other:I18N.other}; kathas.forEach(function(katha, idx){ var title = pickLang(katha,'title')||'Sem título'; var excerpt=pickLang(katha,'excerpt')||''; var period=katha.period||'other'; var icon=periodIcon[period]||'📌'; var label=periodLabel[period]||period; var count=katha.passage_count||0; var btn=document.createElement('button'); btn.type='button'; btn.className='vana-hk-card'; btn.setAttribute('data-action','open-katha'); btn.setAttribute('data-katha-index', idx); btn.innerHTML = '<span class="vana-hk-card__period">'+icon+' '+esc(label)+'</span>'+'<span class="vana-hk-card__count">'+count+' '+esc(I18N.passages||'passages')+'</span>'+'<span class="vana-hk-card__title">'+esc(title)+'</span>' + (excerpt? '<span class="vana-hk-card__excerpt">'+esc(excerpt)+'</span>':''); kathaListEl.appendChild(btn); }); }

  function openKatha(index){ var katha=state.kathas[index]; if(!katha) return; state.activeKatha=katha; state.passages=[]; state.page=1; state.hasMore=false; state.focusIndex=-1; kathaTitleEl.textContent = pickLang(katha,'title')||''; kathaMetaEl.innerHTML = (katha.scripture? '<span class="vana-hk-meta-scripture">'+esc(katha.scripture)+'</span>':'') + '<span class="vana-hk-meta-count">'+(katha.passage_count||0)+' passages</span>'; passageListEl.innerHTML = '<p class="vana-hk__intro">'+esc(I18N.loading||'Carregando…')+'</p>'; paginationEl.hidden=true; switchPanel('passages'); fetchPassages(katha.id,1); }

  function fetchPassages(kathaId,page){ if(state.loading) return; state.loading=true; var url = API + '/passages?katha_id='+enc(kathaId)+'&page='+page; apiFetch(url).then(function(json){ state.loading=false; if(!json || !json.success || !json.data || !json.data.items){ passageListEl.innerHTML = '<p class="vana-hk__error">'+esc(I18N.err_passages)+'</p>'; return; } var items = json.data.items; if(page===1){ state.passages = items; passageListEl.innerHTML=''; } else { state.passages = state.passages.concat(items); } renderPassageCards(items, passageListEl); state.page = page; state.hasMore = !!json.data.has_more; paginationEl.hidden = !state.hasMore; }).catch(function(){ state.loading=false; passageListEl.innerHTML = '<p class="vana-hk__error">'+esc(I18N.err_passages)+'</p>'; }); }

  function renderPassageCards(passages, container){ var kindIcon={narrative:'📖',instruction:'📘',verse_commentary:'🕉️',dialogue:'💬',anecdote:'📜',prayer:'🙏','gaura-lila':'🌸',story:'📖',teaching:'📘',other:'•'}; passages.forEach(function(p,i){ var globalIndex = state.passages.indexOf(p); if(globalIndex===-1) globalIndex = state.passages.length - passages.length + i; var kind = p.passage_kind || 'other'; var icon = kindIcon[kind] || '•'; var hookVal = pickLangField(p,'hook') || pickLangField(p,'key_quote') || ''; var card = document.createElement('button'); card.type='button'; card.className='vana-hk-passage-card'; card.setAttribute('data-action','open-passage'); card.setAttribute('data-passage-index', globalIndex); var tsHtml = p.t_start ? '<span class="vana-hk-passage-card__ts">'+esc(p.t_start)+'</span>' : ''; var badges = ''; if(p.reel_worthy) badges += '<span class="vana-hk-badge" title="'+esc(I18N.reel)+'">🎬</span>'; if(p.contains_confidential_content) badges += '<span class="vana-hk-badge" title="'+esc(I18N.confidential)+'">🔒</span>'; card.innerHTML = '<div class="vana-hk-passage-card__header">'+'<span class="vana-hk-passage-card__ref">'+esc(p.passage_ref||'')+'</span>'+'<span class="vana-hk-passage-card__kind">'+icon+' '+esc(kind)+'</span>'+tsHtml+badges+'</div>'+'<div class="vana-hk-passage-card__hook">'+esc(hookVal)+'</div>'; container.appendChild(card); }); }

  function openPassage(index){ if(index<0||index>=state.passages.length) return; state.focusIndex=index; var p = state.passages[index]; focusPosEl.textContent = (index+1)+' '+(I18N.of||'de')+' '+state.passages.length; renderFocusContent(p); prevBtn.disabled = (index===0); nextBtn.disabled = (index===state.passages.length-1); if(seeFullLink){ seeFullLink.setAttribute('data-action','back-to-passages'); seeFullLink.textContent = I18N.see_full || '📜 Ver aula completa'; } switchPanel('focus'); try{ var url = p.permalink || ''; if(url) history.pushState({ vanaHK:true, passageIndex: index }, '', url); }catch(e){} }

  function renderFocusContent(p){ var kindIcon={narrative:'📖',instruction:'📘',verse_commentary:'🕉️',dialogue:'💬',anecdote:'📜',prayer:'🙏','gaura-lila':'🌸',story:'📖',teaching:'📘',other:'•'}; var kind=p.passage_kind||'other'; var icon=kindIcon[kind]||'•'; var hookVal=pickLangField(p,'hook'); var quoteVal=pickLangField(p,'key_quote'); var contentVal=pickLangField(p,'content'); var tsHtml=''; if(p.t_start){ tsHtml = '<button type="button" class="vana-hk-focus__seek" data-action="seek" data-t="'+esc(p.t_start)+'" title="'+esc(I18N.seek||'Ir para este trecho')+'">▶ '+esc(p.t_start)+'</button>'; } var badges=''; if(p.reel_worthy) badges += '<span class="vana-hk-badge" title="'+esc(I18N.reel)+'">🎬</span>'; if(p.contains_confidential_content) badges += '<span class="vana-hk-badge" title="'+esc(I18N.confidential)+'">🔒</span>'; var permalinkHtml = p.permalink ? '<button type="button" class="vana-hk-focus__copy" data-action="copy-link" data-url="'+esc(p.permalink)+'">'+esc(I18N.permalink||'🔗 Permalink')+'</button>' : ''; focusContentEl.innerHTML = '<header class="vana-hk-focus__header">'+'<span class="vana-hk-focus__ref">#'+esc(p.index||p.passage_ref||'')+'</span>'+'<span class="vana-hk-focus__kind">'+icon+' '+esc(kind)+'</span>'+tsHtml+badges+'</header>' + (hookVal? '<h3 class="vana-hk-focus__hook">'+esc(hookVal)+'</h3>':'') + (quoteVal? '<blockquote class="vana-hk-focus__quote">"'+esc(quoteVal)+'"</blockquote>':'') + (contentVal? '<div class="vana-hk-focus__body">'+contentVal+'</div>':'') + '<footer class="vana-hk-focus__footer">'+permalinkHtml+'</footer>'; }

  function onZoneClick(e){ var target = e.target.closest('[data-action]'); if(!target) return; var action = target.getAttribute('data-action'); switch(action){ case 'open-katha': var ki = parseInt(target.getAttribute('data-katha-index'),10); if(!isNaN(ki)) openKatha(ki); break; case 'open-passage': var pi = parseInt(target.getAttribute('data-passage-index'),10); if(!isNaN(pi)) openPassage(pi); break; case 'back-to-list': state.activeKatha=null; state.passages=[]; state.focusIndex=-1; switchPanel('list'); try{ history.replaceState({}, '', window.location.pathname + window.location.search);}catch(ex){} break; case 'back-to-passages': state.focusIndex=-1; switchPanel('passages'); try{ history.replaceState({}, '', window.location.pathname + window.location.search);}catch(ex){} break; case 'load-more': if(state.activeKatha && state.hasMore && !state.loading){ fetchPassages(state.activeKatha.id, state.page+1); } break; case 'prev': if(state.focusIndex>0) openPassage(state.focusIndex-1); break; case 'next': if(state.focusIndex<state.passages.length-1) openPassage(state.focusIndex+1); break; case 'seek': var tVal = target.getAttribute('data-t')||''; seekStage(tVal); break; case 'copy-link': var copyUrl = target.getAttribute('data-url')||''; copyToClipboard(copyUrl,target); break; case 'see-full': e.preventDefault(); state.focusIndex=-1; switchPanel('passages'); break; } }

  function onKeydown(e){ if(zone.getAttribute('data-state')!=='focus') return; if(e.key==='ArrowLeft'&&state.focusIndex>0){ e.preventDefault(); openPassage(state.focusIndex-1);} if(e.key==='ArrowRight'&&state.focusIndex<state.passages.length-1){ e.preventDefault(); openPassage(state.focusIndex+1);} if(e.key==='Escape'){ e.preventDefault(); state.focusIndex=-1; switchPanel('passages'); } }

  window.addEventListener('popstate', function(e){ if(e.state && e.state.vanaHK && typeof e.state.passageIndex==='number'){ if(state.passages.length>e.state.passageIndex){ state.focusIndex = e.state.passageIndex; var p = state.passages[state.focusIndex]; focusPosEl.textContent = (state.focusIndex+1) + ' ' + (I18N.of||'de') + ' ' + state.passages.length; renderFocusContent(p); prevBtn.disabled = (state.focusIndex===0); nextBtn.disabled = (state.focusIndex===state.passages.length-1); switchPanel('focus'); } } else { var currentState = zone.getAttribute('data-state'); if(currentState==='focus'){ state.focusIndex=-1; switchPanel('passages'); } else if(currentState==='passages'){ state.activeKatha=null; state.passages=[]; switchPanel('list'); } } });

  function seekStage(timeStr){ var parts = String(timeStr).split(':').map(Number); var sec=0; if(parts.length===3) sec=parts[0]*3600+parts[1]*60+parts[2]; else if(parts.length===2) sec=parts[0]*60+parts[1]; var iframe = document.getElementById('vanaStageIframe'); if(iframe && iframe.contentWindow){ iframe.contentWindow.postMessage(JSON.stringify({ event:'command', func:'seekTo', args:[sec,true] }),'*'); var target = iframe.closest('section')||iframe; target.scrollIntoView({ behavior:'smooth', block:'start' }); } }

  function copyToClipboard(text, btn){ if(!text) return; var originalText = btn.textContent; if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(text).then(function(){ btn.textContent = I18N.copied || 'Copiado!'; setTimeout(function(){ btn.textContent = originalText; },2000); }); } else { var ta=document.createElement('textarea'); ta.value=text; ta.style.cssText='position:fixed;opacity:0;'; document.body.appendChild(ta); ta.select(); try{ document.execCommand('copy'); btn.textContent = I18N.copied || 'Copiado!'; }catch(ex){} document.body.removeChild(ta); setTimeout(function(){ btn.textContent = originalText; },2000); } }

  function apiFetch(url){ var headers={'Accept':'application/json'}; if(NONCE) headers['X-WP-Nonce']=NONCE; return fetch(url,{ headers: headers, credentials: 'same-origin' }).then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); }); }

  function pickLang(obj, field){ if(LANG==='en') return obj[field+'_en']||obj[field+'_pt']||''; return obj[field+'_pt']||obj[field+'_en']||''; }
  function pickLangField(p, field){ if(LANG==='en') return p[field+'_en']||p[field+'_pt']||''; return p[field+'_pt']||p[field+'_en']||''; }
  function esc(str){ return String(str==null?'':str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function enc(v){ return encodeURIComponent(v); }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();

})();
