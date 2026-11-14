<?php

session_start();
require_once __DIR__ . "/selector-ficha.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.html");
    exit;
}

if (!isset($_SESSION["personaje_en_creacion"])) {
    header("Location: crear-personaje.php");
    exit;
}

$personaje = &$_SESSION["personaje_en_creacion"];
if (!isset($personaje["background"])) {
    header("Location: background.php");
    exit;
}
$claveClase = $personaje["clase"] ?? "";
$nivel = $personaje["nivel"] ?? null;

if (!is_string($claveClase) || !is_int($nivel) || $nivel < 1) {
    http_response_code(400);
    die("Los datos de la clase o el nivel del personaje no son válidos.");
}

$directorioClases = __DIR__ . "/../data/5etools/class/";
$indice = json_decode(file_get_contents($directorioClases . "index.json"), true);

if (!is_array($indice)) {
    http_response_code(500);
    die("No se pudo leer el índice de clases de 5etools.");
}

$claseSeleccionada = null;
$datosClase = null;
$claveArchivo = null;

foreach ($indice as $clave => $archivo) {
    $datos = json_decode(file_get_contents($directorioClases . $archivo), true);
    if (!is_array($datos) || !isset($datos["class"]) || !is_array($datos["class"])) {
        http_response_code(500);
        die("No se pudieron leer los datos de clases de 5etools.");
    }

    foreach ($datos["class"] as $clase) {
        if (($clase["name"] ?? "") . "|" . ($clase["source"] ?? "") === $claveClase) {
            $claseSeleccionada = $clase;
            $datosClase = $datos;
            $claveArchivo = $clave;
            break 2;
        }
    }
}

if ($claseSeleccionada === null) {
    http_response_code(400);
    die("La clase seleccionada no existe en los datos de 5etools.");
}

if (!isset($claseSeleccionada["classFeatures"]) || !is_array($claseSeleccionada["classFeatures"])) {
    http_response_code(500);
    die("La clase seleccionada no contiene características válidas.");
}

$primerNivelSubclase = null;
foreach ($claseSeleccionada["classFeatures"] as $rasgo) {
    if (!is_array($rasgo) || ($rasgo["gainSubclassFeature"] ?? false) !== true) {
        continue;
    }

    $referencia = $rasgo["classFeature"] ?? "";
    $partes = is_string($referencia) ? explode("|", $referencia) : [];
    if (!isset($partes[3]) || !ctype_digit($partes[3]) || (int)$partes[3] < 1) {
        http_response_code(500);
        die("No se pudo determinar el nivel de subclase de esta clase.");
    }

    $nivelRasgo = (int)$partes[3];
    if ($primerNivelSubclase === null || $nivelRasgo < $primerNivelSubclase) {
        $primerNivelSubclase = $nivelRasgo;
    }
}

if ($primerNivelSubclase === null || $nivel < $primerNivelSubclase) {
    unset($personaje["subclase"]);
    header("Location: crear-subclase.php");
    exit;
}

if (!isset($datosClase["subclass"]) || !is_array($datosClase["subclass"])) {
    http_response_code(500);
    die("No se pudieron leer las subclases de 5etools para esta clase.");
}

$subclases = [];
foreach ($datosClase["subclass"] as $subclase) {
    if (
        ($subclase["className"] ?? "") !== $claseSeleccionada["name"] ||
        ($subclase["classSource"] ?? "") !== $claseSeleccionada["source"] ||
        empty($subclase["name"]) || empty($subclase["source"])
    ) {
        continue;
    }

    $subclases[$subclase["name"] . "|" . $subclase["source"]] = $subclase;
}

if (!$subclases) {
    http_response_code(500);
    die("No hay subclases disponibles en los datos de 5etools para esta clase.");
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $eleccion = $_POST["subclase"] ?? "";
    if (!is_string($eleccion) || !isset($subclases[$eleccion])) {
        $error = "Selecciona una subclase válida para esta clase.";
    } else {
        $personaje["subclase"] = $eleccion;
        header("Location: crear-subclase.php");
        exit;
    }
}

$indiceFluff = json_decode(file_get_contents($directorioClases . "fluff-index.json"), true);
if (!is_array($indiceFluff) || !isset($indiceFluff[$claveArchivo])) {
    http_response_code(500);
    die("No se pudo leer el índice de descripciones de clases de 5etools.");
}
$datosFluff = json_decode(file_get_contents($directorioClases . $indiceFluff[$claveArchivo]), true);
if (!is_array($datosFluff)) {
    http_response_code(500);
    die("No se pudieron leer las descripciones de subclases de 5etools.");
}

foreach ($subclases as &$subclase) {
    $subclase["descripcion"] = "";
    foreach ($datosClase["subclassFeature"] ?? [] as $rasgo) {
        if (
            ($rasgo["name"] ?? "") === $subclase["name"] &&
            ($rasgo["className"] ?? "") === $subclase["className"] &&
            ($rasgo["classSource"] ?? "") === $subclase["classSource"] &&
            ($rasgo["subclassShortName"] ?? "") === ($subclase["shortName"] ?? "") &&
            ($rasgo["subclassSource"] ?? "") === $subclase["source"]
        ) {
            $subclase["descripcion"] = primerTexto($rasgo["entries"] ?? []);
            break;
        }
    }
    if ($subclase["descripcion"] === "") {
        foreach ($datosFluff["subclassFluff"] ?? [] as $fluff) {
            if (
                ($fluff["name"] ?? "") === $subclase["name"] &&
                ($fluff["source"] ?? "") === $subclase["source"] &&
                ($fluff["className"] ?? "") === $subclase["className"] &&
                ($fluff["classSource"] ?? "") === $subclase["classSource"]
            ) {
                $subclase["descripcion"] = primerTexto($fluff["entries"] ?? []);
                break;
            }
        }
    }
}
unset($subclase);

$rasgosPorReferencia = [];
foreach ($datosClase["subclassFeature"] ?? [] as $rasgo) {
    if (!isset($rasgo["name"], $rasgo["className"], $rasgo["classSource"], $rasgo["subclassShortName"], $rasgo["subclassSource"], $rasgo["level"], $rasgo["source"])) {
        continue;
    }
    $clave = strtolower(implode("|", [
        $rasgo["name"], $rasgo["className"], $rasgo["classSource"],
        $rasgo["subclassShortName"], $rasgo["subclassSource"],
        $rasgo["level"], $rasgo["source"]
    ]));
    $rasgosPorReferencia[$clave] = $rasgo;
}

function buscarRasgoSubclase($referencia, $rasgosPorReferencia) {
    if (is_array($referencia)) {
        $referencia = $referencia["subclassFeature"] ?? "";
    }
    if (!is_string($referencia)) {
        return null;
    }
    $partes = explode("|", $referencia);
    if (count($partes) < 6) {
        return null;
    }
    $partes[2] = $partes[2] ?: "PHB";
    $partes[4] = $partes[4] ?: "PHB";
    $partes[6] = $partes[6] ?? "";
    $partes[6] = $partes[6] ?: $partes[4];
    return $rasgosPorReferencia[strtolower(implode("|", array_slice($partes, 0, 7)))] ?? null;
}

function anadirRasgoSubclase($referencia, $rasgosPorReferencia, &$rasgos, &$visitados) {
    $rasgo = buscarRasgoSubclase($referencia, $rasgosPorReferencia);
    if ($rasgo === null) {
        return;
    }
    $clave = implode("|", [$rasgo["name"], $rasgo["className"], $rasgo["classSource"], $rasgo["subclassShortName"], $rasgo["subclassSource"], $rasgo["level"], $rasgo["source"]]);
    if (isset($visitados[$clave])) {
        return;
    }
    $visitados[$clave] = true;
    $rasgos[] = $rasgo;
    foreach ($rasgo["entries"] ?? [] as $entrada) {
        if (is_array($entrada) && ($entrada["type"] ?? "") === "refSubclassFeature") {
            anadirRasgoSubclase($entrada["subclassFeature"] ?? "", $rasgosPorReferencia, $rasgos, $visitados);
        }
    }
}

foreach ($subclases as &$subclase) {
    $subclase["rasgos"] = [];
    $visitados = [];
    foreach ($subclase["subclassFeatures"] ?? [] as $referencia) {
        anadirRasgoSubclase($referencia, $rasgosPorReferencia, $subclase["rasgos"], $visitados);
    }
    $subclase["detalles"] = [];
    foreach ($subclase["rasgos"] as $rasgo) {
        $resumen = primerTexto($rasgo["entries"] ?? []);
        $subclase["detalles"][] = [
            "nombre" => $rasgo["name"] . " — nivel " . $rasgo["level"],
            "texto" => $resumen === $subclase["descripcion"] ? "" : $resumen
        ];
    }
    $subclase = [
        "name" => $subclase["name"],
        "source" => $subclase["source"],
        "descripcion" => $subclase["descripcion"],
        "detalles" => $subclase["detalles"]
    ];
}
unset($subclase);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elegir subclase - RolForge</title>
    <link rel="stylesheet" href="../Api/global.css">
</head>
<body>
<main class="crear-personaje">
    <header class="cabecera">
        <div class="logo">⚔ ROLFORGE ⚔</div>
        <div class="usuario">
            👤 <?= htmlspecialchars($_SESSION["usuario"]) ?>
            <a href="crear-personaje.php">Volver</a>
        </div>
    </header>
    <section class="formulario-personaje">
        <h1>Elegir subclase</h1>
        <p>Clase: <?= htmlspecialchars($claseSeleccionada["name"]) ?> — <?= htmlspecialchars($claseSeleccionada["source"]) ?></p>
        <form method="POST">
            <section class="seccion-creacion">
                <h2>Subclases disponibles</h2>
                <?php if ($error !== ""): ?>
                    <p role="alert"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <?php mostrarSelectorFicha("subclase", "Subclase", $subclases, $personaje["subclase"] ?? ""); ?>
            </section>
            <div class="acciones-creacion">
                <button type="submit">Continuar →</button>
            </div>
        </form>
    </section>
</main>
<script src="../Api/selector-ficha.js"></script>
</body>
</html>
