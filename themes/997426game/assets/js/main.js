/**
 * 997426 Game Platform — main.js
 * 全屏切换 / 排行榜动态刷新 / SDK 配置桥接（iframe 跨域场景）。
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // 1. 全屏按钮
    var fsBtn = document.getElementById('g99-fullscreen-btn');
    var player = document.getElementById('g99-player');
    if (fsBtn && player) {
      fsBtn.addEventListener('click', function () {
        if (!document.fullscreenElement) {
          (player.requestFullscreen || player.webkitRequestFullscreen).call(player);
        } else {
          document.exitFullscreen();
        }
      });
      document.addEventListener('fullscreenchange', function () {
        player.classList.toggle('g99-fullscreen', !!document.fullscreenElement);
        fsBtn.textContent = document.fullscreenElement ? '✕ 退出全屏' : '⛶ 全屏';
      });
    }

    // 2. 响应 iframe 内游戏的配置请求 + API 代理（v2 核心机制）
    window.addEventListener('message', function (e) {
      if (e.data && e.data.type === 'game997426:request-config' && window.Game997426Config) {
        e.source.postMessage({ type: 'game997426:config', config: window.Game997426Config }, '*');
      }

      // ── API 代理：父页面带登录态转发 REST 请求 ──
      if (e.data && e.data.type === 'game997426:proxy' && window.Game997426Config) {
        var cfg = window.Game997426Config;
        var action = e.data.action, payload = e.data.payload || {};
        var path, opts;
        if (action === 'me') {
          path = cfg.restUrl + '/me';
          opts = { method: 'GET', credentials: 'include' };
        } else if (action === 'leaderboard') {
          path = cfg.restUrl + '/leaderboard?game_id=' + encodeURIComponent(payload.game_id) +
            '&limit=' + encodeURIComponent(payload.limit || 10) +
            '&period=' + encodeURIComponent(payload.period || 'all');
          opts = { method: 'GET', credentials: 'include' };
        } else { // submit
          path = cfg.restUrl + '/score';
          opts = {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
            body: JSON.stringify(payload),
          };
        }
        fetch(path, opts)
          .then(function (r) { return r.json(); })
          .then(function (data) {
            e.source.postMessage({
              type: 'game997426:proxy-result',
              msgId: e.data.msgId,
              ok: !(data && data.code),       // WP 错误响应带 code 字段
              data: data,
              error: data && data.message ? data.message : '',
            }, '*');
          })
          .catch(function (err) {
            e.source.postMessage({
              type: 'game997426:proxy-result',
              msgId: e.data.msgId,
              ok: false,
              error: String(err),
            }, '*');
          });
        return;
      }

      if (e.data && e.data.type === 'game997426:score-result') {
        var r = e.data.result;
        if (r && r.ok) {
          var msg = '成绩已提交！排名 #' + r.rank +
            (r.points_awarded ? '，+' + r.points_awarded + ' 积分 💎' : '');
          toast(msg);
          refreshLeaderboard();
        }
      }
    });

    // 3. 轻量 toast
    function toast(text) {
      var el = document.createElement('div');
      el.className = 'g99-toast';
      el.textContent = text;
      Object.assign(el.style, {
        position: 'fixed', left: '50%', bottom: '32px', transform: 'translateX(-50%)',
        background: '#232953', color: '#fff', padding: '10px 20px',
        borderRadius: '999px', zIndex: 9999, boxShadow: '0 8px 24px rgba(0,0,0,.4)',
        fontSize: '.9rem',
      });
      document.body.appendChild(el);
      setTimeout(function () { el.remove(); }, 3200);
    }

    // 4. 无刷新刷新页面内排行榜短代码
    function refreshLeaderboard() {
      var lb = document.querySelector('.g99-leaderboard[data-game-id]');
      if (!lb || !window.Game997426Config) return;
      fetch(window.Game997426Config.restUrl + '/leaderboard?game_id=' + lb.dataset.gameId + '&limit=10&period=' + (lb.dataset.period || 'all'), { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.rows) return;
          renderRows(lb.querySelector('.g99-lb-list'), data.rows);
        });
    }

    function renderRows(list, rows) {
      if (!list) return;
      list.innerHTML = '';
      if (!rows.length) {
        list.innerHTML = '<li class="g99-lb-row g99-lb-empty">暂无成绩</li>';
        return;
      }
      rows.forEach(function (row, i) {
        var li = document.createElement('li');
        li.className = 'g99-lb-row' + (i < 3 ? ' g99-top' + (i + 1) : '');
        li.innerHTML =
          '<span class="g99-lb-rank">' + (i + 1) + '</span>' +
          '<span class="g99-lb-name"></span>' +
          '<span class="g99-lb-score">' + Number(row.score).toLocaleString() + '</span>';
        li.querySelector('.g99-lb-name').textContent = row.user_name || '游客';
        list.appendChild(li);
      });
    }
  });
})();
