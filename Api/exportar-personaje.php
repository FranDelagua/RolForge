<?php

require_once __DIR__ . "/autenticacion.php";
exigirUsuario();

require_once __DIR__ . "/conexion.php";


/*
 * ============================================
 * 1. OBTENER ID DEL PERSONAJE
 * ============================================
 */

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$id) {
    http_response_code(404);
    die("Personaje no encontrado.");
}


/*
 * ============================================
 * 2. COMPROBAR QUE PERTENECE AL USUARIO
 * ============================================
 */

$consulta = $conexion->prepare(
    "SELECT *
     FROM characters
     WHERE id = ? AND user_id = ?"
);

$consulta->execute([
    $id,
    $_SESSION["usuario_id"]
]);

$personaje = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$personaje) {
    http_response_code(404);
    die("Personaje no encontrado.");
}


/*
 * ============================================
 * 3. OBTENER DATOS RELACIONADOS
 * ============================================
 */

$consultas = [

    "entities" => "
        SELECT
            entity_type,
            entity_name,
            source,
            source_id
        FROM character_entities
        WHERE character_id = ?
        ORDER BY id
    ",

    "choices" => "
        SELECT
            source_type,
            source_id,
            choice_key,
            value,
            value_source,
            value_source_id
        FROM character_choices
        WHERE character_id = ?
        ORDER BY id
    ",

    "skills" => "
        SELECT
            skill,
            proficient,
            expertise,
            bonus
        FROM character_skills
        WHERE character_id = ?
        ORDER BY id
    ",

    "proficiencies" => "
        SELECT
            proficiency_type,
            name,
            source,
            source_id
        FROM character_proficiencies
        WHERE character_id = ?
        ORDER BY id
    ",

    "spells" => "
        SELECT
            name,
            source,
            source_id,
            prepared
        FROM character_spells
        WHERE character_id = ?
        ORDER BY id
    ",

    "items" => "
        SELECT
            name,
            source,
            source_id,
            quantity,
            equipped
        FROM character_items
        WHERE character_id = ?
        ORDER BY id
    ",

    "additional_data" => "
        SELECT
            data_key,
            data
        FROM character_data
        WHERE character_id = ?
        ORDER BY id
    "
];


$datos = [];

foreach ($consultas as $nombre => $sql) {

    $consulta = $conexion->prepare($sql);
    $consulta->execute([$id]);

    $datos[$nombre] = $consulta->fetchAll(PDO::FETCH_ASSOC);
}


/*
 * ============================================
 * 4. LIMPIAR DATOS DEL PERSONAJE
 * ============================================
 *
 * No exportamos datos internos de la BD.
 */

$camposOcultos = [
    "id",
    "user_id",
    "created_at",
    "updated_at"
];

$character = [];

foreach ($personaje as $campo => $valor) {

    if (in_array($campo, $camposOcultos, true)) {
        continue;
    }

    /*
     * Intentar convertir automáticamente valores
     * JSON almacenados en la BD.
     */
    if (is_string($valor)) {

        $json = json_decode($valor, true);

        if (
            json_last_error() === JSON_ERROR_NONE &&
            is_array($json)
        ) {
            $valor = $json;
        }
    }

    $character[$campo] = $valor;
}


/*
 * ============================================
 * 5. PROCESAR DATOS ADICIONALES
 * ============================================
 */

$additionalData = [];

foreach ($datos["additional_data"] as $fila) {

    $valor = $fila["data"];

    /*
     * Si "data" contiene JSON válido,
     * lo guardamos como objeto/array real.
     */
    $json = json_decode($valor, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $valor = $json;
    }

    $additionalData[$fila["data_key"]] = $valor;
}


/*
 * ============================================
 * 6. CONSTRUIR ROLFORGE JSON
 * ============================================
 */

$exportacion = [

    /*
     * Identificación del formato
     */
    "format" => "RolForge-JSON",

    "version" => "1.0",

    "target" => "foundry-vtt",


    /*
     * Datos principales
     */
    "character" => $character,


    /*
     * Entidades:
     *
     * Clase
     * Subclase
     * Especie
     * Background
     * Feats
     * Features
     * etc.
     */
    "entities" => $datos["entities"],


    /*
     * Elecciones realizadas por el jugador.
     */
    "choices" => $datos["choices"],


    /*
     * Habilidades.
     */
    "skills" => $datos["skills"],


    /*
     * Competencias.
     */
    "proficiencies" => $datos["proficiencies"],


    /*
     * Hechizos.
     */
    "spells" => $datos["spells"],


    /*
     * Inventario.
     */
    "items" => $datos["items"],


    /*
     * Información adicional.
     *
     * Aquí podemos guardar cualquier información
     * que no encaje en las estructuras anteriores.
     */
    "additional_data" => $additionalData
];


/*
 * ============================================
 * 7. GENERAR JSON
 * ============================================
 */

$json = json_encode(
    $exportacion,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

if ($json === false) {
    http_response_code(500);
    die("No se pudo generar el JSON.");
}


/*
 * ============================================
 * 8. NOMBRE DEL ARCHIVO
 * ============================================
 */

$nombrePersonaje = preg_replace(
    '/[^a-zA-Z0-9_-]/',
    '_',
    $personaje["name"] ?? "personaje"
);

$nombreArchivo = "personaje_" . $nombrePersonaje . ".json";


/*
 * ============================================
 * 9. DESCARGAR
 * ============================================
 */

header("Content-Type: application/json; charset=UTF-8");

header(
    'Content-Disposition: attachment; filename="' .
    $nombreArchivo .
    '"'
);

header("Content-Length: " . strlen($json));

echo $json;

exit;