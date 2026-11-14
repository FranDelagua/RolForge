<?php

require_once __DIR__ . "/modelo-personaje.php";
require_once __DIR__ . "/resolucion-personaje.php";

function identificadorEntidad5e(array $entidad): string {
    $tipo = $entidad["tipo"] ?? "";
    $clave = claveReferencia5e($entidad, $tipo);
    if ($tipo === "subclass") {
        $identidad = referenciaSubclase5e($entidad);
        $clave .= "|" . $identidad["className"] . "|" . $identidad["classSource"] . "|" . $identidad["shortName"];
    }
    return $clave;
}

function anadirEleccionesPersistibles(array &$salida, string $tipo, string $origen, $valor, string $ruta): void {
    if (is_array($valor)) {
        if (isset($valor["tipo"], $valor["name"], $valor["source"])) {
            $salida[] = [
                "source_type" => $tipo, "source_id" => $origen, "choice_key" => $ruta,
                "value" => $valor["name"], "value_source" => $valor["source"],
                "value_source_id" => identificadorEntidad5e($valor)
            ];
            return;
        }
        foreach ($valor as $clave => $elemento) {
            anadirEleccionesPersistibles($salida, $tipo, $origen, $elemento, $ruta === "" ? (string)$clave : $ruta . "." . $clave);
        }
        return;
    }
    if ($ruta === "" || (!is_scalar($valor) && $valor !== null)) {
        throw new InvalidArgumentException("La elección del personaje no es válida.");
    }
    $salida[] = [
        "source_type" => $tipo, "source_id" => $origen, "choice_key" => $ruta,
        "value" => json_encode($valor, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        "value_source" => null, "value_source_id" => null
    ];
}

function modeloParaPersistir(array $personaje): array {
    foreach (["nombre", "nivel", "atributos", "metodo_atributos", "raza", "clase", "background", "resolucion"] as $campo) {
        if (!isset($personaje[$campo])) {
            throw new InvalidArgumentException("Falta " . $campo . " para guardar el personaje.");
        }
    }
    $resolucion = $personaje["resolucion"];
    foreach (["combate", "habilidades", "competencias", "conjuros", "objetos", "entidades", "elecciones"] as $campo) {
        if (!isset($resolucion[$campo]) || !is_array($resolucion[$campo])) {
            throw new InvalidArgumentException("Falta la resolución de " . $campo . ".");
        }
    }
    $modelo = [
        "nombre" => $personaje["nombre"], "nivel" => $personaje["nivel"], "experiencia" => $resolucion["experiencia"] ?? 0,
        "atributos" => $personaje["atributos"], "combate" => $resolucion["combate"],
        "entidades" => $resolucion["entidades"], "elecciones" => $resolucion["elecciones"],
        "habilidades" => $resolucion["habilidades"], "competencias" => $resolucion["competencias"],
        "conjuros" => $resolucion["conjuros"], "objetos" => $resolucion["objetos"],
        "datos" => array_merge($resolucion["datos"] ?? [], ["metodo_atributos" => $personaje["metodo_atributos"], "atributos_base" => $personaje["atributos"]])
    ];
    $mapa = ["fuerza" => "str", "destreza" => "dex", "constitucion" => "con", "inteligencia" => "int", "sabiduria" => "wis", "carisma" => "cha"];
    foreach ($mapa as $nombre => $clave) {
        if (!isset($modelo["atributos"][$nombre]) || !is_int($modelo["atributos"][$nombre])) {
            throw new InvalidArgumentException("La característica " . $nombre . " no es válida.");
        }
        $modelo["atributos"][$nombre] += $personaje["raza"]["bonificadores"][$clave] ?? 0;
        foreach ($personaje["raza"]["elecciones"]["dotes"] ?? [] as $dote) {
            $modelo["atributos"][$nombre] += $dote["elecciones"]["atributos"]["bonificadores"][$clave] ?? 0;
        }
    }
    foreach (["raza" => "species", "clase" => "class", "background" => "background", "subclase" => "subclass"] as $campo => $tipo) {
        if (!isset($personaje[$campo])) {
            continue;
        }
        $entidad = $personaje[$campo];
        $origen = identificadorEntidad5e($entidad);
        $modelo["entidades"][] = ["entity_type" => $tipo, "entity_name" => $entidad["name"], "source" => $entidad["source"], "source_id" => $origen];
        $elecciones = $entidad["elecciones"] ?? [];
        if ($campo === "raza") {
            unset($elecciones["dotes"]);
        } elseif ($campo === "background") {
            unset($elecciones["variantes_dotes"]);
        }
        anadirEleccionesPersistibles($modelo["elecciones"], $tipo, $origen, $elecciones, "");
    }
    $raza = $personaje["raza"];
    foreach ($raza["elecciones"]["dotes"] ?? [] as $i => $dote) {
        $idDote = identificadorEntidad5e($dote);
        $modelo["entidades"][] = ["entity_type" => "feat", "entity_name" => $dote["name"], "source" => $dote["source"], "source_id" => $idDote];
        anadirEleccionesPersistibles($modelo["elecciones"], "species", identificadorEntidad5e($raza), $dote, "dotes." . $i);
        $elecciones = $dote["elecciones"] ?? [];
        unset($elecciones["atributos"]["bonificadores"]);
        anadirEleccionesPersistibles($modelo["elecciones"], "feat", $idDote, $elecciones, "");
    }
    return $modelo;
}
