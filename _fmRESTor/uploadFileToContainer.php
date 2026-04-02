<?php

use fmRESTor\fmRESTor;
session_start();
require_once dirname(__DIR__) . '/_or/src/fmRESTor.php';

$fm = new fmRESTor("sys.kei1.me", "fmRESTor", "php_user", "api", "api123456", array("allowInsecure" => true));

// These steps are for preparation only
$newRecord = array(
    "fieldData" => array(
        "surname" => "King",
        "email" => "king@tempor.net",
        "birthday" => "02.09.2020",
        "personal_identification_number" => "235",
        "address" => "7182 Morbi Road, Hisar 5230"
    ),
);

$response = $fm->createRecord($newRecord);

// This is ID the record that was made and the file be set there
$id = $fm->getResponse($response)["response"]["recordId"];

// Upload the file
$response2 = $fm->uploadFileToContainter($id, "photo", 1, __DIR__ . "/24uSoftware.jpg");
//ツリー構造
echo "<pre>";
print_r($response);
echo "</pre>";
echo "<pre>";
print_r($response2);
echo "</pre>";
exit();