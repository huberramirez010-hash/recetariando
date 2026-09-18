<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

function responder($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function obtenerDatosJSON() {
    return json_decode(file_get_contents("php://input"), true) ?? [];
}

function obtenerUsuarioId() {
    return $_SERVER['HTTP_X_USER_ID'] ?? null;
}
