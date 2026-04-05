<?php
// On démarre la session pour pouvoir compter le nombre de messages (Quota public)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "header.php"; 
?>

<!-- Three.js local (On garde le nonce pour la sécurité CSP) -->
<script src="/assets/js/three.min.js" nonce="<?= $nonce ?? '' ?>"></script>
<script src="/assets/js/GLTFLoader.js" nonce="<?= $nonce ?? '' ?>"></script>

<style nonce="<?= $nonce ?? '' ?>">
    body { background-color: #0f172a; font-family: 'Inter', sans-serif; }

    .ai-layout {
        display: flex; flex-direction: column;
        height: calc(100vh - 80px);
        max-width: 1400px; margin: 0 auto;
        padding: 24px; gap: 24px;
    }
    @media (min-width: 1024px) {
        .ai-layout { flex-direction: row; height: calc(100vh - 100px); padding: 32px; }
    }

    .avatar-panel {
        flex: 1;
        background: linear-gradient(145deg, #1e293b, #0f172a);
        border-radius: 2rem; border: 1px solid rgba(255,255,255,0.05);
        position: relative; overflow: hidden;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        box-shadow: 0 20px 50px -10px rgba(0,0,0,0.5);
        min-height: 400px;
    }
    #avatar-canvas { width: 100%; height: 100%; display: block; cursor: grab; }
    #avatar-canvas:active { cursor: grabbing; }

    .status-pill {
        position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%);
        display: flex; align-items: center; gap: 8px;
        padding: 8px 20px;
        background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.1); border-radius: 99px;
        font-size: 12px; font-weight: 600; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;
    }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #64748b; transition: all 0.3s; }
    .status-pill.listening .status-dot { background: #ef4444; box-shadow: 0 0 10px #ef4444; animation: pulse 1.5s infinite; }
    .status-pill.thinking  .status-dot { background: #6366f1; box-shadow: 0 0 10px #6366f1; animation: pulse 1.5s infinite; }
    .status-pill.speaking  .status-dot { background: #10b981; box-shadow: 0 0 10px #10b981; }

    .chat-panel {
        flex: 1; display: flex; flex-direction: column;
        background: #1e293b; border-radius: 2rem;
        border: 1px solid rgba(255,255,255,0.05); overflow: hidden;
    }
    .chat-area {
        flex: 1; padding: 32px; overflow-y: auto;
        display: flex; flex-direction: column; gap: 24px;
    }
    .message-bubble {
        max-width: 85%; padding: 16px 20px; border-radius: 20px;
        font-size: 15px; line-height: 1.6; animation: fadeIn 0.3s ease-out;
    }
    .message-bubble.bot {
        align-self: flex-start; background: #0f172a;
        border: 1px solid rgba(255,255,255,0.05);
        color: #e2e8f0; border-bottom-left-radius: 4px;
    }
    .message-bubble.user {
        align-self: flex-end; background: #4f46e5; color: white;
        border-bottom-right-radius: 4px;
        box-shadow: 0 4px 15px rgba(79,70,229,0.3);
    }
    .controls-area {
        padding: 20px 32px 32px; background: #172033;
        border-top: 1px solid rgba(255,255,255,0.03);
    }
    .input-group {
        display: flex; gap: 12px; background: #0f172a;
        padding: 8px; border-radius: 16px;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .chat-input {
        flex: 1; background: transparent; border: none;
        padding: 12px 16px; color: white; font-size: 14px; outline: none;
    }
    .chat-input::placeholder { color: #475569; }
    .btn-icon {
        width: 44px; height: 44px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all 0.2s; border: none;
    }
    .btn-mic { background: transparent; color: #64748b; }
    .btn-mic:hover { color: white; background: rgba(255,255,255,0.05); }
    .btn-mic.active { color: #ef4444; background: rgba(239,68,68,0.1); }
    .btn-send { background: #6366f1; color: white; box-shadow: 0 4px 15px rgba(99,102,241,0.3); }
    .btn-send:hover { background: #4f46e5; transform: translateY(-1px); }

    .typing-dots { display: flex; gap: 4px; }
    .typing-dots span { width: 6px; height: 6px; background: #6366f1; border-radius: 50%; animation: bounce 1.4s infinite ease-in-out both; }
    .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
    .typing-dots span:nth-child(2) { animation-delay: -0.16s; }
    .suggestion-chip {
        cursor: pointer; padding: 6px 12px;
        background: rgba(255,255,255,0.05); border-radius: 99px;
        font-size: 12px; color: #94a3b8; transition: all .2s;
    }
    .suggestion-chip:hover { background: rgba(99,102,241,0.15); color: #a5b4fc; }

    @keyframes fadeIn  { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    @keyframes bounce  { 0%,80%,100% { transform:scale(0); } 40% { transform:scale(1.0); } }
    @keyframes pulse   { 0%,100% { opacity:1; } 50% { opacity:0.5; } }
    @keyframes spin    { to { transform: rotate(360deg); } }
</style>

<div class="ai-layout">

    <div class="avatar-panel">
        <canvas id="avatar-canvas"></canvas>
        <div id="loader" style="position:absolute;color:white;text-align:center;">
            <div style="width:40px;height:40px;border:3px solid rgba(255,255,255,0.1);border-top-color:#6366f1;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 12px;"></div>
            Chargement de l'avatar…
        </div>
        <div class="status-pill" id="statusPill">
            <div class="status-dot"></div>
            <span id="statusText">En attente</span>
        </div>
    </div>

    <div class="chat-panel">
        <div class="chat-area" id="chatHistory">
            <div class="message-bubble bot">
                Bonjour ! Je suis l'assistant PsySpace. Comment puis-je vous aider ?
            </div>
        </div>
        <div class="controls-area">
            <div class="input-group">
                <button id="micBtn" class="btn-icon btn-mic">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                </button>
                <input type="text" id="chatInput" class="chat-input" placeholder="Posez votre question ici...">
                <button id="sendBtn" class="btn-icon btn-send">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                <span class="suggestion-chip" data-ask="C'est quoi PsySpace ?">C'est quoi PsySpace ?</span>
                <span class="suggestion-chip" data-ask="Comment s'inscrire ?">Comment s'inscrire ?</span>
                <span class="suggestion-chip" data-ask="Sécurité des données">Sécurité des données</span>
                <span class="suggestion-chip" data-ask="Qui a développé PsySpace ?">Le développeur</span>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $nonce ?? '' ?>">
/* ═══════════════════════════════════════════════════════
   STATE
═══════════════════════════════════════════════════════ */
var isThinking=false, isListening=false, isSpeaking=false;
var recognition=null, currentAudio=null;
var scene, camera, renderer, mixer, clock, avatarRoot;
var headMesh=null, teethMesh=null, tongueMesh=null;
var lipIv=null, blinkIv=null, vIdx=0;

var M = {
    mouthOpen:0,  viseme_sil:1,
    viseme_PP:2,  viseme_FF:3,  viseme_TH:4,
    viseme_DD:56, viseme_kk:57, viseme_CH:58,
    viseme_SS:59, viseme_nn:60, viseme_RR:61,
    viseme_aa:62, viseme_E:63,  viseme_I:64,
    viseme_O:65,  viseme_U:66,
    jawOpen:49, eyeBlinkL:18, eyeBlinkR:19, mouthSmile:47
};

var VISEMES = [
    {k:'viseme_aa',w:.85},{k:'viseme_O',w:.75},{k:'viseme_E',w:.65},
    {k:'viseme_PP',w:.55},{k:'viseme_SS',w:.65},{k:'viseme_DD',w:.55},
    {k:'viseme_I', w:.65},{k:'viseme_kk',w:.55},{k:'viseme_CH',w:.65},
    {k:'viseme_U', w:.75},{k:'mouthOpen',w:.65},{k:'viseme_nn',w:.45}
];

/* ═══════════════════════════════════════════════════════
   UI HELPERS
═══════════════════════════════════════════════════════ */
var chatHistory = document.getElementById('chatHistory');
var chatInput   = document.getElementById('chatInput');
var statusPill  = document.getElementById('statusPill');
var statusText  = document.getElementById('statusText');
var micBtn      = document.getElementById('micBtn');
var sendBtn     = document.getElementById('sendBtn');
var loaderDiv   = document.getElementById('loader');

function setStatus(s,t){ statusPill.className='status-pill '+(s||''); statusText.textContent=t||'En attente'; }

function addMessage(text,type){
    var d=document.createElement('div');
    d.className='message-bubble '+type;
    d.textContent=text; // Protection XSS native
    chatHistory.appendChild(d);
    chatHistory.scrollTop=chatHistory.scrollHeight;
}
function showTyping(){
    var d=document.createElement('div');
    d.className='message-bubble bot'; d.id='typingBubble';
    d.innerHTML='<div class="typing-dots"><span></span><span></span><span></span></div>';
    chatHistory.appendChild(d);
    chatHistory.scrollTop=chatHistory.scrollHeight;
}
function hideTyping(){ var t=document.getElementById('typingBubble'); if(t)t.remove(); }

/* ═══════════════════════════════════════════════════════
   MORPH / LIP SYNC
══════════��════════════════════════════════════════════ */
function setM(mesh,idx,val){
    if(!mesh||!mesh.morphTargetInfluences) return;
    if(idx>=0 && idx<mesh.morphTargetInfluences.length)
        mesh.morphTargetInfluences[idx]=Math.max(0,Math.min(1,val));
}
function resetMorphs(){
    [headMesh,teethMesh,tongueMesh].forEach(function(m){
        if(m&&m.morphTargetInfluences)
            for(var i=0;i<m.morphTargetInfluences.length;i++)
                m.morphTargetInfluences[i]=0;
    });
    if(headMesh) setM(headMesh,M.mouthSmile,0.12);
}
function startLipSync(){
    clearInterval(lipIv); vIdx=0;
    lipIv=setInterval(function(){
        if(!isSpeaking){ clearInterval(lipIv); resetMorphs(); return; }
        resetMorphs();
        var v=VISEMES[vIdx%VISEMES.length];
        var w=v.w*(0.7+Math.random()*0.3);
        setM(headMesh, M[v.k], w);
        setM(headMesh, M.jawOpen, w*0.55);
        if(teethMesh) setM(teethMesh, M[v.k]||0, w*0.8);
        if(tongueMesh) setM(tongueMesh, 0, w*0.3);
        vIdx++;
    },110);
}
function stopLipSync(){ clearInterval(lipIv); resetMorphs(); }

function startBlink(){
    function doBlink(){
        if(!headMesh){ blinkIv=setTimeout(doBlink,4000); return; }
        setM(headMesh,M.eyeBlinkL,1); setM(headMesh,M.eyeBlinkR,1);
        setTimeout(function(){
            setM(headMesh,M.eyeBlinkL,0); setM(headMesh,M.eyeBlinkR,0);
            blinkIv=setTimeout(doBlink,3000+Math.random()*3000);
        },130);
    }
    blinkIv=setTimeout(doBlink,2000);
}

/* ═══════════════════════════════════════════════════════
   THREE.JS
═══════════════════════════════════════════════════════ */
function initThree(){
    if(typeof THREE === 'undefined'){
        loaderDiv.innerHTML='<span style="color:#f87171;">Three.js non chargé.</span>';
        return;
    }
    if(typeof THREE.GLTFLoader === 'undefined'){
        loaderDiv.innerHTML='<span style="color:#f87171;">GLTFLoader non chargé.</span>';
        return;
    }

    var canvas=document.getElementById('avatar-canvas');
    var W=canvas.clientWidth||400;
    var H=canvas.clientHeight||500;

    scene=new THREE.Scene();
    scene.background=new THREE.Color(0x1e293b);

    camera=new THREE.PerspectiveCamera(35,W/H,0.1,100);
    camera.position.set(0,1.6,2.0);
    camera.lookAt(0,1.55,0);

    renderer=new THREE.WebGLRenderer({canvas:canvas,antialias:true});
    renderer.setSize(W,H);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio,2));
    renderer.outputEncoding=THREE.sRGBEncoding;
    renderer.toneMapping=THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure=1.2;

    scene.add(new THREE.AmbientLight(0xffffff,0.8));
    var key=new THREE.DirectionalLight(0xffffff,1.2); key.position.set(1.5,3,2); scene.add(key);
    var fill=new THREE.DirectionalLight(0xffffff,0.6); fill.position.set(-2,1,1); scene.add(fill);
    var rim=new THREE.DirectionalLight(0x06b6d4,0.3); rim.position.set(0,2,-3); scene.add(rim);

    clock=new THREE.Clock();

    var loader=new THREE.GLTFLoader();
    loader.load('model.glb',
        function(gltf){
            avatarRoot=gltf.scene;
            var box=new THREE.Box3().setFromObject(avatarRoot);
            var size=box.getSize(new THREE.Vector3());
            avatarRoot.position.sub(box.getCenter(new THREE.Vector3()));
            avatarRoot.position.y+=size.y*0.5;
            scene.add(avatarRoot);
            avatarRoot.traverse(function(o){
                if(o.isMesh && o.morphTargetInfluences){
                    if(o.name==='Head_Mesh')   headMesh=o;
                    if(o.name==='Teeth_Mesh')  teethMesh=o;
                    if(o.name==='Tongue_Mesh') tongueMesh=o;
                }
            });
            if(gltf.animations.length>0){
                mixer=new THREE.AnimationMixer(avatarRoot);
                mixer.clipAction(gltf.animations[0]).play();
            }
            loaderDiv.style.display='none';
            setTimeout(function(){ if(headMesh) setM(headMesh,M.mouthSmile,0.12); },600);
            startBlink();
        },
        function(xhr){
            var pct=xhr.total?Math.round(xhr.loaded/xhr.total*100):0;
            loaderDiv.innerHTML='<div style="width:40px;height:40px;border:3px solid rgba(255,255,255,0.1);border-top-color:#6366f1;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 12px;"></div>Chargement... '+pct+'%';
        },
        function(e){
            console.error('Erreur modèle:',e);
            loaderDiv.innerHTML='<span style="color:#f87171;">model.glb introuvable.</span>';
        }
    );
}

function animate(){
    requestAnimationFrame(animate);
    var d=clock?clock.getDelta():0.016;
    if(mixer) mixer.update(d);
    if(renderer&&scene&&camera) renderer.render(scene,camera);
}

/* ═══════════════════════════════════════════════════════
   AUDIO
═══════════════════════════════════════════════════════ */
var audioCtx=null, audioUnlocked=false;

function unlockAudio(){
    if(audioUnlocked) return;
    try{
        audioCtx=new (window.AudioContext||window.webkitAudioContext)();
        var buf=audioCtx.createBuffer(1,1,22050);
        var src=audioCtx.createBufferSource();
        src.buffer=buf; src.connect(audioCtx.destination); src.start(0);
        audioUnlocked=true;
    }catch(e){}
}

function playAudio(b64){
    if(currentAudio){ currentAudio.pause(); currentAudio=null; }
    stopLipSync(); isSpeaking=false;
    try{
        var bin=atob(b64);
        var buf=new Uint8Array(bin.length);
        for(var i=0;i<bin.length;i++) buf[i]=bin.charCodeAt(i);
        var url=URL.createObjectURL(new Blob([buf],{type:'audio/mpeg'}));
        var audio=new Audio(url);
        audio.volume=1.0;
        currentAudio=audio;
        audio.onerror=function(e){ console.error("Audio error:",e,audio.error); };
        audio.onended=function(){
            isSpeaking=false; stopLipSync();
            setStatus('','En attente');
            URL.revokeObjectURL(url);
        };
        function onPlay(){
            isSpeaking=true;
            setStatus('speaking','En train de répondre');
            startLipSync();
        }
        function showPlayBtn(){
            var old=document.getElementById('play-btn'); if(old)old.remove();
            var btn=document.createElement('button');
            btn.id='play-btn';
            btn.textContent='▶ Écouter la réponse';
            btn.style.cssText='display:block;margin-top:10px;padding:10px 20px;background:#6366f1;color:white;border:none;border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;font-family:inherit;width:100%;';
            btn.addEventListener('click', function(){
                btn.remove(); unlockAudio();
                audio.play().then(onPlay).catch(function(e){console.error(e);});
            });
            chatHistory.appendChild(btn);
            chatHistory.scrollTop=chatHistory.scrollHeight;
        }
        if(audioCtx&&audioCtx.state==='suspended'){
            audioCtx.resume().then(function(){
                audio.play().then(onPlay).catch(function(e){ console.error(e); showPlayBtn(); });
            });
        } else {
            audio.play().then(onPlay).catch(function(e){ console.error(e); showPlayBtn(); });
        }
    } catch(e){
        console.error("Erreur décodage audio base64:",e);
    }
}

/* ═══════════════════════════════════════════════════════
   CHAT
═══════════════════════════════════════════════════════ */
async function sendMessage(txt){
    var msg=txt||chatInput.value.trim();
    if(!msg||isThinking) return;
    addMessage(msg,'user');
    chatInput.value='';
    isThinking=true;
    setStatus('thinking','Analyse en cours');
    showTyping();
    try{
        var res=await fetch('chat_handler.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({message:msg})
        });
        var data=await res.json();
        hideTyping();
        addMessage(data.text,'bot');
        if(data.audio_base64 && data.audio_base64.length>50){
            playAudio(data.audio_base64);
        } else {
            setStatus('','En attente');
        }
    }catch(e){
        hideTyping();
        addMessage('Erreur de connexion avec le serveur IA.','bot');
        setStatus('','Erreur');
    } finally { isThinking=false; }
}

/* ═══════════════════════════════════════════════════════
   MIC
═══════════════════════════════════════════════════════ */
var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
function toggleMic(){
    if(!SR){ alert('Reconnaissance vocale non supportée. Veuillez utiliser Google Chrome ou Safari.'); return; }
    if(isListening){ recognition.stop(); return; }
    recognition=new SR();
    recognition.lang='fr-FR';
    recognition.onstart=function(){ isListening=true; micBtn.classList.add('active'); setStatus('listening','Je vous écoute'); };
    recognition.onresult=function(e){ sendMessage(e.results[0][0].transcript); };
    recognition.onend=function(){ isListening=false; micBtn.classList.remove('active'); if(!isThinking&&!isSpeaking) setStatus('','En attente'); };
    recognition.start();
}

/* ═════════════════════════════════���═════════════════════
   INIT
═══════════════════════════════════════════════════════ */
window.addEventListener('load', function(){
    initThree();
    animate();

    sendBtn.addEventListener('click', function(){ sendMessage(); });
    chatInput.addEventListener('keydown', function(e){ if(e.key==='Enter') sendMessage(); });
    micBtn.addEventListener('click', function(){ toggleMic(); });

    document.querySelectorAll('.suggestion-chip').forEach(function(chip){
        chip.addEventListener('click', function(){
            sendMessage(chip.getAttribute('data-ask'));
        });
    });

    document.addEventListener('click',    unlockAudio, {once:true});
    document.addEventListener('keydown',  unlockAudio, {once:true});
    document.addEventListener('touchend', unlockAudio, {once:true});

    var canvas=document.getElementById('avatar-canvas');
    var drag=false, prevX=0;
    canvas.addEventListener('mousedown',  function(e){ drag=true; prevX=e.clientX; });
    window.addEventListener('mouseup',    function(){ drag=false; });
    window.addEventListener('mousemove',  function(e){ if(drag&&avatarRoot){ avatarRoot.rotation.y+=(e.clientX-prevX)*0.01; } prevX=e.clientX; });
    canvas.addEventListener('touchstart', function(e){ drag=true; prevX=e.touches[0].clientX; },{passive:true});
    window.addEventListener('touchend',   function(){ drag=false; });
    window.addEventListener('touchmove',  function(e){ if(drag&&avatarRoot){ avatarRoot.rotation.y+=(e.touches[0].clientX-prevX)*0.01; } prevX=e.touches[0].clientX; },{passive:true});
});
</script>

<?php include "footer.php"; ?>