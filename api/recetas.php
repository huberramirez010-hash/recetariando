<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/funciones.php';

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Detalle completo de una receta
            $stmt = $db->prepare("SELECT * FROM recetas WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $receta = $stmt->fetch();
            if (!$receta) responder(["error" => "Receta no encontrada"], 404);

            // Ingredientes
            $ing = $db->prepare("SELECT i.nombre, ri.cantidad, ri.unidad, i.imagen_url
                                 FROM receta_ingredientes ri
                                 JOIN ingredientes i ON ri.ingrediente_id = i.id
                                 WHERE ri.receta_id = ?");
            $ing->execute([$_GET['id']]);
            $receta['ingredientes'] = $ing->fetchAll();

            // Nutrición
            $nut = $db->prepare("SELECT * FROM nutricion WHERE receta_id = ?");
            $nut->execute([$_GET['id']]);
            $receta['nutricion'] = $nut->fetch() ?: null;

            responder($receta);
        } else {
            // Listado con paginación
            $pagina = max(1, intval($_GET['pagina'] ?? 1));
            $limite = min(50, max(1, intval($_GET['limite'] ?? 20)));
            $offset = ($pagina - 1) * $limite;

            $total = $db->query("SELECT COUNT(*) FROM recetas")->fetchColumn();
            $stmt = $db->prepare("SELECT id, titulo, imagen_url, tiempo_preparacion, tiempo_coccion,
                                         calificacion, vegano, vegetariano, sin_gluten
                                  FROM recetas ORDER BY calificacion DESC, id DESC
                                  LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $limite, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();

            responder([
                "total" => (int)$total,
                "pagina" => $pagina,
                "limite" => $limite,
                "recetas" => $stmt->fetchAll()
            ]);
        }
        break;

    case 'POST':
        $d = obtenerDatosJSON();
        $stmt = $db->prepare("INSERT INTO recetas (titulo, imagen_url, resumen, instrucciones,
                              tiempo_preparacion, tiempo_coccion, porciones, calificacion,
                              vegano, vegetariano, sin_gluten, sin_lactosa)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $d['titulo'], $d['imagen_url'] ?? null, $d['resumen'] ?? null,
            $d['instrucciones'] ?? null, $d['tiempo_preparacion'] ?? null,
            $d['tiempo_coccion'] ?? null, $d['porciones'] ?? 1,
            $d['calificacion'] ?? null,
            $d['vegano'] ?? 0, $d['vegetariano'] ?? 0,
            $d['sin_gluten'] ?? 0, $d['sin_lactosa'] ?? 0
        ]);
        responder(["mensaje" => "Receta creada", "id" => $db->lastInsertId()], 201);
        break;

    default:
        responder(["error" => "Método no permitido"], 405);
}
