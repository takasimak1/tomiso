<?php
/**
 * sales_entry_debug.php  v2
 * エラーの生レスポンスをすべて出力
 */
use fmRESTor\fmRESTor;
session_start();
if (!isset($_SESSION['store_id'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/src/fmRESTor.php';
require_once __DIR__ . '/fm_setting.php';

$store_id = $_SESSION['store_id'];
$today    = date('m/d/Y');   // FileMaker Date型: MM/DD/YYYY形式

$fm = new fmRESTor($host, $db, $layout_pos, $api_master_user, $api_master_pass, ['allowInsecure' => true]);

$testRecord = [
    'fieldData' => [
        '販売日時'  => $today,
        '店舗No'   => $store_id,
        '商品名'   => 'テスト商品',
        '部門'     => 'テスト',
        '販売単位' => '1個',
        '本体価格' => 100,
        '数量'     => 1,
        '値引額'   => 0,
        '値引率'   => 0,
        '販売金額' => 100,
        '明細金額' => 100,
    ]
];

$result = $fm->createRecord($testRecord);

echo '<pre style="padding:20px; font-size:13px; line-height:1.7;">';
echo 'layout_pos = ' . $layout_pos . "\n";
echo 'store_id   = ' . $store_id . "\n\n";
echo 'isError: ' . ($fm->isError($result) ? 'true' : 'false') . "\n\n";

// result全体を出力
echo "--- result 全体 ---\n";
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

if (!$fm->isError($result)) {
    $recordId = $result['result']['response']['recordId'] ?? 'N/A';
    echo "✓ 登録成功！ recordId = " . $recordId . "\n";
    echo "※ FileMaker でテストレコード（recordId=" . $recordId . "）を削除してください。\n";
} else {
    echo "✗ 登録失敗\n";
}
echo '</pre>';
