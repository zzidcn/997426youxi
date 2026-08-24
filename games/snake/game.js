/**
 * 贪吃蛇 — 997426 示例游戏 1
 * 键盘(方向键/WASD) + 触屏滑动，画布自适应。
 * 通过 Game997426 SDK 上报成绩到统一排行榜。
 */
(function () {
  'use strict';

  var cv = document.getElementById('cv');
  var ctx = cv.getContext('2d');
  var GRID = 20;                 // 20x20 格
  var overlay = document.getElementById('overlay');
  var startBtn = document.getElementById('startBtn');
  var scoreEl = document.getElementById('score');
  var bestEl = document.getElementById('best');

  var cell, snake, dir, nextDir, food, score, running, timer, speed;

  function resize() {
    var size = cv.clientWidth;
    cv.width = size * devicePixelRatio;
    cv.height = size * devicePixelRatio;
    cell = cv.width / GRID;
    draw();
  }
  window.addEventListener('resize', resize);

  function reset() {
    snake = [{ x: 10, y: 10 }, { x: 9, y: 10 }, { x: 8, y: 10 }];
    dir = { x: 1, y: 0 };
    nextDir = dir;
    food = spawnFood();
    score = 0;
    speed = 140; // ms/步
    updateHud();
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

    // 撞墙或撞自己 → 结束
    if (head.x < 0 || head.y < 0 || head.x >= GRID || head.y >= GRID ||
        snake.some(function (s) { return s.x === head.x && s.y === head.y; })) {
      return gameOver();
    }

    snake.unshift(head);
    if (head.x === food.x && head.y === food.y) {
      score += 10;
      food = spawnFood();
      speed = Math.max(60, speed - 3); // 加速
      clearInterval(timer);
      timer = setInterval(step, speed);
      updateHud();
    } else {
      snake.pop();
    }
    draw();
  }

  function draw() {
    if (!snake) return;
    var w = cv.width;
    ctx.clearRect(0, 0, w, w);
    // 网格底纹
    ctx.fillStyle = 'rgba(255,255,255,.03)';
    for (var i = 0; i < GRID; i++) {
      for (var j = 0; j < GRID; j++) {
        if ((i + j) % 2 === 0) ctx.fillRect(i * cell, j * cell, cell, cell);
      }
    }
    // 食物
    ctx.fillStyle = '#ff5c7a';
    ctx.beginPath();
    ctx.arc((food.x + .5) * cell, (food.y + .5) * cell, cell * .38, 0, Math.PI * 2);
    ctx.fill();
    // 蛇
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
    scoreEl.textContent = score;
    bestEl.textContent = Math.max(score, parseInt(bestEl.textContent, 10) || 0);
  }

  function start() {
    reset();
    overlay.classList.add('hidden');
    running = true;
    timer = setInterval(step, speed);
  }

  async function gameOver() {
    running = false;
    clearInterval(timer);
    draw();

    var resultText = '';
    try {
      var sdk = await Game997426.ready();
      var res = await sdk.submitScore(score);
      if (res && res.ok) {
        resultText = '排名 #' + res.rank +
          (res.points_awarded ? ' · +' + res.points_awarded + ' 积分 💎' : '');
      }
    } catch (e) { /* 平台外运行（本地调试）时忽略 */ }

    document.querySelector('#overlay h1').textContent = '游戏结束 · ' + score + ' 分';
    document.querySelector('#overlay p').innerHTML = resultText ? resultText + '<br>电脑：方向键 / WASD<br>手机：滑动屏幕控制方向' : '电脑：方向键 / WASD<br>手机：滑动屏幕控制方向';
    startBtn.textContent = '再来一局';
    overlay.classList.remove('hidden');
  }

  // ── 输入：键盘 ─────────────────────────
  var KEYMAP = {
    ArrowUp: { x: 0, y: -1 }, KeyW: { x: 0, y: -1 },
    ArrowDown: { x: 0, y: 1 }, KeyS: { x: 0, y: 1 },
    ArrowLeft: { x: -1, y: 0 }, KeyA: { x: -1, y: 0 },
    ArrowRight: { x: 1, y: 0 }, KeyD: { x: 1, y: 0 },
  };
  document.addEventListener('keydown', function (e) {
    var d = KEYMAP[e.code];
    if (d) {
      e.preventDefault();
      setDir(d);
    }
    if (e.code === 'Space' && !running) start();
  });

  function setDir(d) {
    // 禁止 180° 掉头
    if (running && d.x !== -dir.x && d.y !== -dir.y) nextDir = d;
  }

  // ── 输入：触屏滑动 ─────────────────────
  var touchStart = null;
  document.addEventListener('touchstart', function (e) {
    touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  }, { passive: true });
  document.addEventListener('touchmove', function (e) {
    e.preventDefault(); // 阻止页面滚动
    if (!touchStart || !running) return;
    var dx = e.touches[0].clientX - touchStart.x;
    var dy = e.touches[0].clientY - touchStart.y;
    if (Math.abs(dx) < 24 && Math.abs(dy) < 24) return;
    setDir(Math.abs(dx) > Math.abs(dy)
      ? { x: dx > 0 ? 1 : -1, y: 0 }
      : { x: 0, y: dy > 0 ? 1 : -1 });
    touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  }, { passive: false });

  startBtn.addEventListener('click', start);
  resize();
  reset();
})();
