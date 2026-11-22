<?php

require_once __DIR__ . "/../Api/autenticacion.php";
exigirUsuario();
require_once __DIR__ . "/../Api/conexion.php";
require_once __DIR__ . "/../Api/ficha-personaje.php";

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
    "objetos" => "SELECT name, source, source_id, quantity, equipped FROM character_items WHERE character_id = ? ORDER BY id",
    "adicionales" => "SELECT data_key, data FROM character_data WHERE character_id = ? ORDER BY id"
];
$datos = [];
foreach ($consultas as $nombre => $sql) {
    $consulta = $conexion->prepare($sql);
    $consulta->execute([$id]);
    $datos[$nombre] = $consulta->fetchAll(PDO::FETCH_ASSOC);
}
function textoPersonaje($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}
function signoPersonaje(int $valor): string {
    return ($valor >= 0 ? "+" : "") . $valor;
}
$nombresAtributos = ["str" => "Fuerza", "dex" => "Destreza", "con" => "Constitución", "int" => "Inteligencia", "wis" => "Sabiduría", "cha" => "Carisma"];
$camposAtributos = ["str" => "strength", "dex" => "dexterity", "con" => "constitution", "int" => "intelligence", "wis" => "wisdom", "cha" => "charisma"];
$identidad = [];
$rasgos = ["class" => [], "subclass" => []];
$dotesPersonaje = [];
foreach ($datos["entidades"] as $entidad) {
    $tipo = $entidad["entity_type"];
    if (in_array($tipo, ["species", "class", "subclass", "background"], true)) $identidad[$tipo] = $entidad;
    if ($tipo === "feat") $dotesPersonaje[] = $entidad;
    if ($tipo === "feature") $rasgos[strpos($entidad["source_id"], "subclassFeature|") === 0 ? "subclass" : "class"][] = $entidad;
}
try {
    $dotes = $dotesPersonaje ? cargarDotes5e() : [];
    $archivosClase = isset($identidad["class"]) ? datosArchivoClase(["tipo" => "class", "name" => $identidad["class"]["entity_name"], "source" => $identidad["class"]["source"]]) : [];
    $especie = isset($identidad["species"]) ? cargarEspecieFicha($identidad["species"]) : [];
    $catalogoObjetos = $datos["objetos"] ? catalogoObjetosFicha() : [];
    $catalogoConjuros = catalogoConjurosFicha($datos["conjuros"]);
} catch (RuntimeException | InvalidArgumentException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    die("No se pudieron cargar los datos locales de la ficha.");
}
$adicionales = [];
foreach ($datos["adicionales"] as $dato) {
    $adicionales[$dato["data_key"]] = json_decode($dato["data"], true);
}
$competencia = 2 + intdiv(max(0, (int)$personaje["level"] - 1), 4);
$velocidad = $adicionales["especie"]["speed"] ?? null;
if (is_array($velocidad)) $velocidad = $velocidad["walk"] ?? null;
$magia = $adicionales["magia"] ?? [];
$gruposCompetencias = [];
foreach ($datos["competencias"] as $fila) {
    $clave = strtolower($fila["name"]);
    $gruposCompetencias[$fila["proficiency_type"]][$clave] = $fila["name"];
}
$gruposConjuros = [];
foreach ($datos["conjuros"] as $conjuro) {
    $entrada = $catalogoConjuros[strtolower($conjuro["name"] . "|" . $conjuro["source"])] ?? null;
    $nivelConjuro = is_int($entrada["level"] ?? null) ? $entrada["level"] : -1;
    $gruposConjuros[$nivelConjuro][] = $conjuro;
}
ksort($gruposConjuros);
$gruposObjetos = ["Armadura" => [], "Armas" => [], "Equipo" => [], "Otros" => []];
foreach ($datos["objetos"] as $objeto) {
    $entrada = $catalogoObjetos[strtolower($objeto["source_id"] ?? "")] ?? null;
    $tipo = explode("|", $entrada["type"] ?? "")[0];
    $grupo = $entrada === null ? "Otros" : (!empty($entrada["armor"]) || $tipo === "S" ? "Armadura" :
        (!empty($entrada["weaponCategory"]) || in_array($tipo, ["M", "R", "A"], true) ? "Armas" : "Equipo"));
    $gruposObjetos[$grupo][] = $objeto;
}
$eleccionesVisibles = [];
foreach ($datos["elecciones"] as $eleccion) {
    $clave = $eleccion["choice_key"];
    if (($eleccion["source_type"] === "feat" && preg_match('/^(atributos|variante)(\.|$)/', $clave)) ||
        preg_match('/^(dotes|equipo|conjuros|idiomas|habilidades|reparto)(\.|$)/', $clave)) continue;
    $valor = valorEleccionFicha($eleccion);
    if (!is_string($valor) || $valor === "") continue;
    $origen = explode("|", $eleccion["source_id"])[0];
    if (strpos($clave, "atributos.") === 0) {
        $etiqueta = "Mejora de características";
        $valor = $nombresAtributos[$valor] ?? $valor;
    } elseif (strpos($clave, "variante") === 0) {
        $etiqueta = "Rasgo elegido";
        $valor = str_replace($origen . "; ", "", $valor);
    } elseif (strpos($clave, "tamano") === 0) {
        $etiqueta = "Tamaño";
        $valor = ["S" => "Pequeño", "M" => "Mediano", "L" => "Grande"][$valor] ?? $valor;
    } else {
        $etiqueta = "Elección";
    }
    $eleccionesVisibles[$origen][] = $etiqueta . ": " . $valor;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= textoPersonaje($personaje["name"]) ?> - RolForge</title>
    <link rel="stylesheet" href="../Api/global.css">
</head>
<body>
<main class="crear-personaje">
    <header class="cabecera">
        <div class="logo">⚔ ROLFORGE ⚔</div>
        <div class="usuario">👤 <?= textoPersonaje($_SESSION["usuario"]) ?> <a href="index.php">Mis personajes</a></div>
    </header>
    <div class="formulario-personaje ficha-personaje">
        <div class="cabecera-ficha-personaje">
            <div>
                <h1><?= textoPersonaje($personaje["name"]) ?></h1>
            <p>Nivel <?= (int)$personaje["level"] ?><?= isset($identidad["class"]) ? " · " . textoPersonaje($identidad["class"]["entity_name"]) : "" ?></p>
            </div>
            <?php if ((int)$personaje["level"] < 20): ?>
                <a class="boton-subir-nivel" href="subidaNivel.php?id=<?= (int)$personaje["id"] ?>">Subir de nivel</a>
            <?php endif; ?>
                <a class="boton-exportar"
        href="../Api/exportar-personaje.php?id=<?= (int)$personaje["id"] ?>">
        Exportar
    </a>
        </div>
        <section class="seccion-creacion">
            <h2>Identidad</h2>
            <div class="ficha-rejilla">
                <?php foreach (["species" => "Especie / linaje", "class" => "Clase", "subclass" => "Subclase", "background" => "Trasfondo"] as $tipo => $titulo): ?>
                    <?php if (isset($identidad[$tipo])): ?><div><strong><?= textoPersonaje($titulo) ?></strong><p><?= textoPersonaje($identidad[$tipo]["entity_name"]) ?></p></div><?php endif; ?>
                <?php endforeach; ?>
                <div><strong>Nivel</strong><p><?= (int)$personaje["level"] ?></p></div>
                <div><strong>Experiencia</strong><p><?= (int)$personaje["experience"] ?></p></div>
            </div>
        </section>
        <section class="seccion-creacion">
            <h2>Características</h2>
            <div class="ficha-rejilla ficha-atributos">
                <?php foreach ($camposAtributos as $clave => $campo): ?>
                    <?php $valor = (int)$personaje[$campo]; ?>
                    <div><strong><?= textoPersonaje($nombresAtributos[$clave]) ?></strong><p><?= $valor ?> (<?= signoPersonaje((int)floor(($valor - 10) / 2)) ?>)</p></div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="seccion-creacion">
            <h2>Combate</h2>
            <div class="ficha-rejilla">
                <div><strong>Puntos de vida</strong><p><?= (int)$personaje["hp"] ?> / <?= (int)$personaje["max_hp"] ?></p></div>
                <div><strong>PV temporales</strong><p><?= (int)$personaje["temporary_hp"] ?></p></div>
                <div><strong>Clase de armadura</strong><p><?= (int)$personaje["armor_class"] ?></p></div>
                <?php if (is_int($velocidad)): ?><div><strong>Velocidad</strong><p><?= $velocidad ?> pies</p></div><?php endif; ?>
                <div><strong>Iniciativa</strong><p><?= signoPersonaje((int)floor(((int)$personaje["dexterity"] - 10) / 2)) ?></p></div>
                <div><strong>Competencia</strong><p><?= signoPersonaje($competencia) ?></p></div>
            </div>
        </section>
        <section class="seccion-creacion">
            <h2>Habilidades</h2>
            <p>● Competencia · ◆ Pericia</p>
            <ul class="ficha-lista ficha-habilidades">
                <?php foreach ($datos["habilidades"] as $habilidad): ?>
                    <li><span><?= textoPersonaje($habilidad["skill"]) ?></span><span><?= $habilidad["expertise"] ? "◆" : ($habilidad["proficient"] ? "●" : "") ?> <?= signoPersonaje((int)$habilidad["bonus"]) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <section class="seccion-creacion">
            <h2>Competencias</h2>
            <?php foreach (["language" => "Idiomas", "weapon" => "Armas", "armor" => "Armaduras", "saving_throw" => "Salvaciones", "tool" => "Herramientas", "instrument" => "Instrumentos", "other" => "Otras"] as $tipo => $titulo): ?>
                <?php if (!empty($gruposCompetencias[$tipo])): ?>
                    <h3><?= textoPersonaje($titulo) ?></h3>
                    <p><?= textoPersonaje(implode(", ", array_map(function ($nombre) use ($nombresAtributos) { return $nombresAtributos[strtolower($nombre)] ?? ucfirst($nombre); }, array_values($gruposCompetencias[$tipo])))) ?></p>
                    <?php unset($gruposCompetencias[$tipo]); ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php foreach ($gruposCompetencias as $grupo): ?><p><?= textoPersonaje(implode(", ", array_values($grupo))) ?></p><?php endforeach; ?>
        </section>
        <section class="seccion-creacion">
            <h2>Dotes</h2>
            <?php if (!$dotesPersonaje): ?><p>Sin dotes registradas.</p><?php endif; ?>
            <?php foreach ($dotesPersonaje as $entidad): ?>
                <?php $dote = buscarDote5e($dotes, $entidad["entity_name"], $entidad["source"]); ?>
                <article class="ficha-elemento"><h3><?= textoPersonaje($entidad["entity_name"]) ?></h3>
                    <?php if ($dote !== null): ?>
                        <?php
                        try {
                            $bonosDote = bonificacionesDoteFicha($dote, $datos["elecciones"], $entidad["source_id"]);
                        } catch (RuntimeException $error) {
                            error_log($error->getMessage());
                            $bonosDote = null;
                        }
                        ?>
                        <?php if ($bonosDote === null): ?><p>La mejora de características no se puede mostrar.</p><?php endif; ?>
                        <?php foreach ($bonosDote ?? [] as $atributo => $bonus): ?>
                            <p><?= textoPersonaje($nombresAtributos[$atributo] ?? $atributo) ?> <?= signoPersonaje((int)$bonus) ?></p>
                        <?php endforeach; ?>
                        <?php foreach ($datos["elecciones"] as $eleccion): ?>
                            <?php if ($eleccion["source_type"] === "feat" && $eleccion["source_id"] === $entidad["source_id"] && $eleccion["choice_key"] === "variante"): ?>
                                <p>Variante: <?= textoPersonaje(nombreVarianteDote5e($dote, (string)valorEleccionFicha($eleccion))) ?></p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <details><summary>Ver descripción</summary><?= renderizarEntradaDote5e($dote["entries"] ?? []) ?></details>
                    <?php else: ?><p>Descripción no disponible en los datos locales.</p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
        <section class="seccion-creacion">
            <h2>Rasgos</h2>
            <?php if (!empty($especie["entries"])): ?>
                <h3><?= textoPersonaje($identidad["species"]["entity_name"]) ?></h3>
                <?php foreach ($especie["entries"] as $entrada): ?>
                    <?php if (is_array($entrada) && !empty($entrada["name"])): ?>
                        <details class="ficha-elemento"><summary><?= textoPersonaje($entrada["name"]) ?></summary><?= renderizarEntradaDote5e($entrada["entries"] ?? $entrada["entry"] ?? []) ?></details>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!$rasgos["class"] && !$rasgos["subclass"]): ?><p>Sin rasgos registrados.</p><?php endif; ?>
            <?php foreach ($rasgos as $grupo => $lista): ?>
                <?php if ($grupo === "subclass" && $lista && isset($identidad["subclass"])): ?><h3><?= textoPersonaje($identidad["subclass"]["entity_name"]) ?></h3><?php endif; ?>
                <?php foreach ($lista as $entidad): ?>
                    <?php $rasgo = rasgoFicha($archivosClase, $entidad); ?>
                    <details class="ficha-elemento"><summary><?= textoPersonaje($entidad["entity_name"]) ?></summary>
                        <?php if ($rasgo !== null && !empty($rasgo["entries"])): ?><?= renderizarEntradaDote5e($rasgo["entries"]) ?><?php else: ?><p>Descripción no disponible en los datos locales.</p><?php endif; ?>
                    </details>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </section>
        <?php if ($eleccionesVisibles): ?>
            <section class="seccion-creacion">
                <h2>Otras elecciones</h2>
                <?php foreach ($eleccionesVisibles as $origen => $lista): ?>
                    <h3><?= textoPersonaje($origen) ?></h3>
                    <ul class="ficha-lista"><?php foreach (array_unique($lista) as $valor): ?><li><?= textoPersonaje($valor) ?></li><?php endforeach; ?></ul>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
        <?php if ($datos["conjuros"] || $magia): ?>
            <section class="seccion-creacion">
                <h2>Conjuros</h2>
                <?php $habilidadMagica = $magia["spellcastingAbility"] ?? null; ?>
                <?php if (is_string($habilidadMagica) && isset($camposAtributos[$habilidadMagica])): ?>
                    <?php $modificadorMagia = (int)floor(((int)$personaje[$camposAtributos[$habilidadMagica]] - 10) / 2); ?>
                    <div class="ficha-rejilla">
                        <div><strong>Característica de lanzamiento</strong><p><?= textoPersonaje($nombresAtributos[$habilidadMagica]) ?></p></div>
                        <div><strong>CD de salvación</strong><p><?= 8 + $competencia + $modificadorMagia ?></p></div>
                        <div><strong>Ataque de conjuro</strong><p><?= signoPersonaje($competencia + $modificadorMagia) ?></p></div>
                    </div>
                <?php endif; ?>
                <?php if (isset($magia["spellSlots"], $magia["slotLevel"])): ?><p>Espacios de nivel <?= (int)$magia["slotLevel"] ?>: <?= (int)$magia["spellSlots"] ?></p><?php endif; ?>
                <?php foreach ($gruposConjuros as $nivel => $conjuros): ?>
                    <h3><?= $nivel === 0 ? "Trucos" : ($nivel < 0 ? "Nivel desconocido" : "Nivel " . (int)$nivel) ?></h3>
                    <ul class="ficha-lista"><?php foreach ($conjuros as $conjuro): ?>
                        <?php $entradaConjuro = $catalogoConjuros[strtolower($conjuro["name"] . "|" . $conjuro["source"])] ?? null; ?>
                        <li><details><summary><?= textoPersonaje($conjuro["name"]) ?><?= $conjuro["prepared"] ? " · Preparado" : "" ?></summary>
                            <?php if ($entradaConjuro !== null && !empty($entradaConjuro["entries"])): ?><?= renderizarEntradaDote5e($entradaConjuro["entries"]) ?><?php else: ?><p>Descripción no disponible en los datos locales.</p><?php endif; ?>
                        </details></li>
                    <?php endforeach; ?></ul>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
        <section class="seccion-creacion">
            <h2>Inventario</h2>
            <?php if (!$datos["objetos"]): ?><p>Sin objetos registrados.</p><?php endif; ?>
            <?php foreach ($gruposObjetos as $grupo => $objetos): ?>
                <?php if ($objetos): ?><h3><?= textoPersonaje($grupo) ?></h3><ul class="ficha-lista">
                    <?php foreach ($objetos as $objeto): ?><li><?= textoPersonaje($objeto["name"]) ?><?= (int)$objeto["quantity"] !== 1 ? " ×" . (int)$objeto["quantity"] : "" ?><?= $objeto["equipped"] ? " · Equipado" : "" ?></li><?php endforeach; ?>
                </ul><?php endif; ?>
            <?php endforeach; ?>
        </section>
    </div>
</main>
</body>
</html>
