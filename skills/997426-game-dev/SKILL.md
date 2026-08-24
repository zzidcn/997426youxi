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

平台仓库：https://github.com/zzidcn/997426youxi
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
   自定义字段 `_game997426_url`=完整URL（可选 `_game997426_width/_height`），发布。

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
