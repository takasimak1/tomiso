#!/bin/bash
# =====================================================================
# setup_git_mac.sh  ととレジ — GitHub 初回セットアップ
#
# 【実行方法】Mac ターミナルで tomiso/ に移動してから実行
#   cd ~/Documents/Claude/Projects/富惣/FileMakerDataAPI/富惣_FileMakerDataAPI/tomiso
#   bash setup_git_mac.sh
#
# ★ 初回のみ実行してください（2回目以降は git add/commit/push を使う）
# =====================================================================
GITHUB_USER="takasimak1"
REPO_NAME="tomiso"

echo "=== ととレジ GitHub セットアップ ==="
echo ""

# ──────────────────────────────────────────
# [1/4] git 初期化（済みの場合はスキップ）
# ──────────────────────────────────────────
echo "[1/4] git init..."
git init
git config user.email "takasima.k1@gmail.com"
git config user.name  "takasimak1"
git branch -M main 2>/dev/null || git checkout -b main 2>/dev/null || true

# ──────────────────────────────────────────
# [2/4] ステージ（.gitignore が機密を自動除外）
# ──────────────────────────────────────────
echo "[2/4] ファイルをステージ中（.gitignore で機密を自動除外）..."
git add .

echo ""
echo "  ▼ ステージされるファイル:"
git status --short
echo ""
echo "  ★ fm_config_secret.php が含まれていないことを確認してください"
echo ""
read -p "  問題なければ Enter を押してください..."

# ──────────────────────────────────────────
# [3/4] コミット
# ──────────────────────────────────────────
echo "[3/4] コミット..."
git commit -m "initial commit: ととレジ Web POS v0.9

- POS 売上登録（JAN-13 インストアコード・StarWebPRNT 印刷）
- 売上日報 入力・確定
- 本社：投入確認・昨対ランキング・店舗マスター管理・時間帯別
- 店舗トップ：お知らせ（未確定日報の自動表示）
- .gitignore で fm_config_secret.php を除外済み" || echo "  （変更なし or コミット済み — スキップ）"

# ──────────────────────────────────────────
# [4/4] リモート登録 → Push
# ──────────────────────────────────────────
echo "[4/4] GitHub へ push..."

# すでに origin が登録されている場合は URL を更新
if git remote get-url origin >/dev/null 2>&1; then
    echo "  origin は設定済みです。URL を確認します..."
    git remote set-url origin "https://github.com/${GITHUB_USER}/${REPO_NAME}.git"
else
    git remote add origin "https://github.com/${GITHUB_USER}/${REPO_NAME}.git"
fi

# GitHub 側に既存コミットがある場合は --allow-unrelated-histories で merge してから push
if git ls-remote --exit-code origin main >/dev/null 2>&1; then
    echo "  GitHub 側に既存コミットがあります。ローカルを優先して上書きします..."
    git push -u origin main --force
else
    git push -u origin main
fi

echo ""
echo "✅ 完了！"
echo "   https://github.com/${GITHUB_USER}/${REPO_NAME}"
echo ""
echo "  ─────────────────────────────────────"
echo "  次回以降（ファイルを変更したら）:"
echo "    git add -A"
echo "    git commit -m '変更内容のメモ'"
echo "    git push"
echo "  ─────────────────────────────────────"
