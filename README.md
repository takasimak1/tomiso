# ととレジ — 株式会社富惣 Web POS システム

FileMaker DataAPI を使った、食品小売業向け Web POS・売上管理システム。

## セットアップ

```bash
# 1. fm_config_secret.php.example をコピーして API 認証情報を設定
cp fm_config_secret.php.example fm_config_secret.php
# vim fm_config_secret.php  # 編集

# 2. PHP 8.x + Apache (kei1.me) に配置
```

## 主なファイル

| ファイル | 説明 |
|---|---|
| `login.php` | ログイン |
| `sales_entry.php` | POS レシート入力・発行（中核） |
| `hq_tenpo_maint.php` | 本社：店舗マスター管理 |
| `SPEC.md` | 詳細仕様・開発ロードマップ |

## 注意

- `fm_config_secret.php` は `.gitignore` で除外済み（Git 管理外）
- `sales_queue_data/` は Git 管理外（ランタイム生成）

詳細は [SPEC.md](SPEC.md) を参照。
