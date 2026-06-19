#!/bin/bash
# =====================================================================
# setup_git_mac.sh  ととレジ — GitHub セットアップ
# Mac ターミナルで tomiso/ ディレクトリに移動して実行してください
# =====================================================================
set -e

echo "=== ととレジ GitHub セットアップ ==="
echo ""

# ── リポジトリ名（変更可）──
REPO_NAME="tomiso"
GITHUB_USER="takasimak1"

# ── git 初期化 ──
echo "[1/5] git init ..."
git init
git config user.email "takasima.k1@gmail.com"
git config user.name "takasimak1"
git branch -M main

# ── ステージ（機密除外） ──
echo "[2/5] ファイルをステージ中..."
git add \
    .gitignore \
    README.md \
    SPEC.md \
    fm_config_secret.php.example \
    fm_setting.php \
    header.php \
    footer.php \
    hq_header.php \
    hq_jikanbetsu.php \
    hq_nyuryoku.php \
    hq_seiseki.php \
    hq_shohin_maint.php \
    hq_store.php \
    hq_tenpo_maint.php \
    hq_top.php \
    instore_codes.php \
    login.php \
    sales_confirm.php \
    sales_edit.php \
    sales_entry.php \
    sales_list.php \
    shohin_maint.php \
    star_webprnt_inline.php \
    top.php \
    daily_report_entry.php \
    daily_report_mystore.php \
    JS/ \
    src/

# ── コミット ──
echo "[3/5] 初回コミット..."
git commit -m "Initial commit: ととレジ Web POS システム

- POS レシート発行（JAN-13 インストアコード対応）
- 部門別売上集計・昨対比
- 本社：店舗マスター管理（営業状態・インストアコード）
- ログイン（閉店店舗ブロック機能付き）
- .gitignore で機密情報・非公開資料を除外
- SPEC.md: オフラインキュー・脱FileMaker ロードマップを記載"

# ── リモート追加 ──
echo ""
echo "[4/5] GitHub リモートを設定します"
echo ""
echo "  ★ 先に GitHub で空リポジトリを作成してください"
echo "    https://github.com/new"
echo "    ・Repository name: ${REPO_NAME}"
echo "    ・Public または Private"
echo "    ・README は追加しない（ここで作成済み）"
echo ""
read -p "    作成したら Enter キーを押してください..."

git remote add origin "https://github.com/${GITHUB_USER}/${REPO_NAME}.git"

# ── Push ──
echo "[5/5] GitHub へ push..."
git push -u origin main

echo ""
echo "✅ 完了しました！"
echo "   https://github.com/${GITHUB_USER}/${REPO_NAME}"
