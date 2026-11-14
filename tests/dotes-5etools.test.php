<?php

require_once __DIR__ . "/../Api/dotes-5etools.php";

function comprobarDote($condicion, $mensaje) {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$dotes = cargarDotes5e();
$antigua = buscarDote5e($dotes, "Alert", "PHB");
$nueva = buscarDote5e($dotes, "Alert", "XPHB");
comprobarDote($antigua !== null && $nueva !== null && $antigua["entries"] !== $nueva["entries"], "No se distinguieron las fuentes de la dote.");
comprobarDote(buscarDote5e($dotes, "Alert", "fuente-inexistente") === null, "Se resolvió una fuente inexistente.");

$gigantes = buscarDote5e($dotes, "Strike of the Giants", "BGG");
$descripcion = renderizarEntradaDote5e($gigantes["entries"]);
comprobarDote(strpos($descripcion, "Cloud Strike") !== false && strpos($descripcion, "Storm Strike") !== false, "Faltan variantes de la dote.");
$strixhaven = buscarDote5e($dotes, "Strixhaven Initiate", "SCC");
$tabla = renderizarEntradaDote5e($strixhaven["entries"]);
comprobarDote(strpos($tabla, "<table>") !== false && strpos($tabla, "Lorehold") !== false, "No se mostró la tabla de la dote.");
$actor = buscarDote5e($dotes, "Actor", "PHB");
comprobarDote(strpos(renderizarDote5e($actor), "Carisma +1") !== false, "Falta la bonificación estructurada de la dote.");
$html = renderizarEntradaDote5e(["<script>alert(1)</script>", ["type" => "list", "items" => ["{@spell misty step}"]]]);
comprobarDote(strpos($html, "<script>") === false && strpos($html, "&lt;script&gt;") !== false && strpos($html, "<ul>") !== false && strpos($html, "misty step") !== false, "El contenido no se escapó o interpretó correctamente.");

echo "Dotes de 5etools: pruebas correctas.\n";
