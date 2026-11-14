<?php

require_once __DIR__ . "/../Api/autenticacion.php";
exigirUsuario();
require_once __DIR__ . "/../Api/elecciones-raza.php";
require_once __DIR__ . "/../Api/resolucion-personaje.php";
require_once __DIR__ . "/selector-multiple.php";

/*
 * Comprobar que existe un personaje en creación
 */

prepararPersonajeEnCreacion();


$personaje = $_SESSION["personaje_en_creacion"];
exigirEleccionesRazaConfirmadas($personaje);
if (!isset($_SESSION["token_guardar_personaje"])) {
    $_SESSION["token_guardar_personaje"] = bin2hex(random_bytes(32));
}
try {
    $clase = cargarClaseConstruccion($personaje["clase"]);
    $background = cargarEntidadConstruccion(__DIR__ . "/../data/5etools/backgrounds.json", "background", $personaje["background"], "background");
    $variantesDotes = [];
    foreach (dotesFijasTrasfondo($background) as $indice => $dote) {
        $variantes = variantesDote5e($dote);
        if ($variantes) {
            $variantesDotes[$indice] = ["dote" => $dote, "opciones" => $variantes];
        }
    }
    try {
        $reglasHabilidades = reglasHabilidadesClase($clase);
    } catch (RuntimeException $error) {
        $reglasHabilidades = [];
    }
    $resultado = resolverPersonaje($personaje);
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (!is_string($_POST["token"] ?? null) || !hash_equals($_SESSION["token_guardar_personaje"], $_POST["token"])) {
            http_response_code(403);
            die("La solicitud no es válida.");
        }
        if (($_POST["accion"] ?? "") === "habilidades") {
            $seleccion = $_POST["habilidades"] ?? [];
            if (!$reglasHabilidades || !validarHabilidadesClase($reglasHabilidades, $seleccion, true)) {
                $_SESSION["error_guardar_personaje"] = "Selecciona la cantidad requerida de habilidades distintas y válidas.";
            } else {
                $_SESSION["personaje_en_creacion"]["clase"]["elecciones"]["habilidades"] = $seleccion;
            }
        } elseif (($_POST["accion"] ?? "") === "variante_dote") {
            $indice = $_POST["indice_dote"] ?? null;
            $seleccion = $_POST["variante"] ?? null;
            if (!is_string($indice) || !ctype_digit($indice) || !isset($variantesDotes[(int)$indice]) ||
                !is_string($seleccion) || !isset($variantesDotes[(int)$indice]["opciones"][$seleccion])) {
                $_SESSION["error_guardar_personaje"] = "Selecciona una variante válida de la dote.";
            } else {
                $_SESSION["personaje_en_creacion"]["background"]["elecciones"]["variantes_dotes"][(int)$indice] = $seleccion;
            }
        } elseif (($_POST["accion"] ?? "") === "decision_estructurada") {
            $id = $_POST["decision_id"] ?? null;
            $seleccion = $_POST["valores"] ?? [];
            if ($seleccion === [""]) {
                $seleccion = [];
            }
            $decision = is_string($id) ? ($resultado["decisiones"][$id] ?? null) : null;
            $valida = is_array($decision) && is_array($seleccion) && array_is_list($seleccion) &&
                count($seleccion) <= $decision["cantidad"] && count(array_filter($seleccion, "is_string")) === count($seleccion) &&
                count(array_unique($seleccion, SORT_REGULAR)) === count($seleccion);
            if ($valida) {
                foreach ($seleccion as $valor) {
                    if (!is_string($valor) || !isset($decision["opciones"][$valor])) {
                        $valida = false;
                        break;
                    }
                }
            }
            if (!$valida) {
                $_SESSION["error_guardar_personaje"] = "Selecciona la cantidad requerida de opciones válidas.";
            } else {
                $_SESSION["personaje_en_creacion"]["elecciones_adicionales"][$id] = $seleccion;
            }
        } else {
            http_response_code(400);
            die("La elección no es válida.");
        }
        if (($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "XMLHttpRequest") {
            header("Content-Type: application/json; charset=UTF-8");
            if (isset($_SESSION["error_guardar_personaje"])) {
                http_response_code(400);
                echo json_encode(["error" => $_SESSION["error_guardar_personaje"]], JSON_THROW_ON_ERROR);
                unset($_SESSION["error_guardar_personaje"]);
            } else {
                $actualizado = resolverPersonaje($_SESSION["personaje_en_creacion"]);
                echo json_encode(["pendientes" => $actualizado["elecciones"], "decisiones" => array_keys($actualizado["decisiones"])], JSON_THROW_ON_ERROR);
            }
            exit;
        }
        header("Location: crear-subclase.php");
        exit;
    }
    $eleccionesPendientes = $resultado["elecciones"];
    $reglasNoSoportadas = $resultado["advertencias"];
} catch (InvalidArgumentException $error) {
    error_log($error->getMessage());
    http_response_code(400);
    die("No se pudieron comprobar los datos del personaje.");
} catch (RuntimeException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    die("No se pudieron comprobar las reglas del personaje.");
}
$errorGuardado = $_SESSION["error_guardar_personaje"] ?? "";
unset($_SESSION["error_guardar_personaje"]);
$habilidadesElegidas = $personaje["clase"]["elecciones"]["habilidades"] ?? [];
function etiquetaDecisionResumen(string $texto): string {
    $texto = preg_replace('/\|[a-zA-Z0-9-]+/', '', $texto);
    return str_replace(["weaponSimple", "weaponMartial", "focusSpellcastingArcane"], ["arma sencilla", "arma marcial", "foco arcano"], $texto);
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Crear personaje - RolForge</title>

    <link
        rel="stylesheet"
        href="../Api/global.css"
    >

</head>


<body>

<main class="crear-personaje">

    <header class="cabecera">

        <div class="logo">
            ⚔ ROLFORGE ⚔
        </div>

        <div class="usuario">

            👤 <?= htmlspecialchars($_SESSION["usuario"]) ?>

            <a href="crear-personaje.php">
                Volver
            </a>

        </div>

    </header>


    <section class="formulario-personaje">

        <h1>Crear personaje</h1>

        <p id="indicacion-resumen">
            <?= $eleccionesPendientes ? "Datos guardados temporalmente. Revisa las elecciones pendientes." : "Todas las elecciones están completas. Revisa el personaje antes de guardarlo." ?>
        </p>


        <section class="seccion-creacion">

            <h2>Resumen</h2>

            <p>
                Nombre:
                <strong>
                    <?= htmlspecialchars($personaje["nombre"]) ?>
                </strong>
            </p>

            <p>
                Nivel:
                <strong>
                    <?= $personaje["nivel"] ?>
                </strong>
            </p>

            <p>
                Raza:
                <strong>
                    <?= htmlspecialchars($personaje["raza"]["name"]) ?>
                </strong>
            </p>

            <p>
                Clase:
                <strong>
                    <?= htmlspecialchars($personaje["clase"]["name"]) ?>
                </strong>
            </p>

            <?php if (isset($personaje["background"])): ?>
                <p>
                    Trasfondo:
                    <strong>
                        <?= htmlspecialchars($personaje["background"]["name"]) ?>
                    </strong>
                </p>
            <?php endif; ?>

            <?php if (isset($personaje["subclase"])): ?>
                <p>
                    Subclase:
                    <strong>
                        <?= htmlspecialchars($personaje["subclase"]["name"]) ?>
                    </strong>
                </p>
            <?php endif; ?>

            <p>
                Método:
                <strong>
                    <?= htmlspecialchars($personaje["metodo_atributos"]) ?>
                </strong>
            </p>

            <h2>Atributos</h2>

            <?php $clavesAtributos = [
                "fuerza" => "str", "destreza" => "dex", "constitucion" => "con",
                "inteligencia" => "int", "sabiduria" => "wis", "carisma" => "cha"
            ]; ?>
            <?php $nombresAtributos = [
                "str" => "Fuerza", "dex" => "Destreza", "con" => "Constitución",
                "int" => "Inteligencia", "wis" => "Sabiduría", "cha" => "Carisma"
            ]; ?>
            <?php $bonusDotes = [];
            foreach ($personaje["raza"]["elecciones"]["dotes"] ?? [] as $dote) {
                foreach ($dote["elecciones"]["atributos"]["bonificadores"] ?? [] as $atributo => $bonus) {
                    $bonusDotes[$atributo] = ($bonusDotes[$atributo] ?? 0) + $bonus;
                }
            } ?>
            <?php foreach ($personaje["atributos"] as $nombre => $valor): ?>

                <p>
                    <?= ucfirst(htmlspecialchars($nombre)) ?>:
                    <strong>
                        <?= (int)$valor + (int)($personaje["raza"]["bonificadores"][$clavesAtributos[$nombre] ?? ""] ?? 0) + (int)($bonusDotes[$clavesAtributos[$nombre] ?? ""] ?? 0) ?>
                    </strong>
                </p>

            <?php endforeach; ?>

            <?php if (!empty($personaje["raza"]["elecciones"]["dotes"])): ?>
                <h2>Dotes de especie</h2>
                <?php foreach ($personaje["raza"]["elecciones"]["dotes"] as $dote): ?>
                    <p><?= htmlspecialchars($dote["name"]) ?></p>
                    <?php foreach ($dote["elecciones"]["atributos"]["bonificadores"] ?? [] as $atributo => $bonus): ?>
                        <p><?= htmlspecialchars($nombresAtributos[$atributo] ?? $atributo) ?> +<?= (int)$bonus ?></p>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>

        </section>

        <?php if ($reglasHabilidades): ?>
            <section class="seccion-creacion">
                <h2>Habilidades de clase</h2>
                <form method="post" action="crear-subclase.php" data-guardar-eleccion>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION["token_guardar_personaje"], ENT_QUOTES, "UTF-8") ?>">
                    <input type="hidden" name="accion" value="habilidades">
                    <?php foreach ($reglasHabilidades as $indice => $regla): ?>
                        <?php $opcionesHabilidades = [];
                        foreach ($regla["opciones"] as $opcion) {
                            $opcionesHabilidades[$opcion] = ucfirst($opcion);
                        }
                        mostrarSelectorMultiple("habilidades[" . (int)$indice . "][]", $opcionesHabilidades, $habilidadesElegidas[$indice] ?? [], $regla["cantidad"], $regla["cantidad"], "Elige " . $regla["cantidad"] . " habilidad(es)"); ?>
                    <?php endforeach; ?>
                    <noscript><button type="submit">Guardar selección</button></noscript>
                </form>
            </section>
        <?php endif; ?>

        <?php foreach ($variantesDotes as $indice => $grupo): ?>
            <section class="seccion-creacion">
                <h2><?= htmlspecialchars($grupo["dote"]["name"]) ?> — elegir variante</h2>
                <form method="post" action="crear-subclase.php" data-guardar-eleccion>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION["token_guardar_personaje"], ENT_QUOTES, "UTF-8") ?>">
                    <input type="hidden" name="accion" value="variante_dote">
                    <input type="hidden" name="indice_dote" value="<?= (int)$indice ?>">
                    <fieldset>
                        <legend>Elige una variante</legend>
                        <?php foreach ($grupo["opciones"] as $clave => $variante): ?>
                            <label>
                                <input type="radio" name="variante" value="<?= htmlspecialchars($clave, ENT_QUOTES, "UTF-8") ?>" <?= ($personaje["background"]["elecciones"]["variantes_dotes"][$indice] ?? null) === $clave ? "checked" : "" ?>>
                                <?= htmlspecialchars(nombreVarianteDote5e($grupo["dote"], $variante["name"])) ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <noscript><button type="submit">Guardar selección</button></noscript>
                </form>
            </section>
        <?php endforeach; ?>

        <?php foreach ($resultado["decisiones"] as $decision): ?>
            <section class="seccion-creacion" data-id-decision="<?= htmlspecialchars($decision["id"], ENT_QUOTES, "UTF-8") ?>">
                <h2><?= htmlspecialchars(explode("|", $decision["entidad"])[0]) ?> — <?= htmlspecialchars(etiquetaDecisionResumen($decision["titulo"])) ?></h2>
                <form method="post" action="crear-subclase.php" data-guardar-eleccion>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION["token_guardar_personaje"], ENT_QUOTES, "UTF-8") ?>">
                    <input type="hidden" name="accion" value="decision_estructurada">
                    <input type="hidden" name="decision_id" value="<?= htmlspecialchars($decision["id"]) ?>">
                    <?php if ($decision["cantidad"] > 1): ?>
                        <?php mostrarSelectorMultiple("valores[]", array_map("etiquetaDecisionResumen", $decision["opciones"]), $decision["seleccion"], $decision["cantidad"], $decision["cantidad"], "Selecciona " . $decision["cantidad"] . " opciones"); ?>
                    <?php else: ?>
                        <label>Selecciona una opción
                        <select name="valores[]">
                            <option value="">Selecciona una opción</option>
                            <?php foreach ($decision["opciones"] as $valor => $etiqueta): ?>
                                <option value="<?= htmlspecialchars($valor, ENT_QUOTES, "UTF-8") ?>" <?= in_array($valor, $decision["seleccion"], true) ? "selected" : "" ?>><?= htmlspecialchars(etiquetaDecisionResumen($etiqueta)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </label>
                    <?php endif; ?>
                    <noscript><button type="submit">Guardar selección</button></noscript>
                </form>
            </section>
        <?php endforeach; ?>

        <?php if ($errorGuardado !== ""): ?>
            <p role="alert"><?= htmlspecialchars($errorGuardado) ?></p>
        <?php endif; ?>
        <section class="seccion-creacion" id="estado-elecciones" aria-live="polite">
            <?php if ($eleccionesPendientes): ?>
                <p>El personaje no puede guardarse todavía.</p>
                <h2>Decisiones pendientes</h2>
                <ul><?php foreach ($eleccionesPendientes as $pendiente): ?>
                    <li><strong><?= htmlspecialchars(explode("|", $pendiente["entidad"])[0]) ?></strong> — <?= htmlspecialchars(etiquetaDecisionResumen($pendiente["decision"])) ?>. <?= htmlspecialchars($pendiente["motivo"]) ?></li>
                <?php endforeach; ?></ul>
            <?php else: ?>
                <h2>Personaje completo</h2><p>Todas las elecciones están completas.</p>
            <?php endif; ?>
        </section>
        <p id="estado-guardado-eleccion" role="status" aria-live="polite"></p>
        <section class="acciones-creacion">
            <form action="background.php" method="get"><button type="submit">← Volver a editar</button></form>
            <form method="post" action="../Api/guardar-personaje.php" id="formulario-guardar-personaje">
                <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION["token_guardar_personaje"], ENT_QUOTES, "UTF-8") ?>">
                <button type="submit" <?= $eleccionesPendientes ? "disabled" : "" ?>>Confirmar y guardar</button>
            </form>
        </section>

    </section>

</main>

<script src="../Api/selector-multiple.js"></script>
<script src="../Api/resumen-personaje.js"></script>
</body>

</html>