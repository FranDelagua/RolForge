<?php

require_once __DIR__ . "/resolucion-personaje.php";

function cargarEspecieFicha(array $entidad): array {
    $datos = json_decode(file_get_contents(__DIR__ . "/../data/5etools/races.json"), true);
    if (!isset($datos["race"]) || !is_array($datos["race"])) throw new RuntimeException("No se pudieron leer las especies de 5etools.");
    $razas = resolverCopiasRazas($datos["race"]);
    $clave = $entidad["entity_name"] . "|" . $entidad["source"];
    if (!isset($razas[$clave])) throw new RuntimeException("No se encontró la especie " . $clave . ".");
    return $razas[$clave];
}

function valorEleccionFicha(array $eleccion) {
    if ($eleccion["value_source_id"] !== null) {
        return $eleccion["value"];
    }
    $valor = json_decode($eleccion["value"], true);
    return json_last_error() === JSON_ERROR_NONE ? $valor : $eleccion["value"];
}

function bonificacionesDoteFicha(array $dote, array $elecciones, string $origen): array {
    $variantes = variantesAtributosDote($dote);
    $reparto = 0;
    $atributos = [];
    foreach ($elecciones as $eleccion) {
        if ($eleccion["source_type"] !== "feat" || $eleccion["source_id"] !== $origen) continue;
        if ($eleccion["choice_key"] === "atributos.reparto") $reparto = (int)valorEleccionFicha($eleccion);
        if (preg_match('/^atributos\.atributos\.(\d+)$/', $eleccion["choice_key"], $m)) {
            $atributos[(int)$m[1]] = valorEleccionFicha($eleccion);
        }
    }
    if (!isset($variantes[$reparto])) return [];
    $variante = $variantes[$reparto];
    $bonos = $variante["fijos"];
    foreach ($variante["incrementos"] as $i => $cantidad) {
        $atributo = $atributos[$i] ?? null;
        if (is_string($atributo) && in_array($atributo, $variante["opciones"], true)) {
            $bonos[$atributo] = ($bonos[$atributo] ?? 0) + $cantidad;
        }
    }
    return $bonos;
}

function rasgoFicha(array $archivosClase, array $entidad): ?array {
    $partes = explode("|", $entidad["source_id"]);
    $tipo = array_shift($partes);
    if (!in_array($tipo, ["classFeature", "subclassFeature"], true)) return null;
    $nivelIndice = $tipo === "classFeature" ? 3 : 5;
    foreach ($archivosClase[$tipo] ?? [] as $rasgo) {
        if (($rasgo["name"] ?? null) !== ($partes[0] ?? null) ||
            ($rasgo["className"] ?? null) !== ($partes[1] ?? null) ||
            ($rasgo["classSource"] ?? null) !== (($partes[2] ?? "") ?: "PHB") ||
            (int)($rasgo["level"] ?? 0) !== (int)($partes[$nivelIndice] ?? 0) ||
            ($rasgo["source"] ?? null) !== $entidad["source"]) continue;
        if ($tipo === "subclassFeature" &&
            (($rasgo["subclassShortName"] ?? null) !== ($partes[3] ?? null) ||
             ($rasgo["subclassSource"] ?? null) !== ($partes[4] ?? null))) continue;
        return $rasgo;
    }
    return null;
}

function catalogoObjetosFicha(): array {
    $catalogo = [];
    foreach (["items-base.json" => "baseitem", "items.json" => "item"] as $archivo => $coleccion) {
        $datos = json_decode(file_get_contents(__DIR__ . "/../data/5etools/" . $archivo), true);
        if (!isset($datos[$coleccion]) || !is_array($datos[$coleccion])) {
            throw new RuntimeException("No se pudieron leer los objetos de 5etools.");
        }
        foreach ($datos[$coleccion] as $item) {
            if (isset($item["name"], $item["source"])) $catalogo[strtolower($item["name"] . "|" . $item["source"])] = $item;
        }
    }
    return $catalogo;
}

function catalogoConjurosFicha(array $conjuros): array {
    if (!$conjuros) return [];
    $directorio = __DIR__ . "/../data/5etools/spells/";
    $indice = json_decode(file_get_contents($directorio . "index.json"), true);
    if (!is_array($indice)) throw new RuntimeException("No se pudo leer el índice de conjuros.");
    $catalogo = [];
    $fuentes = array_unique(array_column($conjuros, "source"));
    foreach ($fuentes as $fuente) {
        if (!isset($indice[$fuente])) continue;
        $datos = json_decode(file_get_contents($directorio . $indice[$fuente]), true);
        if (!isset($datos["spell"]) || !is_array($datos["spell"])) throw new RuntimeException("No se pudieron leer los conjuros de 5etools.");
        foreach ($datos["spell"] as $conjuro) {
            if (isset($conjuro["name"], $conjuro["source"])) $catalogo[strtolower($conjuro["name"] . "|" . $conjuro["source"])] = $conjuro;
        }
    }
    return $catalogo;
}
