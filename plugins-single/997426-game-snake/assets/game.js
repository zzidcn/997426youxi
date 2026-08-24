/**
 * 贪吃蛇 — 独立插件版
 * 直接运行在 WordPress 页面内（无 iframe）：
 *  - 登录态天然可用（wp_localize_script 注入 nonce + 用户）
 *  - REST 走本插件命名空间 game-snake/v1，X-WP-Nonce 认证
 * 四要素：开始 / 结束(⏹按钮+自然失败) / 全屏 / 介绍
 */
(function () {
  'use strict';

  var root = document.querySelector('.gsnake');
  if (!root) return;

  var GRID = parseInt(root.dataset.grid, 10) || 20;
  var cv = document.getElementById('gsnake-cv');
  var ctx = cv.getContext('2d');

  var $ = function (id) { return document.getElementById(id); };
  var startOv = $('gsnake-start'), overOv = $('gsnake-over'), modal = $('gsnake-modal');
  var scoreEl = $('gsnake-score'), bestEl = $('gsnake-best'), meEl = $('gsnake-me');
  var loginEl = $('gsnake-login'), finalEl = $('gsnake-final'), resultEl = $('gsnake-result');
  var lbEl = $('gsnake-lb');
  var quitBtn = $('gsnake-quit'), fsBtn = $('gsnake-fs'), sfxBtn = $('gsnake-sfx');

  /* ── 音效 ── */
  var SFX = window.GameSFX;
  sfxBtn.addEventListener('click', function () {
    var on = SFX ? SFX.toggle() : false;
    sfxBtn.textContent = on ? '🔊' : '🔇';
    if (on) SFX.play('click');
  });

  var CFG = window.GameSnakeCfg || {};
  var cell, snake, dir, nextDir, food, score, best = 0, running = false, timer, speed;

  /* ── 身份显示 ── */
  if (CFG.userId) {
    meEl.textContent = '👤 ' + CFG.name;
    loginEl.textContent = '';
  } else {
    loginEl.innerHTML = '<a href="/wp-login.php">登录</a> 后以昵称上榜（当前游客模式）';
  }

  /* ── 全屏 ── */
  fsBtn.addEventListener('click', function () {
    if (!document.fullscreenElement) (root.requestFullscreen || function(){}).call(root);
    else document.exitFullscreen();
  });
  function onFs() {
    fsBtn.textContent = document.fullscreenElement ? '🗗' : '⛶';
    setTimeout(resize, 60);
  }
  document.addEventListener('fullscreenchange', onFs);

  /* ── 介绍 ── */
  $('gsnake-info').addEventListener('click', function () { pause(); modal.classList.remove('gsnake-hidden'); });
  $('gsnake-close').addEventListener('click', function () { modal.classList.add('gsnake-hidden'); resume(); });

  /* ── 结束按钮 ── */
  quitBtn.addEventListener('click', function () { if (running) gameOver(); });

  /* ── 画布自适应 ── */
  function resize() {
    var size = cv.clientWidth || root.clientWidth;
    cv.width = size * devicePixelRatio;
    cv.height = size * devicePixelRatio;
    cell = cv.width / GRID;
    draw();
  }
  window.addEventListener('resize', resize);
  document.addEventListener('fullscreenchange', function(){ setTimeout(resize, 80); });
  document.addEventListener('webkitfullscreenchange', function(){ setTimeout(resize, 80); });
  window.addEventListener('orientationchange', function(){ setTimeout(resize, 200); });

  /* ── 游戏核心 ── */
  function spawnFood() {
    var p;
    do { p = { x: (Math.random()*GRID)|0, y: (Math.random()*GRID)|0 }; }
    while (snake.some(function(s){ return s.x===p.x && s.y===p.y; }));
    return p;
  }

  function reset() {
    snake = [{x:10,y:10},{x:9,y:10},{x:8,y:10}];
    dir = nextDir = {x:1,y:0};
    food = spawnFood();
    score = 0; speed = 140;
    hud();
  }

  function step() {
    dir = nextDir;
    var h = { x: snake[0].x + dir.x, y: snake[0].y + dir.y };
    if (h.x<0||h.y<0||h.x>=GRID||h.y>=GRID||snake.some(function(s){return s.x===h.x&&s.y===h.y;})) return gameOver();
    snake.unshift(h);
    if (h.x===food.x && h.y===food.y) {
      score += 10; food = spawnFood();
      speed = Math.max(60, speed-3);
      clearInterval(timer); timer = setInterval(step, speed);
      hud();
      if (window.GameSFX) window.GameSFX.play('eat', { pitch: 1 + Math.min(0.5, score/500) });
    } else snake.pop();
    draw();
  }

  function roundRect(x,y,w,h,r){ctx.beginPath();ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath();}

  function draw() {
    if (!snake || !cell) return;
    ctx.clearRect(0,0,cv.width,cv.height);
    ctx.fillStyle='rgba(255,255,255,.03)';
    for(var i=0;i<GRID;i++)for(var j=0;j<GRID;j++){if((i+j)%2===0)ctx.fillRect(i*cell,j*cell,cell,cell);}
    ctx.fillStyle='#ff5c7a';ctx.beginPath();ctx.arc((food.x+.5)*cell,(food.y+.5)*cell,cell*.38,0,6.29);ctx.fill();
    snake.forEach(function(s,i){
      ctx.fillStyle=i===0?'#00d4ff':'#7c5cff';
      var p=i===0?1:2;roundRect(s.x*cell+p,s.y*cell+p,cell-p*2,cell-p*2,cell*.25);ctx.fill();
    });
  }

  function hud(){ best=Math.max(best,score); scoreEl.textContent=score; bestEl.textContent=best; }
  function pause(){ if(running&&timer){clearInterval(timer);timer=null;} }
  function resume(){ if(running&&!timer) timer=setInterval(step,speed); }

  /* ── 开始 ── */
  function start(){
    reset();
    startOv.classList.add('gsnake-hidden');
    overOv.classList.add('gsnake-hidden');
    quitBtn.style.display='';
    running=true; timer=setInterval(step,speed);
    if (window.GameSFX) window.GameSFX.play('click');
  }
  $('gsnake-go').addEventListener('click',start);
  $('gsnake-retry').addEventListener('click',start);

  /* ── 结束 + 上报 ── */
  async function gameOver(){
    running=false; clearInterval(timer); timer=null;
    quitBtn.style.display='none';
    draw();
    if (window.GameSFX) window.GameSFX.play('over');
    finalEl.textContent=score;
    resultEl.textContent='上报中…';
    overOv.classList.remove('gsnake-hidden');

    try{
      const r = await fetch(CFG.restUrl+'/score',{
        method:'POST',
        headers:{'Content-Type':'application/json','X-WP-Nonce':CFG.nonce},
        credentials:'same-origin',
        body:JSON.stringify({score:score})
      }).then(r=>r.json());
      resultEl.textContent = r.ok ? '已计入排行榜 ✓' : (r.message||'上报失败');
      refreshLb();
    }catch(e){
      resultEl.textContent='网络错误，成绩未上报';
    }
  }

  /* ── 排行榜 ── */
  async function refreshLb(){
    try{
      const d = await fetch(CFG.restUrl+'/leaderboard').then(r=>r.json());
      const rows = d.rows||[];
      lbEl.innerHTML='';
      if(!rows.length){lbEl.innerHTML='<li class="gsnake-lb-empty">暂无成绩，快来抢第一！</li>';return;}
      rows.forEach(function(row,i){
        const li=document.createElement('li');
        li.innerHTML='<span class="gsnake-lb-rank"></span><span class="gsnake-lb-name"></span><span class="gsnake-lb-score"></span>';
        li.children[0].textContent=i+1;
        li.children[1].textContent=row.user_name||'游客';
        li.children[2].textContent=Number(row.score).toLocaleString();
        lbEl.appendChild(li);
      });
    }catch(e){ lbEl.innerHTML='<li class="gsnake-lb-empty">排行榜加载失败</li>'; }
  }

  /* ── 输入 ── */
  var KEYMAP={ArrowUp:{x:0,y:-1},KeyW:{x:0,y:-1},ArrowDown:{x:0,y:1},KeyS:{x:0,y:1},ArrowLeft:{x:-1,y:0},KeyA:{x:-1,y:0},ArrowRight:{x:1,y:0},KeyD:{x:1,y:0}};
  document.addEventListener('keydown',function(e){
    if(e.code==='Space'){e.preventDefault();if(!running&&!modal.classList.contains('gsnake-hidden')===false)start();return;}
    var d=KEYMAP[e.code];
    if(d){e.preventDefault();if(running&&d.x!==-dir.x&&d.y!==-dir.y)nextDir=d;}
  });
  var ts=null;
  document.addEventListener('touchstart',function(e){ts={x:e.touches[0].clientX,y:e.touches[0].clientY};},{passive:true});
  document.addEventListener('touchmove',function(e){
    e.preventDefault();
    if(!ts||!running)return;
    var dx=e.touches[0].clientX-ts.x,dy=e.touches[0].clientY-ts.y;
    if(Math.abs(dx)<24&&Math.abs(dy)<24)return;
    var nd=Math.abs(dx)>Math.abs(dy)?{x:dx>0?1:-1,y:0}:{x:0,y:dy>0?1:-1};
    if(nd.x!==-dir.x&&nd.y!==-dir.y)nextDir=nd;
    ts={x:e.touches[0].clientX,y:e.touches[0].clientY};
  },{passive:false});


  /* ── 虚拟方向键（触屏检测：多信号任一命中即显示） ── */
  var isTouch = ('ontouchstart' in window)
    || (navigator.maxTouchPoints > 0)
    || (window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
  if (isTouch) {
    var dpad = document.getElementById('gsnake-dpad');
    if (dpad) dpad.classList.add('gsnake-touch');
  }
  /* ── 虚拟方向键（触屏） ── */
  var DIRMAP = { up:{x:0,y:-1}, down:{x:0,y:1}, left:{x:-1,y:0}, right:{x:1,y:0} };
  root.querySelectorAll('.gsnake-dbtn[data-dir]').forEach(function (btn) {
    function go(e) {
      e.preventDefault();
      var d = DIRMAP[btn.dataset.dir];
      if (d && running && d.x !== -dir.x && d.y !== -dir.y) nextDir = d;
      if (!running) start();
    }
    btn.addEventListener('touchstart', go, { passive: false });
    btn.addEventListener('mousedown', go);
  });

  /* ── 初始化 ── */
  resize(); reset(); refreshLb();
})();
