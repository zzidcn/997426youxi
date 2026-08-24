/**
 * 贪吃蛇 — 997426 标准示例游戏 v2
 * 四要素：①开始游戏 ②结束游戏(成绩上报) ③全屏按钮 ④游戏介绍
 * 键盘(方向键/WASD) + 触屏滑动，画布自适应。
 */
(function () {
  'use strict';

  var cv = document.getElementById('cv');
  var ctx = cv.getContext('2d');
  var GRID = 20;

  // ── DOM 引用 ──
  var startOverlay = document.getElementById('startOverlay');
  var overOverlay  = document.getElementById('overOverlay');
  var infoModal    = document.getElementById('infoModal');
  var startBtn = document.getElementById('startBtn');
  var retryBtn = document.getElementById('retryBtn');
  var infoBtn  = document.getElementById('infoBtn');
  var infoClose= document.getElementById('infoClose');
  var fsBtn    = document.getElementById('fsBtn');
  var quitBtn  = document.getElementById('quitBtn');
  var scoreEl  = document.getElementById('score');
  var bestEl   = document.getElementById('best');
  var finalScoreEl = document.getElementById('finalScore');
  var scoreResultEl= document.getElementById('scoreResult');

  var cell, snake, dir, nextDir, food, score, best = 0, running = false, timer, speed;

  // ── ③ 全屏按钮 ──
  fsBtn.addEventListener('click', function () {
    var stage = document.getElementById('wrap');
    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
      (stage.requestFullscreen || stage.webkitRequestFullscreen).call(stage);
    } else {
      (document.exitFullscreen || document.webkitExitFullscreen).call(document);
    }
  });

  function syncFsIcon() {
    var fs = !!(document.fullscreenElement || document.webkitFullscreenElement);
    fsBtn.textContent = fs ? '🗗' : '⛶';
  }
  document.addEventListener('fullscreenchange', syncFsIcon);
  document.addEventListener('webkitfullscreenchange', syncFsIcon);

  // ── ④ 游戏介绍 ──
  infoBtn.addEventListener('click', function () {
    if (running) pause();
    infoModal.classList.remove('hidden');
  });
  infoClose.addEventListener('click', function () {
    infoModal.classList.add('hidden');
    resume();
  });
  // ESC 关闭介绍
  document.addEventListener('keydown', function (e) {
    if (e.code === 'Escape' && !infoModal.classList.contains('hidden')) {
      infoModal.classList.add('hidden');
      resume();
    }
  });

  // ── ② 结束游戏按钮：游戏中主动结束，按当前成绩结算上报 ──
  quitBtn.addEventListener('click', function () {
    if (running) gameOver();
  });

  // ── 暂停/恢复（看介绍时暂停） ──
  function pause() { if (running) { clearInterval(timer); timer = null; } }
  function resume() { if (running && !timer) timer = setInterval(step, speed); }

  // ── 游戏逻辑 ──
  function resize() {
    var size = cv.clientWidth;
    cv.width = size * devicePixelRatio;
    cv.height = size * devicePixelRatio;
    cell = cv.width / GRID;
    draw();
  }
  window.addEventListener('resize', resize);
  document.addEventListener('fullscreenchange', resize);

  function reset() {
    snake = [{ x: 10, y: 10 }, { x: 9, y: 10 }, { x: 8, y: 10 }];
    dir = { x: 1, y: 0 };
    nextDir = dir;
    food = spawnFood();
    score = 0;
    speed = 140;
    updateHud();
    draw();
  }

  function spawnFood() {
    var p;
    do {
      p = { x: (Math.random() * GRID) | 0, y: (Math.random() * GRID) | 0 };
    } while (snake && snake.some(function (s) { return s.x === p.x && s.y === p.y; }));
    return p;
  }

  function step() {
    dir = nextDir;
    var head = { x: snake[0].x + dir.x, y: snake[0].y + dir.y };

    if (head.x < 0 || head.y < 0 || head.x >= GRID || head.y >= GRID ||
        snake.some(function (s) { return s.x === head.x && s.y === head.y; })) {
      return gameOver();
    }

    snake.unshift(head);
    if (head.x === food.x && head.y === food.y) {
      score += 10;
      food = spawnFood();
      speed = Math.max(60, speed - 3);
      clearInterval(timer);
      timer = setInterval(step, speed);
      updateHud();
    } else {
      snake.pop();
    }
    draw();
  }

  function draw() {
    if (!snake || !cell) return;
    ctx.clearRect(0, 0, cv.width, cv.height);
    ctx.fillStyle = 'rgba(255,255,255,.03)';
    for (var i = 0; i < GRID; i++) {
      for (var j = 0; j < GRID; j++) {
        if ((i + j) % 2 === 0) ctx.fillRect(i * cell, j * cell, cell, cell);
      }
    }
    if (food) {
      ctx.fillStyle = '#ff5c7a';
      ctx.beginPath();
      ctx.arc((food.x + .5) * cell, (food.y + .5) * cell, cell * .38, 0, Math.PI * 2);
      ctx.fill();
    }
    snake.forEach(function (s, i) {
      ctx.fillStyle = i === 0 ? '#00d4ff' : '#7c5cff';
      var pad = i === 0 ? 1 : 2;
      roundRect(s.x * cell + pad, s.y * cell + pad, cell - pad * 2, cell - pad * 2, cell * .25);
      ctx.fill();
    });
  }

  function roundRect(x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function updateHud() {
    best = Math.max(best, score);
    scoreEl.textContent = score;
    bestEl.textContent = best;
  }

  // ── ① 开始游戏 ──
  function start() {
    reset();
    startOverlay.classList.add('hidden');
    overOverlay.classList.add('hidden');
    quitBtn.style.display = ''; // 游戏中显示结束按钮
    running = true;
    timer = setInterval(step, speed);
  }
  startBtn.addEventListener('click', start);
  retryBtn.addEventListener('click', start);

  // ── ② 结束游戏：展示成绩 + 上报排行榜 ──
  async function gameOver() {
    running = false;
    clearInterval(timer);
    timer = null;
    quitBtn.style.display = 'none'; // 结算界面隐藏结束按钮
    draw();

    finalScoreEl.textContent = score;
    scoreResultEl.textContent = '成绩上报中…';

    try {
      var sdk = await Game997426.ready();
      var res = await sdk.submitScore(score);
      if (res && res.ok) {
        var parts = [];
        parts.push(res.rank <= 100 ? '当前排名 #' + res.rank : '已计入排行');
        if (res.points_awarded) parts.push('+' + res.points_awarded + ' 积分 💎');
        scoreResultEl.textContent = parts.join(' · ');
      } else {
        scoreResultEl.textContent = '';
      }
    } catch (e) {
      // 平台外本地调试：静默跳过上报。
      scoreResultEl.textContent = '(离线模式，成绩未上报)';
    }

    overOverlay.classList.remove('hidden');
  }

  // ── 输入：键盘 ──
  var KEYMAP = {
    ArrowUp: { x: 0, y: -1 }, KeyW: { x: 0, y: -1 },
    ArrowDown: { x: 0, y: 1 }, KeyS: { x: 0, y: 1 },
    ArrowLeft: { x: -1, y: 0 }, KeyA: { x: -1, y: 0 },
    ArrowRight: { x: 1, y: 0 }, KeyD: { x: 1, y: 0 },
  };
  document.addEventListener('keydown', function (e) {
    if (e.code === 'Space') {
      e.preventDefault();
      if (!running && overOverlay.classList.contains('hidden')) start();
      return;
    }
    var d = KEYMAP[e.code];
    if (d) {
      e.preventDefault();
      setDir(d);
    }
  });

  function setDir(d) {
    if (running && d.x !== -dir.x && d.y !== -dir.y) nextDir = d;
  }

  // ── 输入：触屏滑动 ──
  var touchStart = null;
  document.addEventListener('touchstart', function (e) {
    touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  }, { passive: true });
  document.addEventListener('touchmove', function (e) {
    e.preventDefault();
    if (!touchStart || !running) return;
    var dx = e.touches[0].clientX - touchStart.x;
    var dy = e.touches[0].clientY - touchStart.y;
    if (Math.abs(dx) < 24 && Math.abs(dy) < 24) return;
    setDir(Math.abs(dx) > Math.abs(dy)
      ? { x: dx > 0 ? 1 : -1, y: 0 }
      : { x: 0, y: dy > 0 ? 1 : -1 });
    touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  }, { passive: false });

  // ── 初始化 ──
  resize();
  reset();

  // ── 玩家身份显示：开始界面提示登录状态 ──
  (function showLoginStatus() {
    var el = document.getElementById('loginStatus');
    if (!el) return;
    function render() {
      if (!window.Game997426) {
        el.innerHTML = '⚠️ 无法连接平台（离线模式），成绩不会上报';
        return;
      }
      window.Game997426.getMe().then(function (me) {
        if (me && me.logged_in) {
          el.textContent = '👤 当前玩家：' + me.name + '　💎 ' + Number(me.points).toLocaleString() + ' 积分';
          el.style.color = '#7ee787';
        } else {
          el.innerHTML = '<a href="/wp-login.php" target="_top" style="color:#ffd166;">登录</a> 后成绩计入排行榜并获得积分（当前为游客模式）';
        }
      }).catch(function () {
        el.textContent = '(离线模式，成绩不会上报)';
      });
    }
    // SDK 兜底加载是异步的，多试几次。
    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      if (window.Game997426 || tries > 20) { clearInterval(timer); }
      if (window.Game997426 && window.Game997426._cfg) { clearInterval(timer); render(); }
    }, 300);
  })();
})();
