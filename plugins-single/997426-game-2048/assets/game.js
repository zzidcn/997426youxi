/**
 * 2048 — 独立插件版（v2 架构，页面内直渲染）
 * 四要素：开始 / ⏹结束 / ⛶全屏 / ❓介绍
 * REST: game-2048/v1, X-WP-Nonce 认证
 */
(function () {
  'use strict';

  var root = document.querySelector('.g2048');
  if (!root) return;

  var N = 4;
  var gridEl = document.getElementById('g2048-grid');
  var $ = function (id) { return document.getElementById(id); };
  var startOv = $('g2048-start'), overOv = $('g2048-over'), modal = $('g2048-modal');
  var scoreEl = $('g2048-score'), bestEl = $('g2048-best'), meEl = $('g2048-me');
  var loginEl = $('g2048-login'), finalEl = $('g2048-final'), resultEl = $('g2048-result');
  var lbEl = $('g2048-lb'), quitBtn = $('g2048-quit'), fsBtn = $('g2048-fs');

  var CFG = window.Game2048Cfg || {};
  var board, score, best = 0, running = false, pausedByInfo = false;

  /* ── 身份 ── */
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
  document.addEventListener('fullscreenchange', function () {
    fsBtn.textContent = document.fullscreenElement ? '🗗' : '⛶';
  });

  /* ── 介绍（暂停） ── */
  $('g2048-info').addEventListener('click', function () {
    if (running) { running = false; pausedByInfo = true; }
    modal.classList.remove('g2048-hidden');
  });
  function closeInfo() {
    modal.classList.add('g2048-hidden');
    if (pausedByInfo) { running = true; pausedByInfo = false; }
  }
  $('g2048-close').addEventListener('click', closeInfo);
  document.addEventListener('keydown', function (e) {
    if (e.code === 'Escape' && !modal.classList.contains('g2048-hidden')) closeInfo();
  });

  /* ── 结束按钮 ── */
  quitBtn.addEventListener('click', function () { if (running) end(); });

  /* ── 核心逻辑 ── */
  function emptyBoard() {
    return Array.from({ length: N }, function () { return new Array(N).fill(0); });
  }
  function addRandom() {
    var empties = [];
    board.forEach(function (row, r) {
      row.forEach(function (v, c) { if (!v) empties.push([r, c]); });
    });
    if (!empties.length) return;
    var p = empties[(Math.random() * empties.length) | 0];
    board[p[0]][p[1]] = Math.random() < 0.9 ? 2 : 4;
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
        arr[i] *= 2; score += arr[i]; arr.splice(i + 1, 1);
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
  function move(dirIdx) {
    if (!running) return;
    var b = board.map(function (r) { return r.slice(); });
    for (var i = 0; i < dirIdx; i++) b = rotate(b);
    var moved = false;
    b = b.map(function (row) {
      var ns = slide(row);
      if (ns.some(function (v, i2) { return v !== row[i2]; })) moved = true;
      return ns;
    });
    for (var j = 0; j < (4 - dirIdx) % 4; j++) b = rotate(b);
    if (moved) {
      board = b; addRandom(); render();
      if (isDead()) end();
    }
  }
  function isDead() {
    for (var r = 0; r < N; r++) for (var c = 0; c < N; c++) {
      if (!board[r][c]) return false;
      if (c < N - 1 && board[r][c] === board[r][c + 1]) return false;
      if (r < N - 1 && board[r][c] === board[r + 1][c]) return false;
    }
    return true;
  }

  /* ── 结束 + 上报 ── */
  async function end() {
    running = false;
    quitBtn.style.display = 'none';
    finalEl.textContent = score;
    resultEl.textContent = '上报中…';
    overOv.classList.remove('g2048-hidden');

    try {
      const r = await fetch(CFG.restUrl + '/score', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce },
        credentials: 'same-origin',
        body: JSON.stringify({ score: score }),
      }).then(r => r.json());
      resultEl.textContent = r.ok ? '已计入排行榜 ✓' : (r.message || '上报失败');
      refreshLb();
    } catch (e) {
      resultEl.textContent = '网络错误，成绩未上报';
    }
  }

  /* ── 开始 ── */
  function start() {
    board = emptyBoard(); score = 0;
    addRandom(); addRandom(); render();
    startOv.classList.add('g2048-hidden');
    overOv.classList.add('g2048-hidden');
    quitBtn.style.display = '';
    running = true;
  }
  $('g2048-go').addEventListener('click', start);
  $('g2048-retry').addEventListener('click', start);

  /* ── 排行榜 ── */
  async function refreshLb() {
    try {
      const d = await fetch(CFG.restUrl + '/leaderboard').then(r => r.json());
      const rows = d.rows || [];
      lbEl.innerHTML = '';
      if (!rows.length) { lbEl.innerHTML = '<li class="g2048-lb-empty">暂无成绩，快来抢第一！</li>'; return; }
      rows.forEach(function (row, i) {
        const li = document.createElement('li');
        li.innerHTML = '<span class="g2048-lb-rank"></span><span class="g2048-lb-name"></span><span class="g2048-lb-score"></span>';
        li.children[0].textContent = i + 1;
        li.children[1].textContent = row.user_name || '游客';
        li.children[2].textContent = Number(row.score).toLocaleString();
        lbEl.appendChild(li);
      });
    } catch (e) { lbEl.innerHTML = '<li class="g2048-lb-empty">排行榜加载失败</li>'; }
  }

  /* ── 键盘 ── */
  var KEYMAP = { ArrowLeft:0,KeyA:0,ArrowUp:1,KeyW:1,ArrowRight:2,KeyD:2,ArrowDown:3,KeyS:3 };
  document.addEventListener('keydown', function (e) {
    if (e.code === 'Space') {
      e.preventDefault();
      if (!running && modal.classList.contains('g2048-hidden')) start();
      return;
    }
    var d = KEYMAP[e.code];
    if (d !== undefined) { e.preventDefault(); move(d); }
  });

  /* ── 触屏 ── */
  var ts = null;
  var stage = root.querySelector('.g2048-stage');
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

  /* ── 初始化 ── */
  refreshLb();

  // 身份显示
  if (CFG.userId) meEl.textContent = '👤 ' + CFG.name;
})();
