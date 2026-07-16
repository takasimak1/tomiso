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
$layout_bumon   = 'bumon_API';        // 部門マスタ（部門名・並び順。インストアコード部門定義の元）

// ■ 発注・受注（工場向け）
$layout_product = 'product_API';      // 商品マスタ（発注用 ※12,403件）

// ■ 売上日報（店舗入力・本部集計）
$layout_daily_report     = 'daily_report_API';      // _24_売上日報（CRUD全操作）
$layout_daily_report_sum = 'daily_report_sum_API';  // _24_売上日報（集計・昨対クエリ）

// ============================================================
// 注意：FileMaker 側で各 _API レイアウトを作成する際、
//       そのレイアウトのベーステーブルを以下に合わせてください。
//
//   tenpo_API            → _21_店舗マスタ
//   pos_API              → 店舗売上明細
//   hanbai_API           → 販売商品
//   bumon_API            → 部門マスタ（部門CD/部門名/並び順）
//   product_API          → 商品マスタ
//   daily_report_API     → _24_売上日報（全フィールド配置）
//   daily_report_sum_API → _24_売上日報（週番号・曜日・年 の計算フィールドを必ず配置）
// ============================================================

// --- API 機密情報の読み込み ---
require_once __DIR__ . '/fm_config_secret.php';

$api_master_user = $api_master_user;
$api_master_pass = $api_master_pass;

// ============================================================
// 繰り返しフィールド ヘルパー（取扱店舗 / セール価格）
// ============================================================
define('MAX_STORE_REPS', 50); // FileMaker 繰り返し数に合わせる

/** 繰り返しフィールドのキー名を返す（FM Data API は全 pos に (n) サフィックスを付ける） */
function repeatKey(string $field, int $pos): string {
    return "{$field}({$pos})";
}

/** fieldData から繰り返しフィールドを [pos => value] 配列で取得
 *  FM Data API は値を整数で返すことがあるため (string) でキャスト */
function getRepeatValues(array $f, string $field, int $max = MAX_STORE_REPS): array {
    $result = [];
    for ($i = 1; $i <= $max; $i++) {
        $raw = $f[repeatKey($field, $i)] ?? '';
        $v   = trim((string)$raw);
        if ($v !== '') $result[$i] = $v;
    }
    return $result;
}

/** 取扱店舗の [pos => storeId] マップを取得 */
function getStorePositions(array $f): array {
    return getRepeatValues($f, '取扱店舗');
}

/** 特定店舗のセール価格（0=未設定）を取得 */
function getStoreSalePrice(array $f, string $storeId): int {
    $positions = getStorePositions($f);
    $pos = array_search($storeId, $positions);
    if ($pos === false) return 0;
    return (int)($f[repeatKey('セール価格', $pos)] ?? 0);
}

/**
 * 取扱店舗チェックボックスの保存用 fieldData を構築
 * @param array $selectedStoreIds  選択された店舗IDの配列（順序不問）
 * @param array $currentSalePrices 現在の [pos => salePrice] を保持するため
 * @param array $currentPositions  現在の [pos => storeId]
 */
function buildStoreFieldData(array $selectedStoreIds, array $currentPositions = [], array $currentSalePrices = []): array {
    // 選択店舗を既存ポジション優先で割り当て
    $newPositions = []; // pos => storeId
    // 既存ポジションで選択済みのものを維持
    foreach ($currentPositions as $pos => $sid) {
        if (in_array($sid, $selectedStoreIds)) {
            $newPositions[$pos] = $sid;
        }
    }
    // 新規追加分を空きに詰める
    foreach ($selectedStoreIds as $sid) {
        if (!in_array($sid, $newPositions)) {
            for ($i = 1; $i <= MAX_STORE_REPS; $i++) {
                if (!isset($newPositions[$i])) {
                    $newPositions[$i] = $sid;
                    break;
                }
            }
        }
    }

    // fieldData を構築（全50ポジション分）
    $data = [];
    for ($i = 1; $i <= MAX_STORE_REPS; $i++) {
        $data[repeatKey('取扱店舗', $i)]  = $newPositions[$i] ?? '';
        // セール価格は既存ポジションが維持された場合のみ保持
        $data[repeatKey('セール価格', $i)] = isset($newPositions[$i]) ? ($currentSalePrices[$i] ?? '') : '';
    }
    return $data;
}

/** 取扱店舗に1店舗追加する（次の空きポジションに）*/
function addStoreFieldData(array $f, string $storeId): array {
    $positions = getStorePositions($f);
    if (in_array($storeId, $positions)) return []; // 既存
    for ($i = 1; $i <= MAX_STORE_REPS; $i++) {
        if (!isset($positions[$i])) {
            return [repeatKey('取扱店舗', $i) => $storeId];
        }
    }
    return []; // 満杯
}

/** 取扱店舗から1店舗削除する */
function removeStoreFieldData(array $f, string $storeId): array {
    $positions = getStorePositions($f);
    $pos = array_search($storeId, $positions);
    if ($pos === false) return [];
    return [
        repeatKey('取扱店舗', $pos)  => '',
        repeatKey('セール価格', $pos) => '',
    ];
}

/** findRecords 用OR クエリ（いずれかの繰り返しに storeId が含まれる商品を検索）*/
function buildStoreFindQuery(string $storeId, array $extraConditions = []): array {
    $queries = [];
    for ($i = 1; $i <= MAX_STORE_REPS; $i++) {
        $queries[] = array_merge([repeatKey('取扱店舗', $i) => $storeId], $extraConditions);
    }
    return $queries;
}