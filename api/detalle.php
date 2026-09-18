<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/funciones.php';

$db = (new Database())->getConnection();
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    responder(["error" => "ID de receta no válido"], 400);
}

// 1. Consultar receta principal
$stmt = $db->prepare("SELECT * FROM recetas WHERE id = ?");
$stmt->execute([$id]);
$receta = $stmt->fetch();

if (!$receta) {
    responder(["error" => "Receta no encontrada"], 404);
}

// 2. Consultar ingredientes
$stmtIng = $db->prepare("
    SELECT i.nombre, ri.cantidad, ri.unidad 
    FROM receta_ingredientes ri
    JOIN ingredientes i ON i.id = ri.ingrediente_id
    WHERE ri.receta_id = ?
");
$stmtIng->execute([$id]);
$ingredientes = $stmtIng->fetchAll();

// 3. Consultar nutrición
$stmtNut = $db->prepare("SELECT * FROM nutricion WHERE receta_id = ?");
$stmtNut->execute([$id]);
$nutricion = $stmtNut->fetch();

responder([
    "receta" => $receta,
    "ingredientes" => $ingredientes,
    "nutricion" => $nutricion ?: null
]);