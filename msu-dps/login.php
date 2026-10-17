<?php require __DIR__ . '/inc/bootstrap.php';
// Arriving here (for example with the Back button) means the person must sign in again: end any open session.
if (!empty($_SESSION['uid'])) end_session('logout'); ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in · MSU Dropout Prediction System</title>
<link rel="icon" type="image/png" href="assets/img/logo.png"><link rel="stylesheet" href="assets/css/style.css">
<style>
body{background:#d9eef9}
.scene{position:relative;min-height:100vh;overflow:hidden;display:flex;align-items:center;justify-content:flex-end;padding:32px 8vw;background:linear-gradient(180deg,#c6e7f6 0%,#e6f4fb 62%,#f2f4f8 100%)}
.cloud{position:absolute;height:34px;border-radius:40px;background:rgba(255,255,255,.75);animation:drift 40s linear infinite}
.cloud::before,.cloud::after{content:"";position:absolute;background:inherit;border-radius:50%}
.cloud::before{width:56px;height:56px;left:22px;top:-26px}.cloud::after{width:42px;height:42px;left:70px;top:-16px}
.c1{width:170px;top:11%;left:-200px}.c2{width:130px;top:26%;left:-200px;animation-duration:55s;animation-delay:-20s;opacity:.7}.c3{width:200px;top:7%;left:-200px;animation-duration:70s;animation-delay:-45s;opacity:.6}
.hills{position:absolute;left:0;right:0;bottom:0;width:100%;height:36vh;min-height:200px}
.deco{position:absolute;transform-origin:50% 100%;animation:sway 5s ease-in-out infinite}
.t1{left:30%;bottom:19vh;height:26vh}.t2{left:41%;bottom:15vh;height:20vh;animation-delay:-1.5s}.t3{right:2%;bottom:20vh;height:25vh;animation-delay:-3s}.t4{right:21%;bottom:6vh;height:22vh;animation-delay:-2s}.t5{left:2.5%;bottom:4vh;height:19vh;animation-delay:-4s}
.person{position:absolute;left:7vw;bottom:3vh;width:clamp(230px,27vw,380px);height:auto;animation:bob 4.5s ease-in-out infinite;z-index:1}
.bub{animation:float 3.6s ease-in-out infinite}.glow{animation:pulse 2.4s ease-in-out infinite}
.card{position:relative;z-index:2;width:min(460px,100%);background:#fff;border-radius:18px;padding:40px 44px 34px;box-shadow:0 24px 60px rgba(16,35,59,.16);animation:rise .7s ease both}
.card .logo{display:block;height:68px;margin:0 auto 10px}
.card h1{margin:0;text-align:center;font-size:30px;font-weight:800;color:var(--navy);letter-spacing:-.3px}
.card .sub{text-align:center;color:var(--muted);font-size:13px;line-height:1.5;margin:6px 0 26px}
.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
.field{position:relative;margin-bottom:16px}
.field input{width:100%;height:50px;border:1.5px solid var(--blue);border-radius:999px;padding:0 48px 0 48px;font-size:14px;outline:none;background:#fff;color:var(--ink);transition:.2s}
.field input:focus{box-shadow:0 0 0 4px rgba(0,114,188,.16);border-color:var(--navy)}
.field>svg{position:absolute;left:18px;top:50%;transform:translateY(-50%);width:18px;height:18px;stroke:var(--navy);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.field .eye{right:10px;top:50%}
.opts{display:flex;justify-content:space-between;align-items:center;font-size:12.5px;margin:4px 6px 22px;color:var(--muted)}
.opts a{color:var(--muted);text-decoration:none}.opts a:hover{color:var(--blue);text-decoration:underline}
.rem{display:flex;align-items:center;gap:8px;cursor:pointer}.rem input{position:absolute;opacity:0}
.rem i{width:16px;height:16px;border-radius:50%;border:1.5px solid var(--blue);display:inline-block;position:relative;transition:.15s}
.rem input:checked+i{background:var(--blue)}.rem input:checked+i::after{content:"";position:absolute;left:4px;top:1px;width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
.rem input:focus-visible+i{box-shadow:0 0 0 3px rgba(0,114,188,.3)}
.go{width:100%;height:50px;border:0;border-radius:999px;background:linear-gradient(90deg,var(--navy),var(--blue));color:#fff;font-weight:800;letter-spacing:.5px;cursor:pointer;transition:.2s}
.go:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 10px 24px rgba(0,114,188,.38)}.go:focus-visible{outline:3px solid var(--gold);outline-offset:2px}.go:disabled{opacity:.7;cursor:wait}
.err{background:#fee4e2;color:var(--red);padding:10px 14px;border-radius:12px;font-size:13px;margin-top:14px;text-align:center}
.foot{text-align:center;color:var(--muted);font-size:12px;margin:18px 0 0}
@keyframes drift{to{transform:translateX(calc(100vw + 400px))}}@keyframes sway{50%{transform:rotate(2.2deg)}}@keyframes bob{50%{transform:translateY(-8px)}}
@keyframes float{50%{transform:translateY(-8px)}}@keyframes pulse{50%{opacity:.45}}@keyframes rise{from{opacity:0;transform:translateY(24px)}}
@media(max-width:1000px){.person{opacity:.5;left:-6vw}}
@media(max-width:760px){.scene{justify-content:center;padding:20px 14px}.person,.t1,.t2{display:none}.card{padding:32px 24px 26px}}
@media(prefers-reduced-motion:reduce){*{animation:none!important}}
</style></head><body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
  <symbol id="tree" viewBox="0 0 60 170"><path d="M30 4C54 32 56 78 30 128 4 78 6 32 30 4Z" fill="#2fb3d1"/><path d="M30 4C18 30 14 70 30 128 8 78 8 36 30 4Z" fill="#1d95bd" opacity=".55"/><path d="M30 14V168" stroke="#177aa0" stroke-width="3" stroke-linecap="round"/><path d="M30 60L18 46M30 84L16 68M30 60L42 46M30 84L44 68M30 108L20 96M30 108L40 96" stroke="#177aa0" stroke-width="2" stroke-linecap="round" fill="none"/></symbol>
  <symbol id="leaves" viewBox="0 0 150 170"><path d="M20 168C-6 100 14 36 68 12 98 54 92 118 52 160Z" fill="#2fb3d1"/><path d="M56 168C48 108 80 52 140 40 150 96 116 150 72 166Z" fill="#1d95bd"/><path d="M36 164C30 110 44 62 66 24M70 162C78 112 100 80 132 52" stroke="#177aa0" stroke-width="3" fill="none" stroke-linecap="round"/></symbol>
</defs></svg>

<div class="scene">
  <div class="cloud c1"></div><div class="cloud c2"></div><div class="cloud c3"></div>
  <svg class="hills" viewBox="0 0 1440 320" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0 150C240 70 480 130 720 108 960 86 1200 46 1440 116V320H0Z" fill="#8fc3c4"/>
    <path d="M0 214C300 154 520 232 800 192 1080 152 1260 204 1440 174V320H0Z" fill="#c9e3f4"/>
    <path d="M0 262C360 232 700 272 1040 247 1240 232 1360 242 1440 237V320H0Z" fill="#f2f4f8"/>
  </svg>
  <svg class="deco t1" viewBox="0 0 60 170" aria-hidden="true"><use href="#tree"/></svg>
  <svg class="deco t2" viewBox="0 0 60 170" aria-hidden="true"><use href="#tree"/></svg>
  <svg class="deco t3" viewBox="0 0 60 170" aria-hidden="true"><use href="#tree"/></svg>
  <svg class="deco t4" viewBox="0 0 150 170" aria-hidden="true"><use href="#leaves"/></svg>
  <svg class="deco t5" viewBox="0 0 60 170" aria-hidden="true"><use href="#tree"/></svg>

  <svg class="person" viewBox="0 0 360 640" aria-hidden="true">
    <defs>
      <linearGradient id="gTop" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2a93d8"/><stop offset="1" stop-color="#0072bc"/></linearGradient>
      <linearGradient id="gPants" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2a4a6e"/><stop offset="1" stop-color="#10233b"/></linearGradient>
      <linearGradient id="gLap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#b9d9f2"/><stop offset="1" stop-color="#8fbfe6"/></linearGradient>
    </defs>
    <ellipse cx="190" cy="626" rx="130" ry="12" fill="#8fc3e8" opacity=".6"/>
    <g class="bub"><rect x="22" y="58" width="122" height="64" rx="14" fill="#fff" stroke="#9ac7ea" stroke-width="3"/><path d="M112 122L142 146 134 121Z" fill="#fff" stroke="#9ac7ea" stroke-width="3" stroke-linejoin="round"/><rect x="40" y="76" width="70" height="9" rx="4.5" fill="#bfe3f5"/><rect x="40" y="94" width="46" height="9" rx="4.5" fill="#f2a900"/></g>
    <path d="M128 300H262L292 604H214L196 372H182L160 604H84Z" fill="url(#gPants)"/>
    <path d="M78 604H164Q172 624 156 630H80Q70 616 78 604Z" fill="#10233b"/><path d="M208 604H296Q306 624 290 630H212Q200 616 208 604Z" fill="#10233b"/>
    <path d="M138 172Q192 148 252 172L270 314H124Z" fill="url(#gTop)"/>
    <path d="M144 180Q104 202 94 264L113 272Q124 222 158 204Z" fill="#2a93d8"/>
    <path d="M100 264Q116 302 146 324L157 308Q130 290 118 258Z" fill="#2a93d8"/>
    <path d="M248 178Q292 200 296 264L277 270Q272 226 242 204Z" fill="#0072bc"/>
    <path d="M296 264Q302 298 262 308L258 291Q280 286 277 266Z" fill="#0072bc"/>
    <ellipse cx="256" cy="302" rx="11" ry="9" fill="#f2b8a2"/>
    <rect x="180" y="132" width="22" height="30" rx="9" fill="#e9a58d"/>
    <ellipse cx="191" cy="112" rx="29" ry="32" fill="#f2b8a2"/>
    <path d="M160 110Q156 72 192 74 228 74 222 110 214 88 192 90 172 90 160 110Z" fill="#1c2b4a"/>
    <circle cx="214" cy="68" r="15" fill="#1c2b4a"/>
    <path d="M163 114Q148 150 168 176 162 142 171 118Z" fill="#1c2b4a"/>
    <path d="M20 252L120 236 138 324 34 340Z" fill="url(#gLap)" stroke="#0072bc" stroke-width="4" stroke-linejoin="round"/>
    <g class="glow"><circle cx="80" cy="290" r="11" fill="#f2a900"/><path d="M75 291L79 295 86 285" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round"/></g>
    <path d="M34 340L138 324 156 334 46 352Z" fill="#10233b"/>
    <ellipse cx="150" cy="326" rx="12" ry="9" fill="#f2b8a2"/>
  </svg>

  <form class="card" id="f" autocomplete="on">
    <img class="logo" src="assets/img/logo.png" alt="Midlands State University crest">
    <h1>Welcome Back</h1>
    <p class="sub">Midlands State University<br>Dropout Prediction System</p>
    <div class="field"><label class="sr" for="email">Email address</label>
      <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
      <input id="email" type="email" placeholder="Email address (you@staff.msu.ac.zw)" required autofocus></div>
    <div class="field"><label class="sr" for="pw">Password</label>
      <svg viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
      <input id="pw" type="password" placeholder="Password" required>
      <button type="button" class="eye" aria-label="Show password" title="Show / hide password"></button></div>
    <div class="opts"><label class="rem"><input type="checkbox" id="rem"><i></i> Remember me</label>
      <a href="#" onclick="alert('Please contact the system administrator to reset your password.');return false">Forgot password?</a></div>
    <button class="go" id="go">LOGIN</button>
    <div class="err hidden" id="err" role="alert"></div>
    <p class="foot">Accounts are created by the system administrator.</p>
  </form>
</div>
<script>
const $=s=>document.querySelector(s), f=$('#f'), err=$('#err');
if(/[?&]expired/.test(location.search)){err.textContent='You were signed out after an hour of inactivity. Please sign in again.';err.classList.remove('hidden')}
window.addEventListener('pageshow',e=>{if(e.persisted)location.reload()});
$('#email').value=localStorage.getItem('dps_email')||''; $('#rem').checked=!!$('#email').value;
document.querySelectorAll('.eye').forEach(b=>b.addEventListener('click',()=>{const i=b.previousElementSibling,show=i.type==='password';i.type=show?'text':'password';b.classList.toggle('on',show);b.setAttribute('aria-label',show?'Hide password':'Show password')}));
f.addEventListener('submit',async e=>{e.preventDefault();err.classList.add('hidden');$('#go').disabled=true;$('#go').textContent='SIGNING IN…';
  try{const r=await fetch('api.php?a=login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:$('#email').value,password:$('#pw').value})});
    const j=await r.json(); if(!r.ok) throw new Error(j.error||'Sign in failed');
    $('#rem').checked?localStorage.setItem('dps_email',$('#email').value):localStorage.removeItem('dps_email'); location='app.php';
  }catch(x){err.textContent=x.message;err.classList.remove('hidden');$('#go').disabled=false;$('#go').textContent='LOGIN'}});
</script></body></html>
