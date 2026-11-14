<?php

require_once __DIR__ . "/../Api/modelo-personaje.php";
require_once __DIR__ . "/../Api/elecciones-raza.php";

function comprobarModelo($condicion, $mensaje) {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$personaje = [
    "nombre" => "Prueba",
    "raza" => "Custom Lineage|TCE",
    "clase" => "Cleric|PHB",
    "background" => "Lorehold Student|SCC",
    "subclase" => "Knowledge Domain|PHB",
    "elecciones_raza" => ["reparto" => 0, "atributos" => ["wis"], "dotes" => ["Magic Initiate|PHB"]],
    "bonificadores_raza" => ["wis" => 2],
    "raza_confirmada" => "Custom Lineage|TCE"
];
normalizarPersonajeEnCreacion($personaje);
comprobarModelo($personaje["raza"]["tipo"] === "race" && $personaje["raza"]["source"] === "TCE", "No se migró la raza.");
comprobarModelo($personaje["clase"]["tipo"] === "class" && $personaje["clase"]["source"] === "PHB", "No se migró la clase.");
comprobarModelo($personaje["background"]["tipo"] === "background" && $personaje["background"]["source"] === "SCC", "No se migró el background.");
comprobarModelo($personaje["subclase"]["shortName"] === "Knowledge" && $personaje["subclase"]["classSource"] === "PHB", "No se resolvió la identidad de la subclase.");
comprobarModelo($personaje["raza"]["elecciones"]["dotes"][0]["tipo"] === "feat" && $personaje["raza"]["elecciones"]["dotes"][0]["source"] === "PHB", "No se migró la dote de raza.");
comprobarModelo($personaje["raza"]["bonificadores"]["wis"] === 2 && $personaje["raza"]["confirmada"], "Se perdieron las elecciones raciales.");
comprobarModelo(!isset($personaje["elecciones_raza"], $personaje["bonificadores_raza"], $personaje["raza_confirmada"]), "Persisten datos duplicados.");

$personaje["raza"]["elecciones"]["dotes"][0]["elecciones"] = ["hechizo" => ["tipo" => "spell", "name" => "Hex", "source" => "PHB"]];
$antes = $personaje;
normalizarPersonajeEnCreacion($personaje);
comprobarModelo($personaje === $antes, "La normalización debe conservar las decisiones existentes.");
comprobarModelo(cargarDatosEleccionesRaza($personaje)["raza"]["name"] === "Custom Lineage", "No se puede resolver la raza estructurada.");

$referencia = buscarSubclase5e("Knowledge Domain|PHB", referencia5e("class", "Cleric|XPHB"));
comprobarModelo($referencia["classSource"] === "XPHB", "La subclase debe conservar la edición de la clase.");
comprobarModelo(claveReferencia5e($referencia, "subclass") === "Knowledge Domain|PHB", "No se puede recuperar la clave de la subclase.");

$invalido = $personaje;
$invalido["clase"] = referencia5e("class", "Wizard|PHB");
try {
    normalizarPersonajeEnCreacion($invalido);
    throw new RuntimeException("Se aceptó una subclase de otra clase.");
} catch (InvalidArgumentException $error) {
    comprobarModelo($invalido["clase"]["name"] === "Wizard", "Se modificó el personaje al rechazarlo.");
}

echo "Modelo de personaje: pruebas correctas.\n";
