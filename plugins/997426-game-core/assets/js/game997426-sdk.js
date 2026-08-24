/**
 * 997426 游戏 SDK v2.0
 * ============================================================
 * 核心变化：所有 API 调用默认经由父页面代理（postMessage），
 * 由 WordPress 页面（带登录态）转发到 REST API。
 * 彻底解决 iframe 内 Cookie 丢失导致的"游客模式"问题。
 *
 * 游戏侧用法不变：
 *   const sdk = await Game997426.ready();
 *   await sdk.submitScore(12345);
 *   const me = await sdk.getMe();
 */
(function (global) {
  'use strict';

  var CONFIG = global.Game997426Config || null;
  var readyPromise = null;

  /** 父页面代理请求：postMessage 往返。 */
  function proxyCall(msgId, action, payload) {
    return new Promise(function (resolve, reject) {
      if (!global.parent || global.parent === global) {
        return reject(new Error('no-parent'));
      }
      function onMsg(e) {
        if (e.data && e.data.type === 'game997426:proxy-result' && e.data.msgId === msgId) {
          global.removeEventListener('message', onMsg);
          if (e.data.ok) resolve(e.data.data);
          else reject(new Error(e.data.error || 'proxy-error'));
        }
      }
      global.addEventListener('message', onMsg);
      global.parent.postMessage(
        { type: 'game997426:proxy', msgId: msgId, action: action, payload: payload },
        '*'
      );
      setTimeout(function () {
        global.removeEventListener('message', onMsg);
        reject(new Error('proxy-timeout'));
      }, 8000);
    });
  }

  /** 从父窗口请求基础配置（游戏 ID 等）。 */
  function fetchConfigFromParent() {
    return new Promise(function (resolve) {
      if (!global.parent || global.parent === global) return resolve(null);
      function onMsg(e) {
        if (e.data && e.data.type === 'game997426:config') {
          global.removeEventListener('message', onMsg);
          resolve(e.data.config);
        }
      }
      global.addEventListener('message', onMsg);
      try {
        global.parent.postMessage({ type: 'game997426:request-config' }, '*');
      } catch (err) { /* ignore */ }
      setTimeout(function () { resolve(null); }, 2000);
    });
  }

  function normalize(cfg) {
    cfg = cfg || {};
    return {
      restUrl: cfg.restUrl || '/wp-json/game997426/v1',
      nonce: cfg.nonce || '',
      userId: cfg.userId || 0,
    };
  }

  var SDK = {
    /** 返回 Promise<SDK>，可安全重复调用。 */
    init: function () {
      if (readyPromise) return readyPromise;
      readyPromise = (CONFIG ? Promise.resolve(normalize(CONFIG)) : fetchConfigFromParent().then(normalize))
        .then(function (cfg) {
          SDK._cfg = cfg;
          // 同域直连时预热一次 ready()，供身份显示等使用；
          // iframe 场景 getMe/submitScore 会自动走父页面代理。
          return SDK;
        });
      return readyPromise;
    },

    _callApi: function (action, payload) {
      // 首选父页面代理（登录态在父页面，最可靠）。
      return proxyCall('m' + Date.now() + Math.random().toString(36).slice(2, 6), action, payload)
        .catch(function (err) {
          // 无父页面（本地调试）或代理超时 → 直连 REST 兜底。
          if (err.message === 'no-parent') return SDK._direct(action, payload);
          throw err;
        });
    },

    /** 直连 REST（兜底）。 */
    _direct: function (action, payload) {
      var cfg = SDK._cfg || normalize(null);
      var path, body;
      if (action === 'me') {
        path = '/me';
      } else if (action === 'leaderboard') {
        path = '/leaderboard?game_id=' + encodeURIComponent(payload.game_id) +
          '&limit=' + encodeURIComponent(payload.limit || 10) +
          '&period=' + encodeURIComponent(payload.period || 'all');
      } else { // submit
        path = '/score';
        body = payload;
      }
      return fetch(cfg.restUrl + path, {
        method: body ? 'POST' : 'GET',
        headers: body ? { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce } : {},
        credentials: 'include',
        body: body ? JSON.stringify(body) : undefined,
      }).then(function (r) { return r.json(); });
    },

    /**
     * 上报成绩。
     * @returns {Promise<{ok,best,rank,points_awarded,total_points}>}
     */
    submitScore: function (score, opts) {
      opts = opts || {};
      var gameId = this._gameId || parseInt(new URLSearchParams(location.search).get('game_id'), 10) || 0;
      var payload = {
        game_id: gameId,
        score: Math.max(0, Math.round(Number(score) || 0)),
        nonce: (SDK._cfg && SDK._cfg.nonce) || '',
        extra: String(opts.extra || ''),
      };
      return SDK._callApi('submit', payload).then(function (res) {
        try {
          global.parent.postMessage({ type: 'game997426:score-result', result: res }, '*');
        } catch (e) { /* ignore */ }
        return res;
      });
    },

    /** 当前用户信息。 */
    getMe: function () {
      return SDK._callApi('me');
    },

    /** 排行榜。period: all|day|week|month */
    getLeaderboard: function (limit, period) {
      return SDK._callApi('leaderboard', {
        game_id: this._gameId || parseInt(new URLSearchParams(location.search).get('game_id'), 10) || 0,
        limit: limit || 10,
        period: period || 'all',
      });
    },

    _gameId: 0,
    setGameId: function (id) { this._gameId = parseInt(id, 10) || 0; },
  };

  if (global.Game997426GameId) SDK.setGameId(global.Game997426GameId);

  global.Game997426 = SDK;
  global.Game997426.ready = SDK.init.bind(SDK);

  // 响应父页面的配置请求。
  global.addEventListener('message', function (e) {
    if (e.data && e.data.type === 'game997426:request-config' && CONFIG) {
      e.source.postMessage({ type: 'game997426:config', config: CONFIG }, '*');
    }
  });
})(window);
