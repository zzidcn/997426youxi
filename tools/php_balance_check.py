import re

files = [
 'plugins/997426-game-core/game997426.php',
 'plugins/997426-game-core/includes/class-game-cpt.php',
 'plugins/997426-game-core/includes/class-leaderboard.php',
 'plugins/997426-game-core/includes/class-points.php',
 'plugins/997426-game-core/includes/class-rest-api.php',
 'plugins/997426-game-core/includes/class-shortcodes.php',
 'plugins/997426-game-core/uninstall.php',
 'themes/997426game/functions.php',
 'themes/997426game/header.php',
 'themes/997426game/footer.php',
 'themes/997426game/index.php',
 'themes/997426game/single-game.php',
 'themes/997426game/archive.php',
 'themes/997426game/archive-game.php',
 'themes/997426game/page.php',
]
ok = True
for f in files:
    s = open(f, encoding='utf-8').read()
    # 先剥块注释，再剥字符串，最后剥行注释（避免 URL 中的 // 被误判为注释）。
    s2 = re.sub(r'/\*.*?\*/', '', s, flags=re.S)
    s2 = re.sub(r"'(?:\\.|[^'])*'", "''", s2)
    s2 = re.sub(r'"(?:\\.|[^"])*"', '""', s2)
    s2 = re.sub(r'//[^\n]*', '', s2)
    for a, b in [('{', '}'), ('(', ')'), ('[', ']')]:
        if s2.count(a) != s2.count(b):
            print(f'{f}: unbalanced {a}{b}: {s2.count(a)} vs {s2.count(b)}')
            ok = False
print('ALL BALANCED' if ok else 'ERRORS FOUND')
