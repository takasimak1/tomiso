<?php

use fmRESTor\fmRESTor;
session_start();
require_once dirname(__DIR__) . '/_or/src/fmRESTor.php';

//データベースセッティング
$host = 'sys.kei1.me';
$db = 'theta_iris';
$user = '1';
$pass = '1';
$layout = 'Products_API';
$fm = new fmRESTor($host, $db, $layout, $user, $pass, array("allowInsecure" => true));

// Setting up parameters for get records
$GetRecords= array(
    //"_offset.USER_licence"=> ,
    //"limit.USER_licence"=> ,
    "_limit"=>10,
    //"_sort" =>"",
    "script"=>"Log request",
    "script.param"=>"Parameter from fmRESTor - get records"
);

// Gets records with maximum display of 10 records 
$result = $fm->getRecords($GetRecords);
if(!$fm->isError($result)){
    echo "Request succeeded: ";
} else {
    echo "Request Failed: ";
}

$response = $fm->getResponse($result);
$records = $response['response']['data'];
?>
        <table class="table table-bordered table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th></th>
                    <th>商品名</th>
                    <th>商品コード</th>
                    <th>カテゴリー</th>
                    <th>価格</th>
                    <th>在庫量</th>
                    <th>商品説明</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record) { ?>
                    <tr>
                        <td><?= htmlspecialchars($record['recordId']) ?></td>
                <td class="center"><img src="<?= $record['fieldData']['商品画像'] ?>" alt="商品画像" width="100" /> </th>
                        <td><?= htmlspecialchars($record['fieldData']['商品名']) ?></td>
                        <td><?= htmlspecialchars($record['fieldData']['商品コード']) ?></td>
                        <td><?= htmlspecialchars($record['fieldData']['カテゴリー']) ?></td>
                        <td><?= htmlspecialchars($record['fieldData']['価格']) ?> 円</td>
                        <td><?= htmlspecialchars($record['fieldData']['在庫量']) ?></td>
                        <td><?= htmlspecialchars(mb_substr($record['fieldData']['商品説明'], 0, 50, 'UTF-8')) . '...' ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>


<?php
echo "<pre>";
print_r($response);
echo "</pre>";
exit();
?>