/**
 * 997426 游戏 SDK v1.0
 * ============================================================
 * 所有接入 997426 小游戏平台的 HTML5 游戏通过本 SDK：
 *   1. 上报成绩到统一排行榜；
 *   2. 获取当前玩家信息 / 积分 / 徽章；
 *   3. 拉取排行榜数据自行渲染。
 *
 * 用法（在游戏 iframe 内）：
 *   const sdk = await Game997426.ready();          // 等待 SDK 初始化
 *   sdk.submitScore(12345);                        // 上报成绩
 *   const me = await sdk.getMe();                  // {logged_in, name, points, badges}
 *   const lb = await sdk.getLeaderboard(10,'all'); // {rows:[{user_name,score}]}
 *
 * 若游戏运行在跨域 iframe 中且拿不到 Game997426Config，
 * SDK 会自动降级为 postMessage 协议与父页面通信。
 */
(function (global) {
  'use strict';

  var CONFIG = global.Game997426Config || null;
  var readyPromise = null;

  /** 从父窗口请求配置（跨域 iframe 场景）。 */
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
          return SDK;
        });
      return readyPromise;
    },

    _post: function (path, body, retries) {
      retries = retries === undefined ? 1 : retries;
      var cfg = SDK._cfg;
      return fetch(cfg.restUrl + path, {
        method: body ? 'POST' : 'GET',
        headers: body
          ? { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce }
          : {},
        credentials: 'include',
        body: body ? JSON.stringify(body) : undefined,
      }).catch(function (err) {
        // 网络错误自动重试一次（4xx 业务错误不重试）。
        if (retries > 0) return SDK._post(path, body, retries - 1);
        throw err;
      }).then(function (r) { return r.json(); });
    },

    /**
     * 上报成绩。
     * @param {number} score 分数（非负整数）
     * @param {object} [opts] {extra:string}
     * @returns {Promise<{ok,best,rank,points_awarded,total_points}>}
     */
    submitScore: function (score, opts) {
      opts = opts || {};
      var cfg = SDK._cfg;
      var gameId = this._gameId || parseInt(new URLSearchParams(location.search).get('game_id'), 10) || 0;
      return SDK._post('/score', {
        game_id: gameId,
        score: Math.max(0, Math.round(Number(score) || 0)),
        nonce: cfg.nonce,
        extra: String(opts.extra || ''),
      }).then(function (res) {
        // 广播给父页面，便于主题弹出提示。
        try {
          global.parent.postMessage({ type: 'game997426:score-result', result: res }, '*');
        } catch (e) { /* ignore */ }
        return res;
      });
    },

    /** 当前用户信息。 */
    getMe: function () {
      return SDK._post('/me');
    },

    /**
     * 排行榜。
     * @param {number} [limit=10]
     * @param {string} [period='all'] all|day|week|month
     */
    getLeaderboard: function (limit, period) {
      return SDK._post('/leaderboard?game_id=' + encodeURIComponent(this._gameId || 0) +
        '&limit=' + encodeURIComponent(limit || 10) +
        '&period=' + encodeURIComponent(period || 'all'));
    },

    _gameId: 0,
    /** 由宿主页面注入的游戏 ID。 */
    setGameId: function (id) { this._gameId = parseInt(id, 10) || 0; },
  };

  // 宿主页面可通过 window.Game997426GameId 指定游戏 ID。
  if (global.Game997426GameId) SDK.setGameId(global.Game997426GameId);

  global.Game997426 = SDK;
  global.Game997426.ready = SDK.init.bind(SDK);

  // 响应父页面的配置请求（本页面作为 iframe 时）。
  global.addEventListener('message', function (e) {
    if (e.data && e.data.type === 'game997426:request-config' && CONFIG) {
      e.source.postMessage({ type: 'game997426:config', config: CONFIG }, '*');
    }
  });
})(window);
