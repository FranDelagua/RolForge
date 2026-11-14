<?php

require_once __DIR__ . "/texto-5etools.php";
require_once __DIR__ . "/atributos-dotes.php";

function cargarDotes5e() {
    $ruta = __DIR__ . "/../data/5etools/feats.json";
    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        throw new RuntimeException("No se pudieron leer las dotes de 5etools.");
    }
    $datos = json_decode($contenido, true);
    if (!isset($datos["feat"]) || !is_array($datos["feat"])) {
        throw new RuntimeException("El archivo de dotes de 5etools no es válido.");
    }
    $catalogo = [];
    foreach ($datos["feat"] as $dote) {
        if (!isset($dote["name"], $dote["source"])) {
            throw new RuntimeException("Una dote no tiene nombre o fuente.");
        }
        $clave = strtolower($dote["name"] . "|" . $dote["source"]);
        if (isset($catalogo[$clave])) {
            throw new RuntimeException("Hay referencias duplicadas en el catálogo de dotes.");
        }
        $catalogo[$clave] = $dote;
    }
    return $catalogo;
}

function buscarDote5e($catalogo, $nombre, $source) {
    return $catalogo[strtolower($nombre . "|" . $source)] ?? null;
}

function renderizarEntradaDote5e($entrada) {
    if (is_string($entrada)) {
        return htmlspecialchars(primerTexto($entrada), ENT_QUOTES, "UTF-8");
    }

    if (is_numeric($entrada)) {
        return htmlspecialchars((string)$entrada, ENT_QUOTES, "UTF-8");
    }
    if (!is_array($entrada)) {
        return "";
    }
    if (array_is_list($entrada)) {
        return implode("", array_map(function ($elemento) {
            $texto = renderizarEntradaDote5e($elemento);
            return is_string($elemento) || is_numeric($elemento) ? "<p>" . $texto . "</p>" : $texto;
        }, $entrada));
    }
    $tipo = $entrada["type"] ?? "";
    if ($tipo === "list") {
        $html = "<ul>";
        foreach ($entrada["items"] ?? [] as $elemento) {
            $html .= "<li>" . renderizarEntradaDote5e($elemento) . "</li>";
        }
        return $html . "</ul>";
    }
    if ($tipo === "table") {
        $html = "<table>";
        if (!empty($entrada["caption"])) {
            $html .= "<caption>" . renderizarEntradaDote5e($entrada["caption"]) . "</caption>";
        }
        if (!empty($entrada["colLabels"])) {
            $html .= "<thead><tr>";
            foreach ($entrada["colLabels"] as $etiqueta) {
                $html .= "<th scope=\"col\">" . renderizarEntradaDote5e($etiqueta) . "</th>";
            }
            $html .= "</tr></thead>";
        }
        $html .= "<tbody>";
        foreach ($entrada["rows"] ?? [] as $fila) {
            $html .= "<tr>";
            foreach ($fila as $celda) {
                $html .= "<td>" . renderizarEntradaDote5e($celda) . "</td>";
            }
            $html .= "</tr>";
        }
        return $html . "</tbody></table>";
    }
    $html = "";
    if (!empty($entrada["name"])) {
        $html .= "<strong>" . renderizarEntradaDote5e($entrada["name"]) . "</strong> ";
    }
    foreach (["entry", "entries", "items"] as $campo) {
        if (isset($entrada[$campo])) {
            $html .= renderizarEntradaDote5e($entrada[$campo]);
        }
    }
    return $tipo === "item" ? $html : "<section>" . $html . "</section>";
}

function renderizarDote5e($dote) {
    $nombres = ["str" => "Fuerza", "dex" => "Destreza", "con" => "Constitución", "int" => "Inteligencia", "wis" => "Sabiduría", "cha" => "Carisma"];
    $variantes = variantesAtributosDote($dote);
    $html = "";
    if ($variantes) {
        $html .= "<p><strong>Mejora de características:</strong></p><ul>";
        foreach ($variantes as $variante) {
            $partes = [];
            foreach ($variante["fijos"] as $atributo => $bonus) {
                $partes[] = $nombres[$atributo] . " +" . $bonus;
            }
            if ($variante["incrementos"]) {
                $incrementos = implode(" / ", array_map(function ($valor) { return "+" . $valor; }, $variante["incrementos"]));
                $opciones = array_map(function ($atributo) use ($nombres) { return $nombres[$atributo]; }, $variante["opciones"]);
                $partes[] = $incrementos . " a elegir entre " . implode(", ", $opciones);
            }
            $html .= "<li>" . htmlspecialchars(implode("; ", $partes), ENT_QUOTES, "UTF-8") . "</li>";
        }
        $html .= "</ul>";
    }
    return $html . renderizarEntradaDote5e($dote["entries"] ?? []);
}
