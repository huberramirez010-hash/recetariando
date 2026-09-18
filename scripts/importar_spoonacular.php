<?php
require_once __DIR__ . '/../config/database.php';

$config = require __DIR__ . '/../config/spoonacular.php';
$API_KEY = $config['api_key'];
$BASE_URL = $config['base_url'];

$db = (new Database())->getConnection();

// Valida si la API Key no fue configurada o sigue con el texto de plantilla
if (strlen($API_KEY) < 10 || strpos($API_KEY, 'PEGA_AQUI') !== false) {
    die("❌ Configura tu API Key en config/spoonacular.php\n");
}

// =====================================================
// FUNCIÓN: hacer petición a Spoonacular
// =====================================================
function spoonacularGet($endpoint, $params = []) {
    global $API_KEY, $BASE_URL;
    $params['apiKey'] = $API_KEY;
    $url = $BASE_URL . $endpoint . '?' . http_build_query($params);

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 20,
            'ignore_errors' => true
        ]
    ]);
    $json = @file_get_contents($url, false, $ctx);

    if ($json === false) return null;
    $data = json_decode($json, true);

    if (isset($data['status']) && $data['status'] === 'failure') {
        echo "⚠️  Error API: " . ($data['message'] ?? 'desconocido') . "\n";
        return null;
    }
    return $data;
}

// =====================================================
// FUNCIÓN: limpiar texto HTML
// =====================================================
function limpiar($texto) {
    if (!$texto) return null;
    return trim(strip_tags(html_entity_decode($texto, ENT_QUOTES, 'UTF-8')));
}

// =====================================================
// PASO A: Buscar recetas con filtros
// =====================================================
echo "🔍 Buscando recetas en Spoonacular...\n";

$parametrosBusqueda = [
    'number' => 20,               // máximo 100, cuidado con el límite diario
    'offset' => 0,
    'addRecipeInformation' => 'true',
    'addRecipeNutrition' => 'true',
    'instructionsRequired' => 'true',
    'fillIngredients' => 'true',
    // Filtros opcionales (descomenta los que necesites):
    // 'diet' => 'vegetarian',
    // 'intolerances' => 'gluten',
    // 'type' => 'main course',
    // 'maxReadyTime' => 30,
    // 'query' => 'chicken'
];

$resultado = spoonacularGet('/recipes/complexSearch', $parametrosBusqueda);

if (!$resultado || empty($resultado['results'])) {
    die("❌ No se obtuvieron recetas. Verifica tu API Key o el límite diario.\n");
}

$totalEncontradas = $resultado['totalResults'] ?? count($resultado['results']);
echo "✅ Se encontraron $totalEncontradas recetas disponibles.\n";
echo "📥 Procesando " . count($resultado['results']) . " recetas...\n\n";

$insertadas = 0;
$actualizadas = 0;

// =====================================================
// PASO B: Recorrer y guardar cada receta
// =====================================================
foreach ($resultado['results'] as $idx => $recetaApi) {
    $num = $idx + 1;
    echo "[$num] Procesando: " . substr($recetaApi['title'], 0, 50) . "...\n";

    if (!isset($recetaApi['analyzedInstructions'])) {
        $detalle = spoonacularGet("/recipes/{$recetaApi['id']}/information", [
            'includeNutrition' => 'true'
        ]);
        if (!$detalle) continue;
    } else {
        $detalle = $recetaApi;
    }

    $titulo = limpiar($detalle['title']);
    $imagen = $detalle['image'] ?? null;
    $resumen = limpiar($detalle['summary'] ?? '');
    $instrucciones = '';

    if (!empty($detalle['analyzedInstructions'])) {
        foreach ($detalle['analyzedInstructions'] as $sec) {
            foreach ($sec['steps'] ?? [] as $step) {
                $instrucciones .= ($step['number'] ?? '') . '. ' . limpiar($step['step']) . "\n";
            }
        }
    } elseif (!empty($detalle['instructions'])) {
        $instrucciones = limpiar($detalle['instructions']);
    }

    $db->prepare("INSERT INTO recetas
        (spoonacular_id, titulo, imagen_url, resumen, instrucciones,
         tiempo_preparacion, tiempo_coccion, porciones, calificacion,
         vegano, vegetariano, sin_gluten, sin_lactosa, saludable, muy_popular, fuente_url)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
         titulo=VALUES(titulo), imagen_url=VALUES(imagen_url), resumen=VALUES(resumen),
         instrucciones=VALUES(instrucciones), calificacion=VALUES(calificacion)")
        ->execute([
            $detalle['id'], $titulo, $imagen, $resumen, $instrucciones,
            $detalle['readyInMinutes'] ?? null,
            ($detalle['preparationMinutes'] ?? 0) + ($detalle['cookingMinutes'] ?? 0),
            $detalle['servings'] ?? 1,
            $detalle['spoonacularScore'] ? round($detalle['spoonacularScore'] / 20, 2) : null,
            !empty($detalle['vegan']) ? 1 : 0,
            !empty($detalle['vegetarian']) ? 1 : 0,
            !empty($detalle['glutenFree']) ? 1 : 0,
            !empty($detalle['dairyFree']) ? 1 : 0,
            !empty($detalle['veryHealthy']) ? 1 : 0,
            !empty($detalle['veryPopular']) ? 1 : 0,
            $detalle['sourceUrl'] ?? null
        ]);

    $idLocal = $db->prepare("SELECT id FROM recetas WHERE spoonacular_id = ?");
    $idLocal->execute([$detalle['id']]);
    $recetaId = $idLocal->fetchColumn();

    // =====================================================
    // PASO C: Guardar ingredientes
    // =====================================================
    $db->prepare("DELETE FROM receta_ingredientes WHERE receta_id = ?")->execute([$recetaId]);

    foreach ($detalle['extendedIngredients'] ?? [] as $ingApi) {
        $nombreIng = ucfirst(strtolower(trim($ingApi['name'])));

        $db->prepare("INSERT INTO ingredientes (spoonacular_id, nombre, imagen_url)
                      VALUES (?, ?, ?)
                      ON DUPLICATE KEY UPDATE nombre=VALUES(nombre)")
           ->execute([
               $ingApi['id'] ?? null,
               $nombreIng,
               "https://spoonacular.com/cdn/ingredients_100x100/" . strtolower(str_replace(' ', '-', $nombreIng)) . ".jpg"
           ]);

        $ingId = $db->prepare("SELECT id FROM ingredientes WHERE nombre = ?");
        $ingId->execute([$nombreIng]);
        $ingredienteId = $ingId->fetchColumn();

        $db->prepare("INSERT IGNORE INTO receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad)
                      VALUES (?, ?, ?, ?)")
           ->execute([
               $recetaId,
               $ingredienteId,
               $ingApi['measures']['metric']['amount'] ?? ($ingApi['amount'] ?? null),
               $ingApi['measures']['metric']['unitShort'] ?? ($ingApi['unit'] ?? null)
           ]);
    }

    // =====================================================
    // PASO D: Guardar nutrición
    // =====================================================
    $nut = $detalle['nutrition'] ?? null;
    if ($nut && !empty($nut['nutrients'])) {
        $getNutrient = function($name) use ($nut) {
            foreach ($nut['nutrients'] as $n) {
                if (strtolower($n['name']) === strtolower($name)) return $n['amount'];
            }
            return null;
        };
        $db->prepare("INSERT INTO nutricion (receta_id, calorias, proteinas, carbohidratos, grasa, fibra)
                      VALUES (?,?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE calorias=VALUES(calorias)")
           ->execute([
               $recetaId,
               $getNutrient('Calories'),
               $getNutrient('Protein'),
               $getNutrient('Carbohydrates'),
               $getNutrient('Fat'),
               $getNutrient('Fiber')
           ]);
    }

    $insertadas++;
}

echo "\n✅ ¡Importación completada!\n";
echo "   📊 Recetas procesadas: $insertadas\n";
echo "   🥕 Ingredientes disponibles en BD: " . $db->query("SELECT COUNT(*) FROM ingredientes")->fetchColumn() . "\n";
echo "   🍽️  Total de recetas en el sistema: " . $db->query("SELECT COUNT(*) FROM recetas")->fetchColumn() . "\n";