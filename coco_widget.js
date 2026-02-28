/* ═══════════════════════════════════════════════════
   coco_widget.js — Nesquik 🐰 flotando por todas las páginas
   v3 — nombre Nesquik, diseño mejorado
   ═══════════════════════════════════════════════════ */

(function(){
'use strict';

const FB_URL = 'https://lore-basedatos-default-rtdb.firebaseio.com/mascota.json';
async function fbGet(){ try{ const r=await fetch(FB_URL); return await r.json(); }catch{ return null; } }
async function fbSet(d){ try{ await fetch(FB_URL,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)}); }catch{} }

let pet = { name:'Nesquik', hambre:80, feliz:80, energia:80, born:new Date().toISOString(), lastSeen:new Date().toISOString() };
let cocoX = window.innerWidth - 140;
let cocoY = window.innerHeight - 140;
let dirX=-1, dirY=0, walkFrame=0, miniOpen=false, actionLock=false, isJumping=false, currentEmotion='normal';

// ── STYLES ──────────────────────────────────────────
const style = document.createElement('style');
style.textContent=`
#nesquik-widget{position:fixed;z-index:9999;pointer-events:none;user-select:none;will-change:left,top;}
#nesquik-char{position:relative;width:80px;height:95px;cursor:pointer;pointer-events:all;filter:drop-shadow(0 6px 16px rgba(200,80,130,0.35));transition:filter .3s;}
#nesquik-char:hover{filter:drop-shadow(0 8px 22px rgba(244,167,192,0.7));}
#nesquik-char:active{transform:scale(.95);}

#nesquik-bubble{position:absolute;bottom:98px;left:50%;transform:translateX(-50%) translateY(5px);background:linear-gradient(135deg,rgba(255,240,248,.97),rgba(253,225,242,.97));border:1.5px solid rgba(244,167,192,.7);border-radius:18px;padding:8px 14px;font-family:'Cormorant Garamond',Georgia,serif;font-size:.78rem;color:#7a3050;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .4s,transform .4s;box-shadow:0 4px 20px rgba(232,100,138,.15),inset 0 1px 0 rgba(255,255,255,.8);}
#nesquik-bubble::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:7px solid transparent;border-top-color:rgba(244,167,192,.7);}
#nesquik-bubble::before{content:'';position:absolute;top:calc(100% - 1px);left:50%;transform:translateX(-50%);border:6px solid transparent;border-top-color:rgba(255,240,248,.97);z-index:1;}
#nesquik-bubble.show{opacity:1;transform:translateX(-50%) translateY(0);}

.nq-sparkle{position:fixed;pointer-events:none;z-index:10001;animation:nq-spark .85s ease-out forwards;font-size:1.1rem;}
@keyframes nq-spark{0%{opacity:1;transform:translate(0,0) scale(1);}100%{opacity:0;transform:translate(var(--tx),var(--ty)) scale(.4);}}
.nq-heart{position:fixed;pointer-events:none;z-index:10001;animation:nq-heart 1.1s ease-out forwards;font-size:1rem;}
@keyframes nq-heart{0%{opacity:1;transform:translateY(0) scale(.8);}50%{opacity:1;transform:translateY(-25px) scale(1.2);}100%{opacity:0;transform:translateY(-50px) scale(.9);}}
.nq-zzz{position:absolute;top:-8px;right:-4px;font-size:.8rem;color:#9bb8d4;font-family:Georgia,serif;font-style:italic;opacity:0;pointer-events:none;animation:nq-zzz 2.2s ease-in-out infinite;}
.nq-zzz:nth-child(2){animation-delay:.75s;top:-18px;right:3px;font-size:.65rem;}
.nq-zzz:nth-child(3){animation-delay:1.5s;top:-27px;right:-7px;font-size:.5rem;}
.nq-zzz.show{opacity:1;}
@keyframes nq-zzz{0%,100%{opacity:0;transform:translateY(0) rotate(-5deg);}40%,60%{opacity:.85;transform:translateY(-8px) rotate(8deg);}}

#nesquik-panel{position:fixed;z-index:10000;background:linear-gradient(145deg,#130f1e,#1c1330);border:1px solid rgba(244,167,192,.18);border-radius:26px;padding:24px 20px 18px;width:252px;box-shadow:0 24px 64px rgba(0,0,0,.75),0 0 0 1px rgba(255,255,255,.025) inset;display:none;font-family:'Cormorant Garamond',Georgia,serif;color:#f0e6d3;animation:nq-panel .4s cubic-bezier(.34,1.4,.64,1);}
@keyframes nq-panel{from{opacity:0;transform:scale(.8) translateY(14px);}to{opacity:1;transform:scale(1) translateY(0);}}
#nesquik-panel.open{display:block;}
.np-close{position:absolute;top:14px;right:16px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:50%;width:26px;height:26px;cursor:pointer;color:rgba(240,230,211,.35);font-size:.72rem;transition:all .2s;display:flex;align-items:center;justify-content:center;font-family:inherit;}
.np-close:hover{color:#f4a7c0;background:rgba(244,167,192,.08);}
.np-header{display:flex;align-items:center;justify-content:center;gap:7px;margin-bottom:3px;}
.np-name{font-size:1.05rem;font-style:italic;color:#f4a7c0;letter-spacing:.05em;}
.np-sub{text-align:center;font-size:.63rem;color:rgba(240,230,211,.28);letter-spacing:.1em;text-transform:uppercase;margin-bottom:16px;}
.np-bars{display:flex;flex-direction:column;gap:9px;margin-bottom:17px;}
.np-row{display:flex;align-items:center;gap:8px;}
.np-lbl{font-size:.64rem;width:60px;color:rgba(240,230,211,.42);letter-spacing:.04em;}
.np-track{flex:1;height:7px;background:rgba(255,255,255,.05);border-radius:8px;overflow:hidden;box-shadow:inset 0 1px 3px rgba(0,0,0,.35);}
.np-fill{height:100%;border-radius:8px;transition:width .7s cubic-bezier(.4,0,.2,1);}
.np-fill.h{background:linear-gradient(90deg,#f4a7c0,#fce8f5);}
.np-fill.f{background:linear-gradient(90deg,#c8607f,#f4a7c0);}
.np-fill.e{background:linear-gradient(90deg,#9a7c1a,#dfc050);}
.np-val{font-size:.6rem;color:rgba(240,230,211,.28);width:24px;text-align:right;}
.np-btns{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;}
.np-btn{background:rgba(255,255,255,.03);border:1px solid rgba(244,167,192,.1);border-radius:15px;padding:11px 6px 10px;cursor:pointer;color:#f0e6d3;font-family:inherit;font-size:.68rem;display:flex;flex-direction:column;align-items:center;gap:5px;transition:all .25s cubic-bezier(.34,1.4,.64,1);}
.np-btn:hover{background:rgba(244,167,192,.08);border-color:rgba(244,167,192,.25);transform:translateY(-3px);box-shadow:0 6px 20px rgba(232,100,138,.15);}
.np-btn:active{transform:scale(.93);}
.np-btn span:first-child{font-size:1.3rem;line-height:1;}
.np-link{display:block;text-align:center;margin-top:14px;font-size:.67rem;color:rgba(244,167,192,.45);font-style:italic;text-decoration:none;transition:color .2s;border-top:1px solid rgba(255,255,255,.04);padding-top:12px;}
.np-link:hover{color:#f4a7c0;}
`;
document.head.appendChild(style);

// ── SVG NESQUIK ─────────────────────────────────────
const SVG=`<svg id="nesquik-svg" viewBox="0 0 80 95" width="80" height="95" xmlns="http://www.w3.org/2000/svg">
<defs>
  <radialGradient id="nq-body" cx="38%" cy="28%" r="65%"><stop offset="0%" stop-color="#fff6fc"/><stop offset="100%" stop-color="#f2c8e8"/></radialGradient>
  <radialGradient id="nq-head" cx="38%" cy="25%" r="65%"><stop offset="0%" stop-color="#fff8fd"/><stop offset="100%" stop-color="#f2c8e8"/></radialGradient>
  <radialGradient id="nq-belly" cx="40%" cy="20%" r="70%"><stop offset="0%" stop-color="#ffffff"/><stop offset="100%" stop-color="#fce4f5"/></radialGradient>
  <radialGradient id="nq-ear" cx="50%" cy="25%" r="70%"><stop offset="0%" stop-color="#ffbbd6"/><stop offset="100%" stop-color="#dc4878"/></radialGradient>
</defs>
<g id="nq-bodyg">
  <ellipse cx="40" cy="91" rx="19" ry="4.5" fill="rgba(180,80,120,.14)"/>
  <ellipse cx="40" cy="67" rx="20" ry="22" fill="url(#nq-body)"/>
  <ellipse cx="36" cy="58" rx="8" ry="11" fill="rgba(255,255,255,.35)"/>
  <ellipse cx="40" cy="72" rx="12" ry="13" fill="url(#nq-belly)"/>
  <ellipse cx="38" cy="67" rx="5" ry="6" fill="rgba(255,255,255,.42)"/>
  <!-- LEFT EAR -->
  <ellipse cx="25" cy="22" rx="7" ry="19" fill="url(#nq-body)" transform="rotate(-11 25 22)"/>
  <ellipse cx="25" cy="22" rx="4.5" ry="14" fill="url(#nq-ear)" transform="rotate(-11 25 22)" opacity=".88"/>
  <ellipse cx="24" cy="13" rx="2" ry="4" fill="rgba(255,200,222,.45)" transform="rotate(-11 24 13)"/>
  <ellipse cx="24" cy="17" rx="5.5" ry="3" fill="#d04070" transform="rotate(-11 24 17)"/>
  <ellipse cx="24" cy="17" rx="3" ry="1.7" fill="#f2a0bc" transform="rotate(-11 24 17)"/>
  <circle cx="24" cy="17" r="1.5" fill="#d04070" transform="rotate(-11 24 17)"/>
  <!-- RIGHT EAR -->
  <ellipse cx="55" cy="22" rx="7" ry="19" fill="url(#nq-body)" transform="rotate(11 55 22)"/>
  <ellipse cx="55" cy="22" rx="4.5" ry="14" fill="url(#nq-ear)" transform="rotate(11 55 22)" opacity=".88"/>
  <ellipse cx="56" cy="13" rx="2" ry="4" fill="rgba(255,200,222,.45)" transform="rotate(11 56 13)"/>
  <ellipse cx="56" cy="17" rx="5.5" ry="3" fill="#d04070" transform="rotate(11 56 17)"/>
  <ellipse cx="56" cy="17" rx="3" ry="1.7" fill="#f2a0bc" transform="rotate(11 56 17)"/>
  <circle cx="56" cy="17" r="1.5" fill="#d04070" transform="rotate(11 56 17)"/>
  <!-- HEAD -->
  <ellipse cx="40" cy="47" rx="19" ry="18" fill="url(#nq-head)"/>
  <ellipse cx="34" cy="40" rx="7" ry="6.5" fill="rgba(255,255,255,.36)"/>
  <!-- CHEEKS -->
  <ellipse cx="26" cy="51" rx="6.5" ry="3.5" fill="#f4a7c0" opacity=".4"/>
  <ellipse cx="54" cy="51" rx="6.5" ry="3.5" fill="#f4a7c0" opacity=".4"/>
  <!-- EYES NORMAL -->
  <g id="nq-eyes-normal">
    <ellipse cx="32" cy="44" rx="3.5" ry="4.2" fill="#1c1226"/>
    <ellipse cx="30.5" cy="42.5" rx="1.6" ry="1.6" fill="white"/>
    <circle cx="30.8" cy="43.1" r=".55" fill="rgba(255,255,255,.75)"/>
    <ellipse cx="48" cy="44" rx="3.5" ry="4.2" fill="#1c1226"/>
    <ellipse cx="46.5" cy="42.5" rx="1.6" ry="1.6" fill="white"/>
    <circle cx="46.8" cy="43.1" r=".55" fill="rgba(255,255,255,.75)"/>
    <line x1="28.5" y1="41" x2="27" y2="39" stroke="#1c1226" stroke-width=".9" stroke-linecap="round"/>
    <line x1="31" y1="40" x2="30.5" y2="38" stroke="#1c1226" stroke-width=".9" stroke-linecap="round"/>
    <line x1="33.5" y1="40.5" x2="33.5" y2="38.5" stroke="#1c1226" stroke-width=".9" stroke-linecap="round"/>
    <line x1="51.5" y1="41" x2="53" y2="39" stroke="#1c1226" stroke-width=".9" stroke-linecap="round"/>
    <line x1="49" y1="40" x2="49.5" y2="38" stroke="#1c1226" stroke-width=".9" stroke-linecap="round"/>
    <line x1="46.5" y1="40.5" x2="46.5" y2="38.5" stroke="#1c1226" stroke-width=".9" stroke-linecap="round"/>
  </g>
  <!-- EYES HAPPY -->
  <g id="nq-eyes-happy" style="display:none">
    <path d="M28 47 Q32 41 36 47" stroke="#1c1226" stroke-width="2.2" fill="none" stroke-linecap="round"/>
    <path d="M44 47 Q48 41 52 47" stroke="#1c1226" stroke-width="2.2" fill="none" stroke-linecap="round"/>
    <line x1="28" y1="47" x2="26.5" y2="45" stroke="#1c1226" stroke-width=".9" stroke-linecap="round"/>
    <line x1="32" y1="42" x2="31" y2="40" stroke="#1c1226" stroke-width=".9" stroke-linecap="round"/>
    <line x1="52" y1="47" x2="53.5" y2="45" stroke="#1c1226" stroke-width=".9" stroke-linecap="round"/>
    <line x1="48" y1="42" x2="49" y2="40" stroke="#1c1226" stroke-width=".9" stroke-linecap="round"/>
  </g>
  <!-- EYES SAD -->
  <g id="nq-eyes-sad" style="display:none">
    <ellipse cx="32" cy="45" rx="3.2" ry="3.8" fill="#1c1226"/>
    <ellipse cx="30.5" cy="43.5" rx="1.3" ry="1.3" fill="white"/>
    <ellipse cx="48" cy="45" rx="3.2" ry="3.8" fill="#1c1226"/>
    <ellipse cx="46.5" cy="43.5" rx="1.3" ry="1.3" fill="white"/>
    <path d="M28 40.5 Q32 43 36 42" stroke="#1c1226" stroke-width="1.2" fill="none" stroke-linecap="round"/>
    <path d="M44 42 Q48 43 52 40.5" stroke="#1c1226" stroke-width="1.2" fill="none" stroke-linecap="round"/>
    <ellipse cx="31" cy="50" rx="1.3" ry="2" fill="#a8d4f0" opacity=".82"/>
    <ellipse cx="47" cy="50" rx="1.3" ry="2" fill="#a8d4f0" opacity=".82"/>
  </g>
  <!-- EYES BLINK -->
  <g id="nq-eyes-blink" style="display:none">
    <line x1="28.5" y1="44" x2="35.5" y2="44" stroke="#1c1226" stroke-width="2.2" stroke-linecap="round"/>
    <line x1="44.5" y1="44" x2="51.5" y2="44" stroke="#1c1226" stroke-width="2.2" stroke-linecap="round"/>
  </g>
  <!-- NOSE -->
  <ellipse cx="40" cy="52" rx="2.8" ry="2" fill="#dc4878"/>
  <ellipse cx="39.2" cy="51.3" rx=".9" ry=".7" fill="rgba(255,200,220,.6)"/>
  <!-- MOUTHS -->
  <path id="nq-mouth-happy"  d="M36 54.5 Q40 58.5 44 54.5" stroke="#b83860" stroke-width="1.3" fill="none" stroke-linecap="round"/>
  <path id="nq-mouth-sad"    d="M36.5 58 Q40 54.5 43.5 58" stroke="#b83860" stroke-width="1.3" fill="none" stroke-linecap="round" style="display:none"/>
  <!-- WHISKERS -->
  <line x1="18" y1="51" x2="29" y2="52.5" stroke="rgba(160,100,130,.28)" stroke-width=".8" stroke-linecap="round"/>
  <line x1="17" y1="53.5" x2="28" y2="53.5" stroke="rgba(160,100,130,.26)" stroke-width=".8" stroke-linecap="round"/>
  <line x1="51" y1="52.5" x2="62" y2="51" stroke="rgba(160,100,130,.28)" stroke-width=".8" stroke-linecap="round"/>
  <line x1="52" y1="53.5" x2="63" y2="53.5" stroke="rgba(160,100,130,.26)" stroke-width=".8" stroke-linecap="round"/>
  <!-- BOW -->
  <g transform="translate(40,62)">
    <path d="M-12,-4 Q-9,-12 -2,-6 Q-7,-1 -12,-4Z" fill="#c8507a"/>
    <path d="M-12,-4 Q-9,-12 -2,-6 Q-7,-1 -12,-4Z" fill="rgba(255,180,210,.3)"/>
    <path d="M12,-4 Q9,-12 2,-6 Q7,-1 12,-4Z" fill="#c8507a"/>
    <path d="M12,-4 Q9,-12 2,-6 Q7,-1 12,-4Z" fill="rgba(255,180,210,.3)"/>
    <circle cx="0" cy="-5" r="4" fill="#dc4878"/>
    <circle cx="-.6" cy="-5.7" r="1.5" fill="rgba(255,200,225,.7)"/>
  </g>
  <!-- ARMS -->
  <ellipse cx="21" cy="66" rx="5.5" ry="9" fill="#f2c8e8" transform="rotate(-18 21 66)"/>
  <ellipse cx="19.5" cy="73.5" rx="5.5" ry="3.5" fill="#fce4f5"/>
  <ellipse cx="59" cy="66" rx="5.5" ry="9" fill="#f2c8e8" transform="rotate(18 59 66)"/>
  <ellipse cx="60.5" cy="73.5" rx="5.5" ry="3.5" fill="#fce4f5"/>
  <!-- FEET -->
  <ellipse id="nq-foot-l" cx="31" cy="87" rx="9.5" ry="5.5" fill="#fce4f5"/>
  <ellipse id="nq-foot-r" cx="49" cy="87" rx="9.5" ry="5.5" fill="#fce4f5"/>
  <ellipse cx="26.5" cy="91" rx="2.8" ry="1.7" fill="#f2c8e8"/>
  <ellipse cx="31" cy="92" rx="2.8" ry="1.7" fill="#f2c8e8"/>
  <ellipse cx="35.5" cy="91" rx="2.8" ry="1.7" fill="#f2c8e8"/>
  <ellipse cx="44.5" cy="91" rx="2.8" ry="1.7" fill="#f2c8e8"/>
  <ellipse cx="49" cy="92" rx="2.8" ry="1.7" fill="#f2c8e8"/>
  <ellipse cx="53.5" cy="91" rx="2.8" ry="1.7" fill="#f2c8e8"/>
  <!-- TAIL -->
  <ellipse cx="59" cy="66" rx="7.5" ry="6.5" fill="white"/>
  <ellipse cx="58" cy="65" rx="3.5" ry="3" fill="rgba(255,255,255,.85)"/>
</g>
</svg>`;

// ── BUILD DOM ────────────────────────────────────────
const widget=document.createElement('div');
widget.id='nesquik-widget';
widget.innerHTML=`<div id="nesquik-char"><div id="nesquik-bubble"></div><div class="nq-zzz">z</div><div class="nq-zzz">z</div><div class="nq-zzz">z</div>${SVG}</div>`;
document.body.appendChild(widget);

const panel=document.createElement('div');
panel.id='nesquik-panel';
panel.innerHTML=`
  <button class="np-close" onclick="nqClosePanel()">✕</button>
  <div class="np-header"><span>🎀</span><div class="np-name" id="nq-name-txt">Nesquik</div><span>🎀</span></div>
  <div class="np-sub">mi conejita favorita</div>
  <div class="np-bars">
    <div class="np-row"><span class="np-lbl">🍓 Hambre</span><div class="np-track"><div class="np-fill h" id="nq-h" style="width:80%"></div></div><span class="np-val" id="nq-hv">80</span></div>
    <div class="np-row"><span class="np-lbl">💗 Feliz</span><div class="np-track"><div class="np-fill f" id="nq-f" style="width:80%"></div></div><span class="np-val" id="nq-fv">80</span></div>
    <div class="np-row"><span class="np-lbl">⚡ Energía</span><div class="np-track"><div class="np-fill e" id="nq-e" style="width:80%"></div></div><span class="np-val" id="nq-ev">80</span></div>
  </div>
  <div class="np-btns">
    <button class="np-btn" onclick="nqFeed()"><span>🍓</span><span>Comer</span></button>
    <button class="np-btn" onclick="nqPlay()"><span>🎀</span><span>Jugar</span></button>
    <button class="np-btn" onclick="nqKiss()"><span>💋</span><span>Besito</span></button>
  </div>
  <a href="mascota.html" class="np-link">Ver a Nesquik completa →</a>
`;
document.body.appendChild(panel);

// ── HELPERS ──────────────────────────────────────────
function setPanelBars(){
  document.getElementById('nq-name-txt').textContent=pet.name;
  const h=Math.round(pet.hambre),f=Math.round(pet.feliz),e=Math.round(pet.energia);
  document.getElementById('nq-h').style.width=h+'%'; document.getElementById('nq-hv').textContent=h;
  document.getElementById('nq-f').style.width=f+'%'; document.getElementById('nq-fv').textContent=f;
  document.getElementById('nq-e').style.width=e+'%'; document.getElementById('nq-ev').textContent=e;
}
function showBubble(msg,ms=2400){
  const b=document.getElementById('nesquik-bubble');
  b.textContent=msg; b.classList.add('show');
  clearTimeout(b._t); b._t=setTimeout(()=>b.classList.remove('show'),ms);
}
function setEyes(t){
  ['normal','happy','sad','blink'].forEach(n=>{
    const el=document.getElementById('nq-eyes-'+n);
    if(el) el.style.display=(n===t)?'':'none';
  });
}
function setMouth(t){
  ['happy','sad'].forEach(n=>{
    const el=document.getElementById('nq-mouth-'+n);
    if(el) el.style.display=(n===t)?'':'none';
  });
}
function updateMood(){
  const zzz=[...document.querySelectorAll('.nq-zzz')];
  if(pet.energia<20){
    currentEmotion='tired'; setEyes('sad'); setMouth('sad');
    zzz.forEach(z=>z.classList.add('show')); showBubble('Estoy muy cansada... 😴');
  } else {
    zzz.forEach(z=>z.classList.remove('show'));
    if(pet.hambre<20){ currentEmotion='hungry'; setEyes('sad'); setMouth('sad'); showBubble('Tengo hambre... 🥺'); }
    else if(pet.feliz<20){ currentEmotion='sad'; setEyes('sad'); setMouth('sad'); showBubble('Me siento solita 💔'); }
    else if(pet.feliz>85){ currentEmotion='happy'; setEyes('happy'); setMouth('happy'); }
    else { currentEmotion='normal'; setEyes('normal'); setMouth('happy'); }
  }
}

function spawnSparkles(x,y,emojis){
  emojis.forEach((em,i)=>{
    const el=document.createElement('div'); el.className='nq-sparkle'; el.textContent=em;
    el.style.left=x+'px'; el.style.top=y+'px';
    const a=(Math.PI*2/emojis.length)*i;
    el.style.setProperty('--tx',Math.cos(a)*50+'px');
    el.style.setProperty('--ty',Math.sin(a)*45-20+'px');
    document.body.appendChild(el); setTimeout(()=>el.remove(),950);
  });
}
function spawnHeart(x,y){
  const el=document.createElement('div'); el.className='nq-heart';
  el.textContent=['💗','💕','💖','🌸'][Math.floor(Math.random()*4)];
  el.style.left=(x+Math.random()*24-12)+'px'; el.style.top=y+'px';
  document.body.appendChild(el); setTimeout(()=>el.remove(),1200);
}

function startBlink(){
  const cycle=()=>{
    if(!miniOpen && currentEmotion!=='tired'){
      const st=currentEmotion==='happy'?'happy':currentEmotion==='sad'?'sad':'normal';
      setEyes('blink'); setTimeout(()=>setEyes(st),150);
    }
    setTimeout(cycle,2500+Math.random()*4000);
  };
  setTimeout(cycle,2000+Math.random()*3000);
}

function doJump(){
  if(isJumping)return; isJumping=true; let f=0;
  const jLoop=()=>{
    f++;
    const ch=document.getElementById('nesquik-char');
    const jy=Math.sin(f/20*Math.PI)*32;
    if(ch) ch.style.marginTop=-jy+'px';
    if(f<20) requestAnimationFrame(jLoop);
    else{ if(ch) ch.style.marginTop='0'; isJumping=false; }
  };
  requestAnimationFrame(jLoop);
}

// ── WALK ─────────────────────────────────────────────
function walk(){
  walkFrame++;
  if(!miniOpen){
    const offset=Math.sin(walkFrame*.22)*4.5;
    const fl=document.getElementById('nq-foot-l');
    const fr=document.getElementById('nq-foot-r');
    if(fl) fl.setAttribute('cy',String(87-Math.max(0,offset)));
    if(fr) fr.setAttribute('cy',String(87-Math.max(0,-offset)));
    const bg=document.getElementById('nq-bodyg');
    if(bg){ const bob=Math.sin(walkFrame*.22)*2; bg.setAttribute('transform',`translateY(${bob})`); }
    const speed=currentEmotion==='tired'?.3:currentEmotion==='happy'?1.15:.82;
    cocoX+=dirX*speed; cocoY+=dirY*speed;
    const M=10,W=window.innerWidth-90,H=window.innerHeight-110;
    if(cocoX<M){ cocoX=M; dirX=1; flip(); }
    if(cocoX>W){ cocoX=W; dirX=-1; flip(); }
    if(cocoY<M){ cocoY=M; dirY=1; }
    if(cocoY>H){ cocoY=H; dirY=-1; }
    if(walkFrame%300===0){ const a=Math.random()*Math.PI*2; dirX=Math.cos(a)>0?1:-1; dirY=Math.sin(a)*.5; flip(); }
    widget.style.left=cocoX+'px'; widget.style.top=cocoY+'px';
  }
  requestAnimationFrame(walk);
}
function flip(){ const ch=document.getElementById('nesquik-char'); ch.style.transform=dirX>0?'scaleX(-1)':'scaleX(1)'; }

// ── CLICK ────────────────────────────────────────────
const char=document.getElementById('nesquik-char');
char.addEventListener('click',()=>{
  if(miniOpen){ closePanel(); return; }
  miniOpen=true; panel.classList.add('open'); doJump();
  const pw=252,ph=250;
  let px=cocoX-pw/2+40, py=cocoY-ph-15;
  if(px<10)px=10;
  if(px+pw>window.innerWidth-10)px=window.innerWidth-pw-10;
  if(py<10)py=cocoY+105;
  panel.style.left=px+'px'; panel.style.top=py+'px';
  setPanelBars();
  showBubble(currentEmotion==='happy'?'¡Hola! 💗🌸':'¡Hola! 🌸');
  spawnSparkles(cocoX+40,cocoY+20,['✨','🌸','💗','🎀']);
});
function closePanel(){ miniOpen=false; panel.classList.remove('open'); }
window.nqClosePanel=closePanel;

window.nqFeed=function(){
  if(actionLock)return; actionLock=true; setTimeout(()=>actionLock=false,700);
  pet.hambre=Math.min(100,pet.hambre+20); pet.feliz=Math.min(100,pet.feliz+5);
  fbSet(pet); setPanelBars(); updateMood();
  showBubble('¡Qué rica fresa! 🍓');
  spawnSparkles(cocoX+40,cocoY+10,['🍓','✨','💗','🌸']); doJump();
};
window.nqPlay=function(){
  if(actionLock)return; actionLock=true; setTimeout(()=>actionLock=false,700);
  if(pet.energia<15){ showBubble('Estoy muy cansada 😴'); return; }
  pet.feliz=Math.min(100,pet.feliz+15); pet.energia=Math.max(0,pet.energia-8);
  fbSet(pet); setPanelBars(); updateMood();
  showBubble('¡Yay! 🎀✨');
  spawnSparkles(cocoX+40,cocoY+10,['🎀','💫','✨','🌸','💃']); doJump();
};
window.nqKiss=function(){
  if(actionLock)return; actionLock=true; setTimeout(()=>actionLock=false,700);
  pet.feliz=Math.min(100,pet.feliz+12);
  fbSet(pet); setPanelBars(); updateMood();
  showBubble('💋 ¡Más besitos!');
  [0,200,400,600].forEach(d=>setTimeout(()=>spawnHeart(cocoX+30,cocoY),d)); doJump();
};

// ── INIT ─────────────────────────────────────────────
async function init(){
  const data=await fbGet();
  if(data){
    const now=new Date(),last=new Date(data.lastSeen||now);
    const hrs=Math.min((now-last)/3600000,24);
    data.hambre=Math.max(0,(data.hambre||80)-hrs*4);
    data.feliz =Math.max(0,(data.feliz ||80)-hrs*3);
    data.energia=Math.max(0,(data.energia||80)-hrs*2);
    if(!data.name||data.name==='Coco') data.name='Nesquik';
    pet={...pet,...data};
  }
  widget.style.left=cocoX+'px'; widget.style.top=cocoY+'px';
  updateMood(); startBlink();

  const bubbles=['¡Cuídame! 🌸','¿Me das besitos? 💋','¡Tengo hambre! 🍓','Hola hermosa 🎀','¡Juguemos! 💃','Te quiero 💗','¡Soy Nesquik! 🐰','✨ ¡Qué bonito lugar!','¿Lees algo bueno? 📖','¡Miau! ...ops 🐰'];
  setInterval(()=>{ if(!miniOpen) showBubble(bubbles[Math.floor(Math.random()*bubbles.length)],2800); },35000);
  setInterval(()=>{ if(!miniOpen&&currentEmotion==='happy'&&Math.random()>.5) spawnHeart(cocoX+40,cocoY+10); },6000);

  walk();
}

document.addEventListener('click',e=>{ if(miniOpen&&!panel.contains(e.target)&&!char.contains(e.target)) closePanel(); });
init();
})();