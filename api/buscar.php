<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/funciones.php';

$db = (new Database())->getConnection();

// Parámetros de búsqueda
$q = $_GET['q'] ?? '';
$ingredientes = isset($_GET['ingredientes']) ? explode(',', $_GET['ingredientes']) : [];
$vegano = $_GET['vegano'] ?? null;
$vegetariano = $_GET['vegetariano'] ?? null;
$sin_gluten = $_GET['sin_gluten'] ?? null;
$sin_lactosa = $_GET['sin_lactosa'] ?? null;
$tiempo_max = $_GET['tiempo_max'] ?? null;
$calorias_max = $_GET['calorias_max'] ?? null;
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$limite = min(50, max(1, intval($_GET['limite'] ?? 20)));
$offset = ($pagina - 1) * $limite;

// Construcción dinámica de la consulta
$where = [];
$params = [];
$joins = [];

// Búsqueda por texto
if ($q !== '') {
    $where[] = "(r.titulo LIKE ? OR r.resumen LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

// Filtros de dieta
if ($vegano === '1')  { $where[] = "r.vegano = 1"; }
if ($vegetariano === '1') { $where[] = "r.vegetariano = 1"; }
if ($sin_gluten === '1')  { $where[] = "r.sin_gluten = 1"; }
if ($sin_lactosa === '1') { $where[] = "r.sin_lactosa = 1"; }

// Tiempo máximo
if ($tiempo_max) {
    $where[] = "(r.tiempo_preparacion + r.tiempo_coccion) <= ?";
    $params[] = (int)$tiempo_max;
}

// Calorías máximas
if ($calorias_max) {
    $joins[] = "JOIN nutricion n ON n.receta_id = r.id";
    $where[] = "n.calorias <= ?";
    $params[] = (float)$calorias_max;
}

// Filtro por ingredientes (debe contener TODOS los indicados)
if (!empty($ingredientes)) {
    $placeholders = implode(',', array_fill(0, count($ingredientes), '?'));
    $joins[] = "JOIN receta_ingredientes ri ON ri.receta_id = r.id";
    $joins[] = "JOIN ingredientes i ON i.id = ri.ingrediente_id";
    $where[] = "i.nombre IN ($placeholders)";
    $params = array_merge($params, $ingredientes);
}

// Armar SQL base
$sql = "SELECT DISTINCT r.id, r.titulo, r.imagen_url, r.tiempo_preparacion,
               r.tiempo_coccion, r.calificacion, r.vegano, r.vegetariano,
               r.sin_gluten, r.sin_lactosa
        FROM recetas r " . implode(' ', $joins);

if ($where) $sql .= " WHERE " . implode(' AND ', $where);

// Orden compatible con MySQL/MariaDB
$sql .= " ORDER BY r.calificacion DESC, r.id DESC";

// Contar total
$sqlCount = "SELECT COUNT(DISTINCT r.id) FROM recetas r " . implode(' ', $joins);
if ($where) $sqlCount .= " WHERE " . implode(' AND ', $where);
$stmtCount = $db->prepare($sqlCount);
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();

// Concatenar LIMIT y OFFSET como enteros directamente en la consulta
$sql .= " LIMIT " . (int)$limite . " OFFSET " . (int)$offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$recetas = $stmt->fetchAll();

responder([
    "total" => (int)$total,
    "pagina" => $pagina,
    "limite" => $limite,
    "filtros" => [
        "q" => $q,
        "ingredientes" => $ingredientes,
        "vegano" => $vegano,
        "vegetariano" => $vegetariano,
        "sin_gluten" => $sin_gluten,
        "tiempo_max" => $tiempo_max
    ],
    "recetas" => $recetas
]);