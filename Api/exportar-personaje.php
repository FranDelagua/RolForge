<?php

require_once __DIR__ . "/autenticacion.php";
exigirUsuario();

require_once __DIR__ . "/conexion.php";

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
 * Comprobar que el personaje pertenece al usuario actual
 */
$consulta = $conexion->prepare(
    "SELECT * FROM characters
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
 * Obtener todos los datos relacionados
 */
$consultas = [

    "entidades" => "
        SELECT entity_type, entity_name, source, source_id
        FROM character_entities
        WHERE character_id = ?
        ORDER BY id
    ",

    "elecciones" => "
        SELECT source_type, source_id, choice_key,
               value, value_source, value_source_id
        FROM character_choices
        WHERE character_id = ?
        ORDER BY id
    ",

    "habilidades" => "
        SELECT skill, proficient, expertise, bonus
        FROM character_skills
        WHERE character_id = ?
        ORDER BY id
    ",

    "competencias" => "
        SELECT proficiency_type, name, source, source_id
        FROM character_proficiencies
        WHERE character_id = ?
        ORDER BY id
    ",

    "conjuros" => "
        SELECT name, source, source_id, prepared
        FROM character_spells
        WHERE character_id = ?
        ORDER BY id
    ",

    "objetos" => "
        SELECT name, source, source_id, quantity, equipped
        FROM character_items
        WHERE character_id = ?
        ORDER BY id
    ",

    "adicionales" => "
        SELECT data_key, data
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
 * Construir el TXT
 */
$lineas = [];

$lineas[] = "========================================";
$lineas[] = "           ROLFORGE - PERSONAJE";
$lineas[] = "========================================";
$lineas[] = "";

$lineas[] = "DATOS PRINCIPALES";
$lineas[] = "-----------------";

foreach ($personaje as $campo => $valor) {
    $lineas[] = $campo . ": " . (string)$valor;
}

$lineas[] = "";


/*
 * Datos relacionados
 */
$nombres = [
    "entidades" => "ENTIDADES",
    "elecciones" => "ELECCIONES",
    "habilidades" => "HABILIDADES",
    "competencias" => "COMPETENCIAS",
    "conjuros" => "CONJUROS",
    "objetos" => "INVENTARIO",
    "adicionales" => "DATOS ADICIONALES"
];

foreach ($datos as $grupo => $filas) {

    $lineas[] = "";
    $lineas[] = $nombres[$grupo] ?? strtoupper($grupo);
    $lineas[] = str_repeat("-", 40);

    if (!$filas) {
        $lineas[] = "(Sin datos)";
        continue;
    }

    foreach ($filas as $numero => $fila) {

        $lineas[] = "";
        $lineas[] = "Entrada " . ($numero + 1);

        foreach ($fila as $campo => $valor) {

            // Si el valor contiene JSON, intentamos mostrarlo de forma legible
            if ($campo === "data") {
                $json = json_decode($valor, true);

                if ($json !== null) {
                    $valor = json_encode(
                        $json,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_UNICODE
                    );
                }
            }

            $lineas[] = "  " . $campo . ": " . (string)$valor;
        }
    }
}

$contenido = implode(PHP_EOL, $lineas);


/*
 * Descargar archivo
 */
$nombrePersonaje = preg_replace(
    '/[^a-zA-Z0-9_-]/',
    '_',
    $personaje["name"]
);

$nombreArchivo = "personaje_" . $nombrePersonaje . ".txt";

header("Content-Type: text/plain; charset=UTF-8");
header(
    'Content-Disposition: attachment; filename="' .
    $nombreArchivo .
    '"'
);
header("Content-Length: " . strlen($contenido));

echo $contenido;
exit;