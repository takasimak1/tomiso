<?php
use fmRESTor\fmRESTor;
session_start();
if (!isset($_SESSION['store_id'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/src/fmRESTor.php';
require_once __DIR__ . '/fm_setting.php';

$store_id = $_SESSION['store_id'];

$fm  = new fmRESTor($host, $db, $layout_hanbai, $api_master_user, $api_master_pass, ['allowInsecure' => true]);
$res = $fm->getRecords(['_limit' => 100]);

echo '<pre style="padding:20px; font-size:12px; line-height:1.8;">';
echo 'layout_hanbai = ' . $layout_hanbai . "\n";
echo 'store_id      = ' . $store_id . "\n\n";

$data = $res['result']['response']['data'] ?? [];
echo 'record count: ' . count($data) . "\n\n";

if (count($data) > 0) {
    echo "--- fieldData keys ---\n";
    echo json_encode(array_keys($data[0]['fieldData']), JSON_UNESCAPED_UNICODE) . "\n\n";

    echo "--- 全件（取扱店舗・よみがな確認）---\n";
    printf("%-20s %-30s %-15s %-15s\n", '部門', '商品名', 'よみがな', '取扱店舗');
    echo str_repeat('-', 85) . "\n";
    foreach ($data as $row) {
        $f = $row['fieldData'];
        printf("%-20s %-30s %-15s %-15s\n",
            mb_substr($f['部門']     ?? '', 0, 8),
            mb_substr($f['商品名']   ?? '', 0, 14),
            mb_substr($f['よみがな'] ?? 'MISSING', 0, 10),
            mb_substr($f['取扱店舗'] ?? 'MISSING', 0, 10)
        );
    }
}
echo '</pre>';
