<?php

require_once __DIR__ . "/../Api/autenticacion.php";
exigirUsuario();
require_once __DIR__ . "/../Api/elecciones-raza.php";
require_once __DIR__ . "/selector-ficha.php";

prepararPersonajeEnCreacion();

$personaje = &$_SESSION["personaje_en_creacion"];
try {
    $datos = cargarDatosEleccionesRaza($personaje);
} catch (RuntimeException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    die("No se pudieron cargar las elecciones de la raza: " . htmlspecialchars($error->getMessage()));
}

$errores = [];
$mensaje = $_SESSION["mensaje_elecciones_raza"] ?? "";
unset($_SESSION["mensaje_elecciones_raza"]);
if ($_SERVER["REQUEST_METHOD"] === "POST" || (!$datos["variantes"] && !$datos["cantidadDotes"])) {
    $resultado = validarEleccionesRaza($datos, $_POST);
    $errores = $resultado["errores"];
    if (!$errores) {
        $anteriores = $personaje["raza"]["elecciones"]["dotes"] ?? [];
        $dotes = [];
        foreach ($resultado["elecciones"]["dotes"] as $posicion => $claveDote) {
            $dote = referencia5e("feat", $claveDote);
            $dote["elecciones"] = [];
            foreach ($anteriores as $anterior) {
                if (claveReferencia5e($anterior, "feat") === $claveDote) {
                    $dote["elecciones"] = $anterior["elecciones"] ?? [];
                    break;
                }
            }
            if ($resultado["elecciones_dotes"][$posicion] !== null) {
                $dote["elecciones"]["atributos"] = $resultado["elecciones_dotes"][$posicion];
            } else {
                unset($dote["elecciones"]["atributos"]);
            }
            $dotes[] = $dote;
        }
        $resultado["elecciones"]["dotes"] = $dotes;
        $personaje["raza"]["elecciones"] = $resultado["elecciones"];
        $personaje["raza"]["bonificadores"] = $resultado["bonificadores"];
        $personaje["raza"]["confirmada"] = true;
        header("Location: background.php");
        exit;
    }
}

$nombresAtributos = [
    "str" => "Fuerza", "dex" => "Destreza", "con" => "Constitución",
    "int" => "Inteligencia", "wis" => "Sabiduría", "cha" => "Carisma"
];
$seleccionAnterior = $personaje["raza"]["elecciones"] ?? [];
$reparto = $_POST["reparto"] ?? (string)($seleccionAnterior["reparto"] ?? 0);
if (!is_string($reparto) || !ctype_digit($reparto) || !isset($datos["variantes"][(int)$reparto])) {
    $reparto = "0";
}
$atributosElegidos = $_POST["atributos_raza"] ?? ($seleccionAnterior["atributos"] ?? []);
if (!is_array($atributosElegidos)) {
    $atributosElegidos = [];
}
$dotesAnteriores = array_map(function ($dote) {
    return claveReferencia5e($dote, "feat");
}, $seleccionAnterior["dotes"] ?? []);
$dotesElegidas = $_POST["dotes_raza"] ?? $dotesAnteriores;
if (!is_array($dotesElegidas)) {
    $dotesElegidas = [];
}
$eleccionesDotes = $_POST["dotes_atributos"] ?? [];
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    foreach ($seleccionAnterior["dotes"] ?? [] as $i => $dote) {
        $eleccionesDotes[$i] = $dote["elecciones"]["atributos"] ?? [];
    }
}
if (!is_array($eleccionesDotes)) {
    $eleccionesDotes = [];
}
$opcionesDotes = [];
foreach ($datos["dotes"] as $clave => $dote) {
    $opcionesDotes[$clave] = [
        "name" => $dote["name"],
        "source" => $dote["source"],
        "descripcion" => primerTexto($dote["entries"] ?? []),
        "detalles" => [],
        "atributos" => variantesAtributosDote($dote)
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
                <?php if ($mensaje !== ""): ?>
                    <p role="alert"><?= htmlspecialchars($mensaje) ?></p>
                <?php endif; ?>
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
                    <p>La selección es provisional: los requisitos y otras elecciones internas de la dote todavía no se validan.</p>
                    <?php for ($i = 0; $i < $datos["cantidadDotes"]; $i++): ?>
                        <?php mostrarSelectorFicha("dote-raza-" . $i, "Dote", $opcionesDotes, $dotesElegidas[$i] ?? "", "dotes_raza[]"); ?>
                        <div data-atributos-dote data-indice="<?= $i ?>" data-seleccion="<?= htmlspecialchars(json_encode($eleccionesDotes[$i] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8") ?>"></div>
                    <?php endfor; ?>
                <?php endif; ?>
            </section>
            <div class="acciones-creacion"><button type="submit">Continuar →</button></div>
        </form>
    </section>
</main>
<script src="../Api/selector-ficha.js"></script>
<?php if ($datos["cantidadDotes"]): ?>
<script>
    const nombresAtributosDote = <?= json_encode($nombresAtributos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    document.querySelectorAll("[data-atributos-dote]").forEach(function (contenedor) {
        const indice = contenedor.dataset.indice;
        const ficha = document.getElementById("dote-raza-" + indice);
        const opciones = JSON.parse(ficha.closest("[data-selector-ficha]").querySelector("[data-opciones]").textContent);
        let seleccion = JSON.parse(contenedor.dataset.seleccion);

        function dibujar() {
            contenedor.replaceChildren();
            const dote = opciones[ficha.value];
            if (!dote || !dote.atributos.length) return;
            const variantes = dote.atributos;
            let reparto = Number(seleccion.reparto || 0);
            if (!Number.isInteger(reparto) || !variantes[reparto]) reparto = 0;
            const titulo = document.createElement("h3");
            titulo.textContent = "Mejora de características — " + dote.name;
            contenedor.appendChild(titulo);
            if (variantes.length > 1) {
                const etiqueta = document.createElement("label");
                etiqueta.textContent = "Reparto ";
                const selector = document.createElement("select");
                selector.name = "dotes_atributos[" + indice + "][reparto]";
                variantes.forEach(function (variante, i) {
                    const cantidades = Object.values(variante.fijos).concat(variante.incrementos);
                    selector.add(new Option(cantidades.map(function (cantidad) { return "+" + cantidad; }).join(" / "), String(i)));
                });
                selector.value = String(reparto);
                selector.addEventListener("change", function () {
                    seleccion = {reparto: selector.value, atributos: []};
                    dibujar();
                });
                etiqueta.appendChild(selector);
                contenedor.appendChild(etiqueta);
            }
            const variante = variantes[reparto];
            if (variante.nota) {
                const nota = document.createElement("p");
                nota.textContent = variante.nota;
                contenedor.appendChild(nota);
            }
            const tabla = document.createElement("table");
            const encabezado = tabla.createTHead().insertRow();
            ["Mejora", "Característica"].forEach(function (texto) {
                const celda = document.createElement("th");
                celda.scope = "col";
                celda.textContent = texto;
                encabezado.appendChild(celda);
            });
            const cuerpo = tabla.createTBody();
            Object.entries(variante.fijos).forEach(function ([atributo, cantidad]) {
                const fila = cuerpo.insertRow();
                fila.insertCell().textContent = "+" + cantidad;
                fila.insertCell().textContent = nombresAtributosDote[atributo];
            });
            variante.incrementos.forEach(function (cantidad, i) {
                const fila = cuerpo.insertRow();
                fila.insertCell().textContent = "+" + cantidad;
                const selector = document.createElement("select");
                selector.name = "dotes_atributos[" + indice + "][atributos][]";
                selector.required = true;
                selector.setAttribute("aria-label", "Característica +" + cantidad + " para " + dote.name);
                selector.add(new Option("Elegir característica", ""));
                variante.opciones.forEach(function (atributo) {
                    selector.add(new Option(nombresAtributosDote[atributo], atributo));
                });
                selector.value = (seleccion.atributos || [])[i] || "";
                fila.insertCell().appendChild(selector);
            });
            contenedor.appendChild(tabla);
        }
        ficha.addEventListener("change", function () {
            seleccion = {};
            dibujar();
        });
        dibujar();
    });
</script>
<?php endif; ?>
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
