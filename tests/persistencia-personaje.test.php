<?php

require_once __DIR__ . "/../Api/persistencia-personaje.php";
require_once __DIR__ . "/../Api/modelo-persistencia.php";
require_once __DIR__ . "/../Api/resolucion-personaje.php";
require_once __DIR__ . "/../Api/ficha-personaje.php";

function comprobarPersistencia($condicion, $mensaje) {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$db = new PDO("sqlite::memory:", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("PRAGMA foreign_keys = ON");
$db->exec("CREATE TABLE characters (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, name TEXT NOT NULL, level INTEGER NOT NULL, experience INTEGER NOT NULL, strength INTEGER NOT NULL, dexterity INTEGER NOT NULL, constitution INTEGER NOT NULL, intelligence INTEGER NOT NULL, wisdom INTEGER NOT NULL, charisma INTEGER NOT NULL, hp INTEGER NOT NULL, max_hp INTEGER NOT NULL, temporary_hp INTEGER NOT NULL, armor_class INTEGER NOT NULL)");
$db->exec("CREATE TABLE character_entities (character_id INTEGER NOT NULL REFERENCES characters(id), entity_type TEXT NOT NULL, entity_name TEXT NOT NULL, source TEXT NOT NULL, source_id TEXT NOT NULL)");
$db->exec("CREATE TABLE character_choices (character_id INTEGER NOT NULL REFERENCES characters(id), source_type TEXT NOT NULL, source_id TEXT NOT NULL, choice_key TEXT NOT NULL, value TEXT NOT NULL, value_source TEXT, value_source_id TEXT)");
$db->exec("CREATE TABLE character_skills (character_id INTEGER NOT NULL REFERENCES characters(id), skill TEXT NOT NULL, proficient INTEGER NOT NULL, expertise INTEGER NOT NULL, bonus INTEGER NOT NULL)");
$db->exec("CREATE TABLE character_proficiencies (character_id INTEGER NOT NULL REFERENCES characters(id), proficiency_type TEXT NOT NULL, name TEXT NOT NULL, source TEXT, source_id TEXT)");
$db->exec("CREATE TABLE character_spells (character_id INTEGER NOT NULL REFERENCES characters(id), name TEXT NOT NULL, source TEXT NOT NULL, source_id TEXT NOT NULL, prepared INTEGER NOT NULL)");
$db->exec("CREATE TABLE character_items (character_id INTEGER NOT NULL REFERENCES characters(id), name TEXT NOT NULL, source TEXT, source_id TEXT, quantity INTEGER NOT NULL, equipped INTEGER NOT NULL)");
$db->exec("CREATE TABLE character_data (character_id INTEGER NOT NULL REFERENCES characters(id), data_key TEXT NOT NULL, data TEXT NOT NULL)");

$modelo = [
    "nombre" => "Prueba", "nivel" => 1, "experiencia" => 0,
    "atributos" => ["fuerza" => 10, "destreza" => 12, "constitucion" => 14, "inteligencia" => 10, "sabiduria" => 10, "carisma" => 10],
    "combate" => ["hp" => 12, "max_hp" => 12, "temporary_hp" => 0, "armor_class" => 11],
    "entidades" => [["entity_type" => "species", "entity_name" => "Elf", "source" => "PHB", "source_id" => "Elf|PHB"], ["entity_type" => "feat", "entity_name" => "Actor", "source" => "PHB", "source_id" => "Actor|PHB"]],
    "elecciones" => [["source_type" => "species", "source_id" => "Elf|PHB", "choice_key" => "ejemplo", "value" => "valor", "value_source" => null, "value_source_id" => null]],
    "habilidades" => [["skill" => "perception", "proficient" => 1, "expertise" => 0, "bonus" => 2]],
    "competencias" => [["proficiency_type" => "language", "name" => "elvish", "source" => "PHB", "source_id" => "Elf|PHB"]],
    "conjuros" => [["name" => "Shield", "source" => "PHB", "source_id" => "Shield|PHB", "prepared" => 1]],
    "objetos" => [["name" => "Longsword", "source" => "PHB", "source_id" => "Longsword|PHB", "quantity" => 1, "equipped" => 1]],
    "datos" => ["notas" => ["contenido" => "Ejemplo"]]
];
$id = guardarPersonaje($db, 7, $modelo);
comprobarPersistencia((int)$db->query("SELECT user_id FROM characters WHERE id = " . $id)->fetchColumn() === 7, "No se conservó el propietario.");
foreach (["character_choices", "character_skills", "character_proficiencies", "character_spells", "character_items", "character_data"] as $tabla) {
    comprobarPersistencia((int)$db->query("SELECT COUNT(*) FROM " . $tabla)->fetchColumn() === 1, "No se guardó " . $tabla . ".");
}
comprobarPersistencia((int)$db->query("SELECT COUNT(*) FROM character_entities")->fetchColumn() === 2, "No se guardaron todas las entidades.");
$incorrecto = $modelo;
$incorrecto["objetos"][0]["quantity"] = "valor inválido";
try {
    guardarPersonaje($db, 7, $incorrecto);
    throw new RuntimeException("Se aceptó un objeto inválido.");
} catch (InvalidArgumentException $error) {
    comprobarPersistencia((int)$db->query("SELECT COUNT(*) FROM characters")->fetchColumn() === 1, "No se revirtió la inserción principal.");
    comprobarPersistencia((int)$db->query("SELECT COUNT(*) FROM character_entities")->fetchColumn() === 2, "No se revirtieron las entidades.");
}

$linaje = cargarDatosEleccionesRaza(["raza" => "Custom Lineage|TCE"]);
$elegido = validarEleccionesRaza($linaje, ["atributos_raza" => ["wis"], "dotes_raza" => ["Actor|PHB"]]);
$personaje = [
    "nombre" => "Prueba", "nivel" => 1, "metodo_atributos" => "personalizado", "atributos" => $modelo["atributos"],
    "raza" => ["tipo" => "race", "name" => "Custom Lineage", "source" => "TCE", "confirmada" => true, "bonificadores" => $elegido["bonificadores"], "elecciones" => [
        "reparto" => 0, "atributos" => ["wis"],
        "dotes" => [["tipo" => "feat", "name" => "Actor", "source" => "PHB", "elecciones" => ["atributos" => $elegido["elecciones_dotes"][0]]]]
    ]],
    "clase" => ["tipo" => "class", "name" => "Fighter", "source" => "PHB"],
    "background" => ["tipo" => "background", "name" => "Acolyte", "source" => "PHB"],
    "resolucion" => ["combate" => $modelo["combate"], "habilidades" => [], "competencias" => [], "conjuros" => [], "objetos" => [], "entidades" => [], "elecciones" => []]
];
$proyeccion = modeloParaPersistir($personaje);
comprobarPersistencia($proyeccion["atributos"]["sabiduria"] === 12 && $proyeccion["atributos"]["carisma"] === 11, "No se aplicaron las bonificaciones.");
comprobarPersistencia(count($proyeccion["entidades"]) === 4 && $proyeccion["entidades"][3]["source_id"] === "Actor|PHB", "Faltan referencias del personaje.");
$pendientes = pendientesPersonaje($personaje);
comprobarPersistencia(count($pendientes) > 0 && count(array_filter($pendientes, function ($p) { return strpos($p["decision"], "habilidad") !== false && strpos($p["entidad"], "Fighter") !== false; })) > 0, "No se identificó la elección de clase pendiente.");

$personaje["background"] = ["tipo" => "background", "name" => "Lorehold Student", "source" => "SCC"];
$pendientes = pendientesPersonaje($personaje);
comprobarPersistencia(count(array_filter($pendientes, function ($p) {
    return $p["entidad"] === "Lorehold Student | SCC" && strpos($p["decision"], "must choose Lorehold") !== false;
})) === 1, "No se identificó la elección del trasfondo Lorehold.");

$personaje["background"] = ["tipo" => "background", "name" => "Giant Foundling", "source" => "BGG"];
$pendientes = pendientesPersonaje($personaje);
comprobarPersistencia(count(array_filter($pendientes, function ($p) {
    return $p["entidad"] === "Giant Foundling | BGG" && strpos($p["decision"], "strike of the giants|bgg") !== false;
})) === 1, "No se identificó la dote del trasfondo Giant Foundling.");

$personaje["clase"] = ["tipo" => "class", "name" => "Warlock", "source" => "PHB"];
$reglas = reglasHabilidadesClase(cargarClaseConstruccion($personaje["clase"]));
comprobarPersistencia(validarHabilidadesClase($reglas, [["arcana", "deception"]]), "No se aceptaron habilidades válidas de clase.");
comprobarPersistencia(validarHabilidadesClase($reglas, [["arcana"]], true), "No se conservó una selección parcial válida.");
comprobarPersistencia(!validarHabilidadesClase($reglas, [["arcana"]]), "Una selección parcial no debe completar la elección.");
comprobarPersistencia(!validarHabilidadesClase($reglas, [["arcana", "arcana"]], true), "No debe conservarse una selección parcial duplicada.");
comprobarPersistencia(!validarHabilidadesClase($reglas, [["arcana", "arcana"]]), "Se aceptó una habilidad duplicada.");
comprobarPersistencia(!validarHabilidadesClase($reglas, [["arcana", "perception"]]), "Se aceptó una habilidad ajena a la clase.");
comprobarPersistencia(!validarHabilidadesClase($reglas, [["arcana", "deception", "history"]]), "Se aceptaron demasiadas habilidades.");
$personaje["clase"]["elecciones"]["habilidades"] = [["arcana", "deception"]];
$pendientes = pendientesPersonaje($personaje);
comprobarPersistencia(count(array_filter($pendientes, function ($p) {
    return ($p["tipo"] ?? "") === "eleccion" && $p["entidad"] === "Warlock|PHB" && strpos($p["decision"], "habilidad") !== false;
})) === 0, "La elección de habilidades confirmada sigue pendiente.");
$background = cargarEntidadConstruccion(__DIR__ . "/../data/5etools/backgrounds.json", "background", $personaje["background"], "background");
$dotes = dotesFijasTrasfondo($background);
comprobarPersistencia(count($dotes) === 1 && $dotes[0]["name"] === "Strike of the Giants", "No se identificó la dote fija concedida.");
$variantes = variantesDote5e($dotes[0]);
comprobarPersistencia(isset($variantes["Strike of the Giants; Storm|BGG"]), "No se identificó la variante del JSON.");
comprobarPersistencia(nombreVarianteDote5e($dotes[0], $variantes["Strike of the Giants; Storm|BGG"]["name"]) === "Storm Strike", "No se mostró el nombre legible de la variante.");
$personaje["background"]["elecciones"]["variantes_dotes"] = ["Strike of the Giants; Storm|BGG"];
$personaje["subclase"] = ["tipo" => "subclass", "name" => "The Hexblade", "source" => "XGE", "shortName" => "Hexblade", "className" => "Warlock", "classSource" => "PHB"];
$personaje["resolucion"] = resolverPersonaje($personaje)["resolucion"];
comprobarPersistencia(($personaje["resolucion"]["datos"]["magia"]["spellSlots"] ?? null) === 1 &&
    ($personaje["resolucion"]["datos"]["magia"]["slotLevel"] ?? null) === 1,
    "No se resolvieron los espacios de conjuro de nivel 1.");
$proyeccion = modeloParaPersistir($personaje);
comprobarPersistencia(count(array_filter($proyeccion["elecciones"], function ($e) {
    return $e["source_type"] === "class" && $e["source_id"] === "Warlock|PHB" && $e["choice_key"] === "habilidades.0.0" && $e["value"] === '"arcana"';
})) === 1, "No se proyectó la elección de clase para persistir.");
comprobarPersistencia(count(array_filter($proyeccion["entidades"], function ($e) {
    return $e["entity_type"] === "feat" && $e["source_id"] === "Strike of the Giants|BGG";
})) === 1, "No se proyectó la dote concedida por el trasfondo.");
comprobarPersistencia(count(array_filter($proyeccion["elecciones"], function ($e) {
    return $e["source_type"] === "feat" && $e["source_id"] === "Strike of the Giants|BGG" &&
        $e["choice_key"] === "variante" && $e["value_source_id"] === "Strike of the Giants; Storm|BGG";
})) === 1, "No se proyectó la referencia de la variante seleccionada.");
foreach (["Pact Magic", "Otherworldly Patron", "Hex Warrior", "Hexblade's Curse"] as $nombreRasgo) {
    comprobarPersistencia(count(array_filter($proyeccion["entidades"], function ($e) use ($nombreRasgo) {
        return $e["entity_type"] === "feature" && $e["entity_name"] === $nombreRasgo;
    })) === 1, "No se proyectó el rasgo " . $nombreRasgo . ".");
}
foreach (["Arcana", "Deception", "Intimidation", "Survival"] as $nombreHabilidad) {
    comprobarPersistencia(count(array_filter($proyeccion["habilidades"], function ($h) use ($nombreHabilidad) {
        return $h["skill"] === $nombreHabilidad && $h["proficient"] === 1;
    })) === 1, "No se resolvió la competencia " . $nombreHabilidad . ".");
}
$idProyectado = guardarPersonaje($db, 7, $proyeccion);
$consulta = $db->prepare("SELECT COUNT(*) FROM character_entities WHERE character_id = ? AND entity_type = ? AND source_id = ?");
$consulta->execute([$idProyectado, "feat", "Strike of the Giants|BGG"]);
comprobarPersistencia((int)$consulta->fetchColumn() === 1, "No se guardó la dote concedida.");
$consulta = $db->prepare("SELECT value_source_id FROM character_choices WHERE character_id = ? AND source_type = ? AND choice_key = ?");
$consulta->execute([$idProyectado, "feat", "variante"]);
comprobarPersistencia($consulta->fetchColumn() === "Strike of the Giants; Storm|BGG", "No se guardó la variante de dote.");

$elegido = validarEleccionesRaza($linaje, ["atributos_raza" => ["wis"], "dotes_raza" => ["Elven Accuracy|XGE"], "dotes_atributos" => [["atributos" => ["cha"]]]]);
comprobarPersistencia(!$elegido["errores"], "No se pudo seleccionar la dote de prueba.");
$personaje["raza"]["bonificadores"] = $elegido["bonificadores"];
$personaje["raza"]["elecciones"]["dotes"] = [["tipo" => "feat", "name" => "Elven Accuracy", "source" => "XGE", "elecciones" => ["atributos" => $elegido["elecciones_dotes"][0]]]];
$pendientes = pendientesPersonaje($personaje);
comprobarPersistencia(count(array_filter($pendientes, function ($p) {
    return ($p["tipo"] ?? "") === "invalido" || strpos($p["decision"], "Requisito de especie") !== false;
})) === 0, "No se deben bloquear los requisitos de legalidad.");

$personaje["elecciones_adicionales"] = [];
for ($intento = 0; $intento < 10; $intento++) {
    $resultado = resolverPersonaje($personaje);
    $cambios = 0;
    foreach ($resultado["decisiones"] as $decision) {
        if ($decision["seleccion"]) {
            continue;
        }
        $opciones = array_keys($decision["opciones"]);
        if ($decision["titulo"] === "Elegir rasgo de especie") {
            $opciones = ["Custom Lineage; Darkvision|TCE"];
        } elseif ($decision["titulo"] === "Elegir objeto: weaponSimple") {
            $opciones = ["Dagger|PHB"];
        } elseif ($decision["titulo"] === "Elegir trucos conocidos" && isset($decision["opciones"]["Eldritch Blast|PHB"])) {
            $opciones = array_values(array_unique(array_merge(["Eldritch Blast|PHB"], $opciones)));
        } elseif ($decision["titulo"] === "Elegir conjuros conocidos" && isset($decision["opciones"]["Hex|PHB"])) {
            $opciones = array_values(array_unique(array_merge(["Hex|PHB"], $opciones)));
        }
        $personaje["elecciones_adicionales"][$decision["id"]] = array_slice($opciones, 0, $decision["cantidad"]);
        $cambios++;
    }
    if (!$cambios) {
        break;
    }
}
$resultado = resolverPersonaje($personaje);
comprobarPersistencia(!$resultado["elecciones"], "El personaje con decisiones válidas sigue bloqueado.");
$personajeParcial = $personaje;
foreach ($resultado["decisiones"] as $decision) {
    if ($decision["cantidad"] > 1) {
        $personajeParcial["elecciones_adicionales"][$decision["id"]] = array_slice($decision["seleccion"], 0, 1);
        $parcial = resolverPersonaje($personajeParcial);
        comprobarPersistencia(count($parcial["decisiones"][$decision["id"]]["seleccion"]) === 1, "No se conservó la selección estructurada parcial.");
        comprobarPersistencia((bool)$parcial["elecciones"], "Una selección estructurada parcial no debe permitir guardar.");
        break;
    }
}
comprobarPersistencia((bool)$resultado["advertencias"], "Deben conservarse las advertencias no bloqueantes.");
comprobarPersistencia(count($resultado["resolucion"]["conjuros"]) === 4, "No se resolvieron los conjuros elegidos.");
comprobarPersistencia(count($resultado["resolucion"]["objetos"]) > 0, "No se resolvió el equipo elegido.");
$personaje["resolucion"] = $resultado["resolucion"];
$proyeccion = modeloParaPersistir($personaje);
$idIntegrado = guardarPersonaje($db, 7, $proyeccion);
$doteElven = buscarDote5e(cargarDotes5e(), "Elven Accuracy", "XGE");
comprobarPersistencia(bonificacionesDoteFicha($doteElven, $proyeccion["elecciones"], "Elven Accuracy|XGE") === ["cha" => 1], "La ficha no recuperó la mejora elegida de la dote.");
$datosClaseFicha = datosArchivoClase($personaje["clase"]);
$pact = array_values(array_filter($proyeccion["entidades"], function ($e) { return $e["entity_type"] === "feature" && $e["entity_name"] === "Pact Magic"; }))[0];
comprobarPersistencia(!empty(rasgoFicha($datosClaseFicha, $pact)["entries"]), "La ficha no recuperó la descripción del rasgo.");
$conjurosFicha = catalogoConjurosFicha($proyeccion["conjuros"]);
comprobarPersistencia(($conjurosFicha["eldritch blast|phb"]["level"] ?? null) === 0, "La ficha no recuperó el nivel del truco.");
$consulta = $db->prepare("SELECT COUNT(*) FROM character_spells WHERE character_id = ?");
$consulta->execute([$idIntegrado]);
comprobarPersistencia((int)$consulta->fetchColumn() === 4, "No se guardaron los conjuros seleccionados.");
$consulta = $db->prepare("SELECT COUNT(*) FROM character_items WHERE character_id = ? AND source_id = ?");
$consulta->execute([$idIntegrado, "Dagger|PHB"]);
comprobarPersistencia((int)$consulta->fetchColumn() > 0, "No se guardó el equipo elegido.");
$consulta = $db->prepare("SELECT COUNT(*) FROM character_skills WHERE character_id = ? AND skill = ? AND proficient = 1");
$consulta->execute([$idIntegrado, "Arcana"]);
comprobarPersistencia((int)$consulta->fetchColumn() === 1, "No se guardó la competencia de clase.");

$varianteHabilidad = $personaje;
foreach ($resultado["decisiones"] as $decision) {
    if ($decision["titulo"] === "Elegir rasgo de especie") {
        $varianteHabilidad["elecciones_adicionales"][$decision["id"]] = ["Custom Lineage; Skill Proficiency|TCE"];
    }
}
$resultadoVariante = resolverPersonaje($varianteHabilidad);
$decisionHabilidad = null;
foreach ($resultadoVariante["decisiones"] as $decision) {
    if ($decision["titulo"] === "Elegir habilidad de especie") {
        $decisionHabilidad = $decision;
    }
}
comprobarPersistencia($decisionHabilidad !== null && isset($decisionHabilidad["opciones"]["perception"]), "No se ofreció la habilidad de la variante racial.");
$varianteHabilidad["elecciones_adicionales"][$decisionHabilidad["id"]] = ["perception"];
$resultadoVariante = resolverPersonaje($varianteHabilidad);
comprobarPersistencia(count(array_filter($resultadoVariante["resolucion"]["habilidades"], function ($h) {
    return $h["skill"] === "Perception" && $h["proficient"] === 1;
})) === 1, "No se incorporó la habilidad racial elegida.");

echo "Persistencia de personaje: pruebas correctas (SQLite en memoria).\n";
