/**
 * 997426 游戏音效模块（零依赖，Web Audio 合成，无外部音频文件）
 * 用法：
 *   SFX.init();          // 首次用户交互后调用（浏览器自动播放策略）
 *   SFX.play('eat');     // 播放指定音效
 * 内置音效：eat 吃/合并、over 结束、click 按钮、move 移动、level 升级
 */
(function (global) {
  'use strict';

  var ctx = null;
  var enabled = true;

  var SFX = {
    init: function () {
      if (ctx) return;
      try {
        var AC = global.AudioContext || global.webkitAudioContext;
        if (AC) ctx = new AC();
      } catch (e) { /* 不支持则静默 */ }
    },
    resume: function () {
      if (ctx && ctx.state === 'suspended') ctx.resume();
    },
    toggle: function () {
      enabled = !enabled;
      return enabled;
    },
    isEnabled: function () { return enabled; },

    /** 播放音效。name 见顶部注释；opts 可覆盖音高。 */
    play: function (name, opts) {
      if (!enabled) return;
      // 移动端：ctx 可能在首次交互前未创建，这里惰性创建。
      if (!ctx) SFX.init();
      if (!ctx) return;
      opts = opts || {};
      try {
        if (ctx.state === 'suspended') ctx.resume();
        var t = ctx.currentTime;
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        var presets = {
          // 吃食物/合并：短促上扬
          eat:    { type: 'square',   f0: 520, f1: 880, dur: .09, vol: .12 },
          // 移动/滑动：极短低音
          move:   { type: 'triangle', f0: 220, f1: 180, dur: .05, vol: .07 },
          // 按钮点击
          click:  { type: 'sine',     f0: 700, f1: 700, dur: .04, vol: .09 },
          // 游戏结束：下行三连
          over:   { type: 'sawtooth', f0: 440, f1: 110, dur: .55, vol: .14 },
          // 升级/新纪录：胜利琶音
          level:  { type: 'square',   f0: 660, f1: 1320, dur: .25, vol: .11 },
        };
        var p = presets[name] || presets.click;
        var mult = opts.pitch || 1;

        osc.type = p.type;
        osc.frequency.setValueAtTime(p.f0 * mult, t);
        osc.frequency.exponentialRampToValueAtTime(Math.max(40, p.f1 * mult), t + p.dur);

        gain.gain.setValueAtTime(p.vol, t);
        gain.gain.exponentialRampToValueAtTime(0.001, t + p.dur);

        osc.start(t);
        osc.stop(t + p.dur + .02);

        // over/level 附加第二声
        if (name === 'over') {
          var o2 = ctx.createOscillator(), g2 = ctx.createGain();
          o2.connect(g2); g2.connect(ctx.destination);
          o2.type = 'sawtooth';
          o2.frequency.setValueAtTime(330, t + .18);
          o2.frequency.exponentialRampToValueAtTime(80, t + .7);
          g2.gain.setValueAtTime(.12, t + .18);
          g2.gain.exponentialRampToValueAtTime(.001, t + .75);
          o2.start(t + .18); o2.stop(t + .8);
        }
        if (name === 'level') {
          [880, 1100, 1320].forEach(function (f, i) {
            var o = ctx.createOscillator(), g = ctx.createGain();
            o.connect(g); g.connect(ctx.destination);
            o.type = 'square';
            o.frequency.setValueAtTime(f, t + .1 + i * .09);
            g.gain.setValueAtTime(.09, t + .1 + i * .09);
            g.gain.exponentialRampToValueAtTime(.001, t + .1 + i * .09 + .12);
            o.start(t + .1 + i * .09); o.stop(t + .1 + i * .09 + .15);
          });
        }
      } catch (e) { /* 音频失败不影响游戏 */ }
    },
  };

  global.GameSFX = SFX;

  // 首次任意交互自动初始化（浏览器自动播放策略要求）。
  ['pointerdown', 'touchstart', 'keydown'].forEach(function (ev) {
    global.addEventListener(ev, function () { SFX.init(); SFX.resume(); }, { once: false, passive: true });
  });

  // iOS Safari：touchend 才解锁 AudioContext，额外挂一次。
  global.addEventListener('touchend', function () {
    if (ctx && ctx.state === 'suspended') ctx.resume();
    if (!ctx) { SFX.init(); }
  }, { passive: true });
})(window);
