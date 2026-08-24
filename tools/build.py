# -*- coding: utf-8 -*-
"""
997426小游戏平台 — 一键打包脚本
将主题、插件、游戏目录分别打包为可直接在 WordPress 后台上传的 zip，
并在 dist/ 下生成一个整合包。

用法：
    python tools/build.py            # 打包全部
    python tools/build.py --clean    # 先清空 dist/ 再打包
"""
import argparse
import shutil
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DIST = ROOT / "dist"

# (源目录相对路径, zip 内顶层目录名, 输出文件名)
TARGETS = [
    ("themes/997426game", "997426game", "997426game-theme.zip"),
    ("plugins/997426-game-core", "997426-game-core", "997426-game-core-plugin.zip"),
    ("games/snake", "snake", "game-snake.zip"),
    ("games/2048", "2048", "game-2048.zip"),
]

EXCLUDE_NAMES = {".git", ".gitignore", "node_modules", ".DS_Store", "Thumbs.db"}


def make_zip(src: Path, top_dir: str, out_file: Path) -> int:
    """把 src 目录打包为 zip，内部带一层 top_dir 顶层目录。返回文件数。"""
    count = 0
    with zipfile.ZipFile(out_file, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for path in sorted(src.rglob("*")):
            if any(part in EXCLUDE_NAMES for part in path.parts):
                continue
            if path.is_file():
                arcname = Path(top_dir) / path.relative_to(src)
                zf.write(path, str(arcname))
                count += 1
    return count


def main() -> int:
    parser = argparse.ArgumentParser(description="997426小游戏平台一键打包")
    parser.add_argument("--clean", action="store_true", help="先清空 dist/ 目录")
    args = parser.parse_args()

    if args.clean and DIST.exists():
        shutil.rmtree(DIST)

    DIST.mkdir(exist_ok=True)
    print(f"输出目录: {DIST}\n")

    total = 0
    for src_rel, top_dir, out_name in TARGETS:
        src = ROOT / src_rel
        if not src.is_dir():
            print(f"[跳过] {src_rel} 不存在")
            continue
        out = DIST / out_name
        n = make_zip(src, top_dir, out)
        total += n
        size_kb = out.stat().st_size / 1024
        print(f"[OK] {out_name:<32} {n:>3} 个文件  {size_kb:>8.1f} KB")

    # 整合包：整个仓库内容（不含 .git 与 dist 自身）
    bundle = DIST / "997426youxi-full.zip"
    n = 0
    with zipfile.ZipFile(bundle, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for path in sorted(ROOT.rglob("*")):
            rel = path.relative_to(ROOT)
            if rel.parts[0] in EXCLUDE_NAMES | {".git", "dist", "__pycache__"}:
                continue
            if path.is_file():
                zf.write(path, str(Path("997426youxi") / rel))
                n += 1
    total += n
    print(f"\n[OK] 整合包 997426youxi-full.zip  {n} 个文件  "
          f"{bundle.stat().st_size / 1024:.1f} KB")

    print(f"\n完成 ✅  共 {total} 个文件。")
    print("WordPress 安装：")
    print("  · 外观→主题→上传 997426game-theme.zip 后启用")
    print("  · 插件→安装插件→上传 997426-game-core-plugin.zip 后激活（自动建表）")
    print("  · game-*.zip 解压到站点根目录 games/ 下即可游玩")
    return 0


if __name__ == "__main__":
    sys.exit(main())
