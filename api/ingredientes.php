<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/funciones.php';

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['q'])) {
        $stmt = $db->prepare("SELECT id, nombre, imagen_url FROM ingredientes WHERE nombre LIKE ? ORDER BY nombre LIMIT 30");
        $stmt->execute(['%' . $_GET['q'] . '%']);
    } else {
        $stmt = $db->query("SELECT id, nombre, imagen_url FROM ingredientes ORDER BY nombre");
    }
    responder($stmt->fetchAll());
} else {
    responder(["error" => "Método no permitido"], 405);
}
