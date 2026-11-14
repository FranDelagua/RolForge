<?php

require_once __DIR__ . "/../Api/autenticacion.php";
exigirUsuario();
require_once __DIR__ . "/selector-ficha.php";
require_once __DIR__ . "/../Api/elecciones-raza.php";

prepararPersonajeEnCreacion();

$rutaDatos = __DIR__ . "/../data/5etools/";
$datos = json_decode(file_get_contents($rutaDatos . "backgrounds.json"), true);
$fluff = json_decode(file_get_contents($rutaDatos . "fluff-backgrounds.json"), true);
if (!isset($datos["background"], $fluff["backgroundFluff"]) || !is_array($datos["background"]) || !is_array($fluff["backgroundFluff"])) {
    http_response_code(500);
    die("No se pudieron leer los trasfondos de 5etools.");
}

$descripciones = [];
foreach ($fluff["backgroundFluff"] as $entrada) {
    $descripciones[($entrada["name"] ?? "") . "|" . ($entrada["source"] ?? "")] = primerTexto($entrada["entries"] ?? []);
}

$opciones = [];
foreach ($datos["background"] as $background) {
    if (empty($background["name"]) || empty($background["source"])) {
        continue;
    }
    $clave = $background["name"] . "|" . $background["source"];
    $detalles = [];
    foreach ($background["entries"] ?? [] as $entrada) {
        if (!is_array($entrada)) {
            continue;
        }
        if (($entrada["type"] ?? "") === "list") {
            foreach ($entrada["items"] ?? [] as $item) {
                if (!empty($item["name"])) {
                    $detalles[] = ["nombre" => $item["name"], "texto" => primerTexto($item)];
                }
            }
        } elseif (!empty($entrada["data"]["isFeature"])) {
            $detalles[] = ["nombre" => $entrada["name"] ?? "Feature", "texto" => primerTexto($entrada)];
        }
    }
    if (isset($background["_copy"])) {
        $detalles[] = ["nombre" => "Datos heredados", "texto" => "Este trasfondo hace referencia a otro registro de 5etools."];
    }
    $opciones[$clave] = [
        "name" => $background["name"],
        "source" => $background["source"],
        "descripcion" => $descripciones[$clave] ?? "",
        "detalles" => $detalles
    ];
}

$personaje = &$_SESSION["personaje_en_creacion"];
exigirEleccionesRazaConfirmadas($personaje);
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $eleccion = $_POST["background"] ?? "";
    if (!is_string($eleccion) || !isset($opciones[$eleccion])) {
        $error = "Selecciona un trasfondo válido.";
    } else {
        $backgroundAnterior = isset($personaje["background"]) ? claveReferencia5e($personaje["background"], "background") : "";
        if ($backgroundAnterior !== $eleccion) {
            unset($personaje["subclase"], $personaje["elecciones"], $personaje["feats"]);
            $personaje["background"] = referencia5e("background", $eleccion);
        }
        header("Location: subclase.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elegir background - RolForge</title>
    <link rel="stylesheet" href="../Api/global.css">
</head>
<body>
<main class="crear-personaje">
    <header class="cabecera">
        <div class="logo">⚔ ROLFORGE ⚔</div>
        <div class="usuario">
            👤 <?= htmlspecialchars($_SESSION["usuario"]) ?>
            <a href="<?= !empty($personaje["raza"]["elecciones"]["atributos"]) || !empty($personaje["raza"]["elecciones"]["dotes"]) ? "elecciones-raza.php" : "crear-personaje.php" ?>">Volver</a>
        </div>
    </header>
    <section class="formulario-personaje">
        <h1>Elegir background</h1>
        <p>Selecciona el trasfondo de tu personaje.</p>
        <form method="POST">
            <section class="seccion-creacion">
                <h2>Background</h2>
                <?php if ($error !== ""): ?>
                    <p role="alert"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <?php mostrarSelectorFicha("background", "Background", $opciones, isset($personaje["background"]) ? claveReferencia5e($personaje["background"], "background") : ""); ?>
            </section>
            <div class="acciones-creacion"><button type="submit">Continuar →</button></div>
        </form>
    </section>
</main>
<script src="../Api/selector-ficha.js"></script>
</body>
</html>
