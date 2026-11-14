<?php

session_start();
require_once __DIR__ . "/../Api/elecciones-raza.php";
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
try {
    $datos = cargarDatosEleccionesRaza($personaje);
} catch (RuntimeException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    die("No se pudieron cargar las elecciones de la raza: " . htmlspecialchars($error->getMessage()));
}

$errores = [];
if ($_SERVER["REQUEST_METHOD"] === "POST" || (!$datos["variantes"] && !$datos["cantidadDotes"])) {
    $resultado = validarEleccionesRaza($datos, $_POST);
    $errores = $resultado["errores"];
    if (!$errores) {
        $personaje["elecciones_raza"] = $resultado["elecciones"];
        $personaje["bonificadores_raza"] = $resultado["bonificadores"];
        $personaje["raza_confirmada"] = $personaje["raza"];
        header("Location: background.php");
        exit;
    }
}

$nombresAtributos = [
    "str" => "Fuerza", "dex" => "Destreza", "con" => "Constitución",
    "int" => "Inteligencia", "wis" => "Sabiduría", "cha" => "Carisma"
];
$seleccionAnterior = $personaje["elecciones_raza"] ?? [];
$reparto = $_POST["reparto"] ?? (string)($seleccionAnterior["reparto"] ?? 0);
if (!is_string($reparto) || !ctype_digit($reparto) || !isset($datos["variantes"][(int)$reparto])) {
    $reparto = "0";
}
$atributosElegidos = $_POST["atributos_raza"] ?? ($seleccionAnterior["atributos"] ?? []);
if (!is_array($atributosElegidos)) {
    $atributosElegidos = [];
}
$dotesElegidas = $_POST["dotes_raza"] ?? ($seleccionAnterior["dotes"] ?? []);
if (!is_array($dotesElegidas)) {
    $dotesElegidas = [];
}
$opcionesDotes = [];
foreach ($datos["dotes"] as $clave => $dote) {
    $opcionesDotes[$clave] = [
        "name" => $dote["name"],
        "source" => $dote["source"],
        "descripcion" => primerTexto($dote["entries"] ?? []),
        "detalles" => []
    ];
}
$maximoAtributos = 0;
foreach ($datos["variantes"] as $variante) {
    $maximoAtributos = max($maximoAtributos, count($variante["incrementos"]));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elecciones de raza - RolForge</title>
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
        <h1>Elecciones de raza</h1>
        <p><?= htmlspecialchars($datos["raza"]["name"]) ?> — <?= htmlspecialchars($datos["raza"]["source"]) ?></p>
        <form method="POST">
            <section class="seccion-creacion">
                <?php foreach ($errores as $error): ?>
                    <p role="alert"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
                <?php if ($datos["variantes"]): ?>
                    <h2>Bonificaciones de atributos</h2>
                    <?php
                    $bonificacionesFijas = [];
                    foreach ($datos["fijos"] as $clave => $valor) {
                        if ($valor !== 0) {
                            $bonificacionesFijas[] = $nombresAtributos[$clave] . " " . ($valor > 0 ? "+" : "") . $valor;
                        }
                    }
                    ?>
                    <?php if ($bonificacionesFijas): ?>
                        <p>Bonificaciones fijas: <?= htmlspecialchars(implode(", ", $bonificacionesFijas)) ?></p>
                    <?php endif; ?>
                    <?php if (count($datos["variantes"]) > 1): ?>
                        <div class="campo">
                            <label for="reparto">Reparto</label>
                            <select id="reparto" name="reparto">
                                <?php foreach ($datos["variantes"] as $i => $variante): ?>
                                    <option value="<?= $i ?>" <?= (int)$reparto === $i ? "selected" : "" ?>>
                                        <?= htmlspecialchars(implode(" / ", array_map(function ($valor) { return "+" . $valor; }, $variante["incrementos"]))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php for ($i = 0; $i < $maximoAtributos; $i++): ?>
                        <?php $activo = isset($datos["variantes"][(int)$reparto]["incrementos"][$i]); ?>
                        <div class="campo" data-eleccion-atributo <?= $activo ? "" : "hidden" ?>>
                            <label for="atributo-raza-<?= $i ?>" data-etiqueta-atributo>
                                Atributo +<?= $activo ? (int)$datos["variantes"][(int)$reparto]["incrementos"][$i] : "" ?>
                            </label>
                            <select id="atributo-raza-<?= $i ?>" name="atributos_raza[]" required <?= $activo ? "" : "disabled" ?>>
                                <option value="">Elegir atributo</option>
                                <?php foreach ($nombresAtributos as $clave => $nombre): ?>
                                    <?php if (in_array($clave, $datos["variantes"][(int)$reparto]["opciones"], true)): ?>
                                        <option value="<?= $clave ?>" <?= ($atributosElegidos[$i] ?? "") === $clave ? "selected" : "" ?>><?= htmlspecialchars($nombre) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endfor; ?>
                <?php endif; ?>
                <?php if ($datos["cantidadDotes"]): ?>
                    <h2>Dote de raza</h2>
                    <p>La selección es provisional: los requisitos y las elecciones internas de la dote todavía no se validan.</p>
                    <?php for ($i = 0; $i < $datos["cantidadDotes"]; $i++): ?>
                        <?php mostrarSelectorFicha("dote-raza-" . $i, "Dote", $opcionesDotes, $dotesElegidas[$i] ?? "", "dotes_raza[]"); ?>
                    <?php endfor; ?>
                <?php endif; ?>
            </section>
            <div class="acciones-creacion"><button type="submit">Continuar →</button></div>
        </form>
    </section>
</main>
<script src="../Api/selector-ficha.js"></script>
<?php if (count($datos["variantes"]) > 1): ?>
<script>
    const variantesRaza = <?= json_encode($datos["variantes"], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const repartoRaza = document.getElementById("reparto");
    const camposAtributosRaza = document.querySelectorAll("[data-eleccion-atributo]");
    function actualizarRepartoRaza() {
        const variante = variantesRaza[Number(repartoRaza.value)];
        camposAtributosRaza.forEach(function (campo, indice) {
            const activo = indice < variante.incrementos.length;
            const selector = campo.querySelector("select");
            campo.hidden = !activo;
            selector.disabled = !activo;
            if (activo) {
                campo.querySelector("[data-etiqueta-atributo]").textContent =
                    "Atributo +" + variante.incrementos[indice];
                Array.from(selector.options).forEach(function (opcion) {
                    if (opcion.value) {
                        opcion.disabled = !variante.opciones.includes(opcion.value);
                    }
                });
            }
        });
    }
    repartoRaza.addEventListener("change", actualizarRepartoRaza);
    actualizarRepartoRaza();
</script>
<?php endif; ?>
</body>
</html>
