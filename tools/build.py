# -*- coding: utf-8 -*-
"""
997426小游戏平台 — 一键打包脚本（v2 插件架构）
自动发现 plugins-single/ 下所有游戏/组件插件并打包，
生成 dist/ 下：主题包、大厅包、各游戏包、整合包。

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
SINGLE = ROOT / "plugins-single"

EXCLUDE_NAMES = {".git", ".gitignore", "node_modules", ".DS_Store", "Thumbs.db"}


def make_zip(src: Path, top_dir: str, out_file: Path) -> int:
    """把 src 目录打包为 zip，内部带一层 top_dir 顶层目录。返回文件数。"""
    count = 0
    with zipfile.ZipFile(out_file, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for path in sorted(src.rglob("*")):
            if any(part in EXCLUDE_NAMES for part in path.parts):
                continue
            if path.is_file():
                zf.write(path, str(Path(top_dir) / path.relative_to(src)))
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

    # 1) 主题。
    theme_src = ROOT / "themes" / "997426game"
    out = DIST / "997426game-theme.zip"
    n = make_zip(theme_src, "997426game", out)
    total += n
    print(f"[OK] 997426game-theme.zip            {n:>3} 个文件  {out.stat().st_size/1024:>8.1f} KB")

    # 2) 自动发现 plugins-single/ 全部插件（主题外每个子目录一个 zip）。
    for plugin_dir in sorted(SINGLE.iterdir()):
        if not plugin_dir.is_dir():
            continue
        out = DIST / f"{plugin_dir.name}-plugin.zip"
        n = make_zip(plugin_dir, plugin_dir.name, out)
        total += n
        print(f"[OK] {plugin_dir.name}-plugin.zip".ljust(37) +
              f"{n:>3} 个文件  {out.stat().st_size/1024:>8.1f} KB")

    # 3) 整合包：整个仓库内容（不含 .git 与 dist 自身）。
    full = DIST / "997426youxi-full.zip"
    n = 0
    with zipfile.ZipFile(full, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for path in sorted(ROOT.rglob("*")):
            rel = path.relative_to(ROOT)
            if rel.parts[0] in EXCLUDE_NAMES | {".git", "dist", "__pycache__"}:
                continue
            if path.is_file():
                zf.write(path, str(Path("997426youxi") / rel))
                n += 1
    total += n
    print(f"[OK] 整合包 997426youxi-full.zip     {n:>3} 个文件  {full.stat().st_size/1024:>8.1f} KB")

    print(f"\n完成 ✅  共 {total} 个文件。新游戏插件放入 plugins-single/ 后重新运行即可。")
    return 0


if __name__ == "__main__":
    sys.exit(main())
