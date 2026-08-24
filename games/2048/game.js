/**
 * 2048 — 997426 示例游戏 2
 * 键盘(方向键/WASD) + 触屏滑动，DOM 渲染自适应。
 * 通过 Game997426 SDK 上报成绩到统一排行榜。
 */
(function () {
  'use strict';

  var gridEl = document.getElementById('grid');
  var overlay = document.getElementById('overlay');
  var startBtn = document.getElementById('startBtn');
  var scoreEl = document.getElementById('score');
  var bestEl = document.getElementById('best');

  var N = 4, board, score, running = false, best = 0;

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

  function rotate(b) { // 顺时针旋转
    return b[0].map(function (_, c) {
      return b.map(function (row) { return row[c]; }).reverse();
    });
  }

  function move(dirIdx) { // 0左 1上 2右 3下
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

  async function end() {
    running = false;
    var resultText = '';
    try {
      var sdk = await Game997426.ready();
      var res = await sdk.submitScore(score);
      if (res && res.ok) {
        resultText = '排名 #' + res.rank +
          (res.points_awarded ? ' · +' + res.points_awarded + ' 积分 💎' : '');
      }
    } catch (e) { /* 平台外运行忽略 */ }

    document.querySelector('#overlay h1').textContent = '游戏结束 · ' + score + ' 分';
    document.querySelector('#overlay p').innerHTML =
      (resultText ? resultText + '<br>' : '') + '电脑：方向键 / WASD<br>手机：滑动屏幕合并数字';
    startBtn.textContent = '再来一局';
    overlay.classList.remove('hidden');
  }

  function start() {
    board = emptyBoard();
    score = 0;
    addRandom();
    addRandom();
    render();
    overlay.classList.add('hidden');
    running = true;
  }

  // ── 键盘 ──────────────────────────────
  var KEYMAP = {
    ArrowLeft: 0, KeyA: 0, ArrowUp: 1, KeyW: 1,
    ArrowRight: 2, KeyD: 2, ArrowDown: 3, KeyS: 3,
  };
  document.addEventListener('keydown', function (e) {
    if (!running) return;
    var d = KEYMAP[e.code];
    if (d !== undefined) {
      e.preventDefault();
      move(d);
    }
  });

  // ── 触屏滑动 ───────────────────────────
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

  startBtn.addEventListener('click', start);
})();
