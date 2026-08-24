/**
 * 2048 — 997426 标准示例游戏 v2
 * 四要素：①开始游戏 ②结束游戏(成绩上报) ③全屏按钮 ④游戏介绍
 * 键盘(方向键/WASD) + 触屏滑动，DOM 自适应。
 */
(function () {
  'use strict';

  var gridEl = document.getElementById('grid');
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

  var N = 4, board, score, best = 0, running = false;

  // ── ③ 全屏按钮 ──
  fsBtn.addEventListener('click', function () {
    var wrap = document.getElementById('wrap');
    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
      (wrap.requestFullscreen || wrap.webkitRequestFullscreen).call(wrap);
    } else {
      (document.exitFullscreen || document.webkitExitFullscreen).call(document);
    }
  });
  function syncFsIcon() {
    fsBtn.textContent =
      (document.fullscreenElement || document.webkitFullscreenElement) ? '🗗' : '⛶';
  }
  document.addEventListener('fullscreenchange', syncFsIcon);
  document.addEventListener('webkitfullscreenchange', syncFsIcon);

  // ── ② 结束游戏按钮：主动结束并结算上报 ──
  quitBtn.addEventListener('click', function () {
    if (running) end();
  });

  // ── ④ 游戏介绍（打开时暂停） ──
  var pausedByInfo = false;
  infoBtn.addEventListener('click', function () {
    if (running) { running = false; pausedByInfo = true; }
    infoModal.classList.remove('hidden');
  });
  function closeInfo() {
    infoModal.classList.add('hidden');
    if (pausedByInfo) { running = true; pausedByInfo = false; }
  }
  infoClose.addEventListener('click', closeInfo);
  document.addEventListener('keydown', function (e) {
    if (e.code === 'Escape' && !infoModal.classList.contains('hidden')) closeInfo();
  });

  // ── 游戏逻辑 ──
  function emptyBoard() {
    return Array.from({ length: N }, function () { return new Array(N).fill(0); });
  }

  function addRandom() {
    var empties = [];
    board.forEach(function (row, r) {
      row.forEach(function (v, c) { if (!v) empties.push([r, c]); });
    });
    if (!empties.length) return;
    var pick = empties[(Math.random() * empties.length) | 0];
    board[pick[0]][pick[1]] = Math.random() < 0.9 ? 2 : 4;
  }

  function render() {
    gridEl.innerHTML = '';
    board.forEach(function (row) {
      row.forEach(function (v) {
        var d = document.createElement('div');
        d.className = 'tile' + (v ? ' t' + Math.min(v, 2048) : '');
        d.textContent = v || '';
        gridEl.appendChild(d);
      });
    });
    best = Math.max(best, score);
    scoreEl.textContent = score;
    bestEl.textContent = best;
  }

  function slide(row) {
    var arr = row.filter(function (v) { return v; });
    for (var i = 0; i < arr.length - 1; i++) {
      if (arr[i] === arr[i + 1]) {
        arr[i] *= 2;
        score += arr[i];
        arr.splice(i + 1, 1);
      }
    }
    while (arr.length < N) arr.push(0);
    return arr;
  }

  function rotate(b) {
    return b[0].map(function (_, c) {
      return b.map(function (row) { return row[c]; }).reverse();
    });
  }

  function move(dirIdx) { // 0左 1上 2右 3下
    if (!running) return;
    var b = board.map(function (r) { return r.slice(); });
    for (var i = 0; i < dirIdx; i++) b = rotate(b);
    var moved = false;
    b = b.map(function (row) {
      var ns = slide(row);
      if (ns.some(function (v, i) { return v !== row[i]; })) moved = true;
      return ns;
    });
    for (var j = 0; j < (4 - dirIdx) % 4; j++) b = rotate(b);
    if (moved) {
      board = b;
      addRandom();
      render();
      if (isDead()) end();
    }
  }

  function isDead() {
    for (var r = 0; r < N; r++) {
      for (var c = 0; c < N; c++) {
        if (!board[r][c]) return false;
        if (c < N - 1 && board[r][c] === board[r][c + 1]) return false;
        if (r < N - 1 && board[r][c] === board[r + 1][c]) return false;
      }
    }
    return true;
  }

  // ── ② 结束游戏：成绩上报 ──
  async function end() {
    running = false;
    quitBtn.style.display = 'none';
    finalScoreEl.textContent = score;
    scoreResultEl.textContent = '成绩上报中…';
    overOverlay.classList.remove('hidden');

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
      scoreResultEl.textContent = '(离线模式，成绩未上报)';
    }
  }

  // ── ① 开始游戏 ──
  function start() {
    board = emptyBoard();
    score = 0;
    addRandom();
    addRandom();
    render();
    startOverlay.classList.add('hidden');
    overOverlay.classList.add('hidden');
    quitBtn.style.display = '';
    running = true;
  }
  startBtn.addEventListener('click', start);
  retryBtn.addEventListener('click', start);

  // ── 键盘 ──
  var KEYMAP = {
    ArrowLeft: 0, KeyA: 0, ArrowUp: 1, KeyW: 1,
    ArrowRight: 2, KeyD: 2, ArrowDown: 3, KeyS: 3,
  };
  document.addEventListener('keydown', function (e) {
    if (e.code === 'Space') {
      e.preventDefault();
      if (!running && infoModal.classList.contains('hidden')) start();
      return;
    }
    var d = KEYMAP[e.code];
    if (d !== undefined) {
      e.preventDefault();
      move(d);
    }
  });

  // ── 触屏滑动 ──
  var ts = null;
  var stage = document.getElementById('stage');
  stage.addEventListener('touchstart', function (e) {
    ts = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  }, { passive: true });
  stage.addEventListener('touchend', function (e) {
    if (!ts || !running) return;
    var t = e.changedTouches[0];
    var dx = t.clientX - ts.x, dy = t.clientY - ts.y;
    if (Math.max(Math.abs(dx), Math.abs(dy)) < 24) return;
    move(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 2 : 0) : (dy > 0 ? 3 : 1));
    ts = null;
  }, { passive: true });

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
    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      if (window.Game997426 || tries > 20) { clearInterval(timer); }
      if (window.Game997426 && window.Game997426._cfg) { clearInterval(timer); render(); }
    }, 300);
  })();
})();
