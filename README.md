# 997426小游戏

基于 WordPress 的 HTML5 小游戏平台 —— **每款游戏即一个独立插件，上传启用即上架**。

线上站点：https://997426.xyz

## ✨ 平台特性

- **🧩 游戏即插件**：每款游戏是独立的 WordPress 插件，后台上传 zip → 启用，
  自动完成「建成绩表 + 创建发布游戏页面 + 注册进大厅 + 纳入全站排行」，
  全程零手工配置。
- **📱 原生页面渲染（无 iframe）**：游戏直接运行在 WordPress 页面内，
  登录态天然可用，积分记录不依赖跨域 Cookie，彻底规避 CDN/浏览器策略干扰。
- **🏆 全站积分排行**：首页动态聚合所有游戏的 `*_scores` 数据表，
  按「每人每游戏最高分 ÷ 100」求和输出 TOP10——装新游戏自动纳入，零配置。
- **🎮 游戏大厅**：`/games-hub/` 页面自动列出全部已装游戏卡片；
  游戏装卸实时同步，二次校验杜绝死链。
- **🔊 零依赖音效**：Web Audio API 实时合成（无音频文件、无额外请求），
  音高随游戏进程动态变化；HUD 一键静音。
- **📐 手机竖屏优先**：全屏占满 `100dvh` + 刘海安全区适配；
  贪吃蛇自动出现虚拟方向键（触屏检测多信号判断）；
  canvas 高分屏高清渲染、旋转自适应。
- **🔒 安全规范**：X-WP-Nonce 认证、游客加盐 IP 哈希去重、
  成绩参数校验与限流、卸载完全清理（页面+数据表）。
- **♿ 统一四要素 UI**：开始游戏 / 结束游戏(⏹) / 全屏(⛶) / 游戏介绍(❓)
  所有游戏一致体验。

## 📁 项目结构

```
997426-game-platform/
├── themes/997426game/              # WordPress 门户主题
│   ├── style.css                   #   主题头声明（WP 识别版本）
│   ├── functions.php               #   资源加载、登录用户区数据注入
│   ├── header.php / footer.php     #   顶栏（登录/注册/积分）+ 页脚
│   ├── index.php                   #   首页：Hero + 全站积分TOP10聚合 + 挑战入口
│   ├── search.php                  #   搜索结果页（游戏专用）
│   ├── page.php / archive*.php     #   普通页 / 归档模板
│   └── assets/css|js/              #   主题样式与交互（全屏/toast/榜单刷新）
│
├── plugins-single/                 # ★ 游戏插件目录（每款一个子目录）
│   ├── 997426-game-hub/            #   游戏大厅插件
│   │   ├── game997426-hub.php      #     注册表机制、/games-hub/ 自建页、[game_hub]
│   │   └── assets/hub.css          #     大厅卡片样式
│   ├── 997426-game-snake/          #   贪吃蛇插件（v2 标准模板）
│   │   ├── game997426-snake.php    #     激活建表+自动建页+大厅注册+REST+短代码
│   │   └── assets/game.js|css      #     玩法与皮肤（含虚拟方向键）
│   │   └── assets/sfx.js           #     Web Audio 合成音效模块（各游戏共用）
│   └── 997426-game-2048/           #   2048 插件（同标准）
│
├── docs/游戏开发规范说明书.md        # ★ 开发规范（架构/标准/验收清单）
├── tools/build.py                  # 一键打包脚本（生成 dist/*.zip 与 games.zip）
├── tools/php_balance_check.py      # PHP 括号平衡静态检查
└── skills/997426-game-dev/         # Hermes Agent 开发技能（经验固化）
```

## 🚀 快速开始

1. **装主题**：「外观 → 主题 → 上传」`997426game-theme.zip` → 启用
2. **装大厅**：「插件 → 安装插件 → 上传」`997426-game-hub-plugin.zip` → 启用
3. **装游戏**：上传任意 `997426-game-<slug>-plugin.zip` → 启用 → 完成！

启用后访问 `/games-hub/` 即游戏大厅；每款游戏有独立页面（如 `/game-snake/`）。
登录用户的成绩以昵称上榜并累计积分；游客按设备保留最高一条。

> 💡 提示：后台「设置 → 常规」勾选「任何人都可以注册」可开放访客注册。

## 🛠️ 开发新游戏

复制 `plugins-single/997426-game-snake/` 为模板，替换标识符与玩法逻辑，
按《[游戏开发规范说明书](docs/游戏开发规范说明书.md)》自查后，
`python tools/build.py --clean` 打包上传即可。详见规范文档。

## 📦 Releases

前往 [Releases 页面](https://github.com/zzidcn/997426youxi/releases/latest)
下载最新正式版：

| 文件 | 用途 |
|---|---|
| `997426game-theme.zip` | 主题 |
| `997426-game-hub-plugin.zip` | 游戏大厅（必装）|
| `997426-game-snake-plugin.zip` 等 | 各游戏插件 |
| `games.zip` | — （v2 已废弃静态部署，仅存档）|
| `997426youxi-full.zip` | 全仓库整合包 |

## License

GPL-2.0-or-later
