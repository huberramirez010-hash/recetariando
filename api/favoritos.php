<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/funciones.php';

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$usuarioId = obtenerUsuarioId();

if (!$usuarioId) responder(["error" => "Falta X-User-Id en headers"], 401);

switch ($method) {
    case 'GET':
        $stmt = $db->prepare("SELECT r.id, r.titulo, r.imagen_url, r.calificacion,
                                     f.guardado_en
                              FROM favoritos f
                              JOIN recetas r ON f.receta_id = r.id
                              WHERE f.usuario_id = ?
                              ORDER BY f.guardado_en DESC");
        $stmt->execute([$usuarioId]);
        responder($stmt->fetchAll());
        break;

    case 'POST':
        $d = obtenerDatosJSON();
        if (!isset($d['receta_id'])) responder(["error" => "Falta receta_id"], 400);
        try {
            $stmt = $db->prepare("INSERT INTO favoritos (usuario_id, receta_id) VALUES (?, ?)");
            $stmt->execute([$usuarioId, $d['receta_id']]);
            responder(["mensaje" => "Receta agregada a favoritos"], 201);
        } catch (PDOException $e) {
            responder(["error" => "Ya está en favoritos"], 409);
        }
        break;
    case 'DELETE':
        if (!isset($_GET['receta_id'])) responder(["error" => "Falta receta_id"], 400);
        $stmt = $db->prepare("DELETE FROM favoritos WHERE usuario_id = ? AND receta_id = ?");
        $stmt->execute([$usuarioId, $_GET['receta_id']]);
        responder(["mensaje" => "Receta eliminada de favoritos"]);
        break;
}
