<?php
use fmRESTor\fmRESTor;
session_start();
if (!isset($_SESSION['store_id'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/src/fmRESTor.php';
require_once __DIR__ . '/fm_setting.php';

$store_id = $_SESSION['store_id'];
$today_fm = date('m/d/Y');

$fm = new fmRESTor($host, $db, $layout_pos, $api_master_user, $api_master_pass, ['allowInsecure' => true]);

// _limitなし、limitをquery同階層に
$query = [
    'query' => [[
        '店舗No'   => $store_id,
        '販売日時' => $today_fm,
    ]],
    'limit' => 10,
];

$result = $fm->findRecords($query);

echo '<pre style="padding:20px; font-size:12px; line-height:1.7;">';
echo 'store_id  = ' . $store_id . "\n";
echo 'today_fm  = ' . $today_fm . "\n\n";
echo 'isError: ' . ($fm->isError($result) ? 'true' : 'false') . "\n";

// messages 全パス確認
$msg1 = $result['result']['messages'] ?? null;
$msg2 = $result['messages'] ?? null;
echo 'result.messages: ' . json_encode($msg1, JSON_UNESCAPED_UNICODE) . "\n";
echo 'messages: '        . json_encode($msg2, JSON_UNESCAPED_UNICODE) . "\n\n";

// top keys
echo 'top keys: '    . json_encode(array_keys($result), JSON_UNESCAPED_UNICODE) . "\n";
echo 'result keys: ' . json_encode(array_keys($result['result'] ?? []), JSON_UNESCAPED_UNICODE) . "\n";
$resp = $result['result']['response'] ?? [];
echo 'result.response keys: ' . json_encode(array_keys($resp), JSON_UNESCAPED_UNICODE) . "\n\n";

// dataのパスを全部試す
$paths = [
    "result.response.data"       => $result['result']['response']['data'] ?? null,
    "result.response.dataInfo"   => $result['result']['response']['dataInfo'] ?? null,
    "response.data"              => $result['response']['data'] ?? null,
];
foreach ($paths as $path => $val) {
    echo $path . ': ' . ($val !== null ? count((array)$val) . '件' : 'null') . "\n";
}

// 1件目があれば表示
$data = $result['result']['response']['data'] ?? $result['response']['data'] ?? [];
if (count($data) > 0) {
    echo "\n--- 1件目 fieldData ---\n";
    echo json_encode($data[0]['fieldData'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
echo '</pre>';
