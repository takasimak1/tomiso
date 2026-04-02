<?php
// ============================================================
// fm_setting.php  FileMaker 接続設定
// ============================================================

// --- サーバー & データベース ---
$host = 'sys.kei1.me';
$db   = 'tomiso';

// --- レイアウト定義 ---
//
// ■ 認証・ユーザー管理
$layout_tenpo   = 'tenpo_API';        // _21_店舗マスタ（ログイン・店舗情報取得）
$layout_account = 'account_API';      // Webユーザー管理（将来用）

// ■ 店舗POS（販売商品テーブル 46件）
$layout_pos     = 'pos_API';          // 店舗売上明細（売上登録・参照）
$layout_hanbai  = 'hanbai_API';       // 販売商品（POS商品一覧 ※46件）

// ■ 発注・受注（工場向け）
$layout_product = 'product_API';      // 商品マスタ（発注用 ※12,403件）

// ============================================================
// 注意：FileMaker 側で各 _API レイアウトを作成する際、
//       そのレイアウトのベーステーブルを以下に合わせてください。
//
//   tenpo_API   → _21_店舗マスタ
//   pos_API     → 店舗売上明細
//   hanbai_API  → 販売商品
//   product_API → 商品マスタ
// ============================================================

// --- API 機密情報の読み込み ---
require_once __DIR__ . '/fm_config_secret.php';

$api_master_user = $api_master_user;
$api_master_pass = $api_master_pass;