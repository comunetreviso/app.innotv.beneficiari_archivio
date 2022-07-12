<?php

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Access-Control-Request-Headers, Authorization");
    http_response_code(200);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once("../../config/config.php");
require_once("../../config/database.php");
require_once("../../models/beneficiario.php");

if (!isset($_SERVER["PHP_AUTH_USER"]) || !isset($_SERVER["PHP_AUTH_PW"]) || $_SERVER["PHP_AUTH_USER"] != API_USER || $_SERVER["PHP_AUTH_PW"] != API_PW) {
    http_response_code(401);
    exit;
}

$db = new database();
$conn = null;

try {
    $conn = $db->connect();
    $beneficiario = new beneficiario($conn);
    $results = $beneficiario->get_anni_riferimento();
    echo json_encode($results);
}   

catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("error_message" => $e->getMessage()));
}  

finally {
    $db->disconnect($conn);
}