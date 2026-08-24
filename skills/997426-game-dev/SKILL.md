---
name: 997426-game-dev
description: 制作可上架997426小游戏平台的HTML5游戏时使用，含SDK接入与上架流程。
version: 1.0.0
author: zzidcn (997426)
license: GPL-2.0-or-later
metadata:
  hermes:
    tags: [wordpress, html5-game, game-platform, 997426]
---

# 997426小游戏 开发规范

## ⚠️ 架构变更（v2.0 起）：游戏即插件

自 v2.0 起放弃 iframe 嵌入（Cookie/积分链路不可靠），**每款游戏 = 一个独立 WordPress 插件**：
- 模板：`plugins-single/997426-game-snake/`（复制改造）
- 游戏直接渲染在页面内，登录态天然可用（wp_localize_script 注入 nonce + userId）
- 每游戏独立数据表 `wp_game997426_<slug>_scores`，独立 REST 命名空间 `game-<slug>/v1`
- 认证用 WordPress 标准 X-WP-Nonce（`wp_create_nonce('wp_rest')`）
- 上架 = 后台上传插件 zip → 页面写短代码 `[game_<slug>]`
- 卸载插件自动 DROP 数据表

## 平台仓库：https://github.com/zzidcn/997426youxi
本地代码库：`D:\AIjob\997426-game-platform\`
权威文档：`docs/游戏开发规范说明书.md`（先读它，本文是其执行摘要）

## When to Use

- 开发一款新的 HTML5 小游戏并上架「997426小游戏」平台；
- 涉及 Game997426 SDK、游戏排行榜/积分接入、WordPress 游戏上架；
- 修改/扩展平台的示例游戏或打包发布。

## 核心概念

- 每款游戏 = `games/<英文slug>/` 目录，唯一入口 `index.html`，纯静态、零构建、相对路径引用资源。
- 每发布一篇 WordPress `game` 文章（自定义字段 `_game997426_url` 指向 index.html）= 上架一款游戏。
- 成绩通过统一 REST API 上报 → 自动进全站排行榜与积分荣誉系统。

## 开发步骤

1. **选模板**：复制现成示例作脚手架：
   - Canvas 游戏 → `games/snake/`（devicePixelRatio 高清自适应）
   - DOM/CSS 游戏 → `games/2048/`（CSS Grid + clamp 字号自适应）
2. **HTML 骨架必须包含**：
   ```html
   <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no, viewport-fit=cover">
   <script src="../../plugins/997426-game-core/assets/js/game997426-sdk.js"></script>
   ```
3. **自适应**：Canvas 类监听 resize 按 `clientWidth × devicePixelRatio` 重设物理像素；DOM 类用 `min(96vw, 96vh - HUD)` + `aspect-ratio`。禁止横向滚动条、禁止固定窗口尺寸假设。
4. **双输入**：键盘（方向键/WASD + 空格开始）和触屏（滑动/点按；容器 `touch-action:none`，touchmove 里 `preventDefault()` 防滚动）。开始界面写明两种操作方式。
4b. **界面四要素（v2 强制）**：①开始游戏遮罩+「▶开始」按钮 ②⏹结束游戏按钮(游戏中显示,主动结算上报)+结算画面 ③HUD常驻⛶全屏按钮 ④❓介绍弹窗。参考 `plugins-single/997426-game-snake/`。
4c. **CSS 隔离**：所有游戏规则必须限定在游戏根类下（如 `.g2048-grid .tN`），裸类名（`.tile`、`.t2`）会被主题全局样式覆盖——曾致 2048 格子颜色失效。关键配色加 !important。
4d. **音效标准**：引入共享 `assets/sfx.js`（GameSFX，Web Audio 合成零文件），HUD 加 🔊 开关；挂接 eat/move/click/over 事件；首次交互自动 init（浏览器自动播放策略）。
4e. **全屏竖屏适配**：根类 `:fullscreen` 占满 100dvh + safe-area-inset；舞台 `min(96vw, 92vh-70px)` 方形居中；canvas 监听 fullscreenchange/orientationchange 重算。
4f. **榜单同步提示**：排行榜标题旁注明「成绩约 1 分钟内同步」（60s transient 缓存）。
4c. **SDK 兜底路径**（线上"离线模式"最常见原因）：SDK 动态注入候选路径依次为 `/wp-content/plugins/...` → `/plugins/...` → `../../plugins/...`，Rocket Loader 环境必须用此兜底。
5. **SDK 接入**（游戏结束时调用，一局仅一次）：
   ```js
   const sdk = await Game997426.ready();
   const res = await sdk.submitScore(finalScore); // {ok,best,rank,points_awarded}
   ```
   本地调试（file:// 或平台外）API 会静默失败，不影响游玩——无需 mock。
6. **行为红线**：不改父页面 DOM/localStorage、不发额外网络请求、不跳转外链、不伪造分数。

## 上架流程

1. `python tools/build.py --clean` 打包（新游戏需在 `tools/build.py` 的 TARGETS 里加一行）；
2. 游戏文件部署到站点 `games/<slug>/`；
3. WordPress 后台「游戏→上架新游戏」：填标题/介绍/特色图片(800×600)/分类，
   在「🎮 游戏设置」Metabox 填游戏 URL（字段名 `game997426_url`，**不能带下划线前缀**——
   WP 后台禁止手工创建 `_` 开头的隐藏字段，会报"抱歉您不能这么做"），发布。

## 上架前自查

- [ ] **所有 .php 文件必须以 `<?php` 开标签开头**（曾因 functions.php 以注释开头无开标签，导致源码原样输出到页面——本地用 grep 检查一遍）
- [ ] **主题 zip 根目录必须有 style.css（含主题头注释），否则 WordPress 报"缺少 style.css"拒绝安装**（v1.0.1 已修复）
- [ ] 浏览器直接打开 index.html 可玩、无控制台报错
- [ ] 桌面 Chrome + 手机（微信/Safari）实测通过；横竖屏切换正常
- [ ] 键盘+触屏均能完整玩一局
- [ ] submitScore 一局只调一次，上报失败不影响体验
- [ ] 无外链/广告/恶意代码

## 平台 API 速查（命名空间 /wp-json/game997426/v1）

| 接口 | 说明 |
|---|---|
| POST /score {game_id,score,nonce} | 提交成绩 |
| GET /me | 当前用户积分+徽章 |
| GET /leaderboard?game_id=&period=all\|day\|week\|month&limit= | 排行榜 |

短代码：`[games_grid]` `[game_leaderboard]` `[user_points]`

## Git 推送注意（本机环境）

- push 前 `export https_proxy=http://127.0.0.1:10808`；
- gh CLI 路径 `%LOCALAPPDATA%/Temp/ghtool/bin/gh.exe`；
- GitHub 仓库名不支持中文。

## 发版检查清单（每次 release 必做）

版本号需同步更新 **5 处**，缺一会被 WordPress/浏览器误判为旧版：
1. 插件主文件头 `Version:`（WP 识别插件版本的唯一依据）
2. `GAME997426_VERSION` 常量
3. 主题 style.css 头 `Version:`（WP 识别主题版本的唯一依据）
4. functions.php 头注释 Version + wp_enqueue 资源版本号（缓存刷新）
5. 打包后 grep 确认 dist 内无旧版本号残留
