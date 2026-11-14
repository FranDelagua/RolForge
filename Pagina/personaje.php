<?php

require_once __DIR__ . "/../Api/autenticacion.php";
exigirUsuario();
require_once __DIR__ . "/../Api/conexion.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if (!$id) {
    http_response_code(404);
    die("Personaje no encontrado.");
}
$consulta = $conexion->prepare("SELECT * FROM characters WHERE id = ? AND user_id = ?");
$consulta->execute([$id, $_SESSION["usuario_id"]]);
$personaje = $consulta->fetch(PDO::FETCH_ASSOC);
if (!$personaje) {
    http_response_code(404);
    die("Personaje no encontrado.");
}

$consultas = [
    "entidades" => "SELECT entity_type, entity_name, source, source_id FROM character_entities WHERE character_id = ? ORDER BY id",
    "elecciones" => "SELECT source_type, source_id, choice_key, value, value_source, value_source_id FROM character_choices WHERE character_id = ? ORDER BY id",
    "habilidades" => "SELECT skill, proficient, expertise, bonus FROM character_skills WHERE character_id = ? ORDER BY id",
    "competencias" => "SELECT proficiency_type, name, source, source_id FROM character_proficiencies WHERE character_id = ? ORDER BY id",
    "conjuros" => "SELECT name, source, source_id, prepared FROM character_spells WHERE character_id = ? ORDER BY id",
    "objetos" => "SELECT name, source, source_id, quantity, equipped FROM character_items WHERE character_id = ? ORDER BY id"
];
$datos = [];
foreach ($consultas as $nombre => $sql) {
    $consulta = $conexion->prepare($sql);
    $consulta->execute([$id]);
    $datos[$nombre] = $consulta->fetchAll(PDO::FETCH_ASSOC);
}
function textoPersonaje($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personaje - RolForge</title>
    <link rel="stylesheet" href="../Api/global.css">
</head>
<body>
<main class="crear-personaje">
    <header class="cabecera">
        <div class="logo">⚔ ROLFORGE ⚔</div>
        <div class="usuario">👤 <?= textoPersonaje($_SESSION["usuario"]) ?> <a href="index.php">Mis personajes</a></div>
    </header>
    <section class="formulario-personaje">
        <h1><?= textoPersonaje($personaje["name"]) ?></h1>
        <p>Nivel <?= (int)$personaje["level"] ?></p>
        <section class="seccion-creacion">
            <h2>Características</h2>
            <?php foreach (["strength" => "Fuerza", "dexterity" => "Destreza", "constitution" => "Constitución", "intelligence" => "Inteligencia", "wisdom" => "Sabiduría", "charisma" => "Carisma"] as $campo => $nombre): ?>
                <p><?= textoPersonaje($nombre) ?>: <?= (int)$personaje[$campo] ?></p>
            <?php endforeach; ?>
            <h2>Entidades</h2>
            <?php if (!$datos["entidades"]): ?><p>Sin entidades registradas.</p><?php endif; ?>
            <?php foreach ($datos["entidades"] as $entidad): ?>
                <p><?= textoPersonaje($entidad["entity_type"]) ?>: <?= textoPersonaje($entidad["entity_name"]) ?> (<?= textoPersonaje($entidad["source"]) ?>; <?= textoPersonaje($entidad["source_id"]) ?>)</p>
            <?php endforeach; ?>
            <h2>Elecciones</h2>
            <?php if (!$datos["elecciones"]): ?><p>Sin elecciones registradas.</p><?php endif; ?>
            <?php foreach ($datos["elecciones"] as $eleccion): ?>
                <p><?= textoPersonaje($eleccion["source_type"]) ?> / <?= textoPersonaje($eleccion["source_id"]) ?> — <?= textoPersonaje($eleccion["choice_key"]) ?>: <?= textoPersonaje($eleccion["value"]) ?><?= $eleccion["value_source"] !== null ? " (" . textoPersonaje($eleccion["value_source"]) . ")" : "" ?><?= $eleccion["value_source_id"] !== null ? " — " . textoPersonaje($eleccion["value_source_id"]) : "" ?></p>
            <?php endforeach; ?>
            <h2>Habilidades</h2>
            <?php if (!$datos["habilidades"]): ?><p>Sin habilidades registradas.</p><?php endif; ?>
            <?php foreach ($datos["habilidades"] as $habilidad): ?>
                <p><?= textoPersonaje($habilidad["skill"]) ?> — competencia: <?= $habilidad["proficient"] ? "sí" : "no" ?>; pericia: <?= $habilidad["expertise"] ? "sí" : "no" ?>; bonificador: <?= (int)$habilidad["bonus"] ?></p>
            <?php endforeach; ?>
            <h2>Competencias</h2>
            <?php if (!$datos["competencias"]): ?><p>Sin competencias registradas.</p><?php endif; ?>
            <?php foreach ($datos["competencias"] as $competencia): ?>
                <p><?= textoPersonaje($competencia["proficiency_type"]) ?>: <?= textoPersonaje($competencia["name"]) ?><?= $competencia["source"] !== null ? " (" . textoPersonaje($competencia["source"]) . ")" : "" ?></p>
            <?php endforeach; ?>
            <h2>Conjuros</h2>
            <?php if (!$datos["conjuros"]): ?><p>Sin conjuros registrados.</p><?php endif; ?>
            <?php foreach ($datos["conjuros"] as $conjuro): ?>
                <p><?= textoPersonaje($conjuro["name"]) ?> (<?= textoPersonaje($conjuro["source"]) ?>)<?= $conjuro["prepared"] ? " — preparado" : "" ?></p>
            <?php endforeach; ?>
            <h2>Inventario</h2>
            <?php if (!$datos["objetos"]): ?><p>Sin objetos registrados.</p><?php endif; ?>
            <?php foreach ($datos["objetos"] as $objeto): ?>
                <p><?= textoPersonaje($objeto["name"]) ?> × <?= (int)$objeto["quantity"] ?><?= $objeto["source"] !== null ? " (" . textoPersonaje($objeto["source"]) . ")" : "" ?><?= $objeto["equipped"] ? " — equipado" : "" ?></p>
            <?php endforeach; ?>
        </section>
    </section>
</main>
</body>
</html>
