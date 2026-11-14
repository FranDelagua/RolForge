<?php

require_once __DIR__ . "/../Api/elecciones-raza.php";

function comprobarEleccion($condicion, $mensaje) {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$linaje = cargarDatosEleccionesRaza(["raza" => "Custom Lineage|TCE"]);
comprobarEleccion(isset($linaje["dotes"]["Magic Initiate|PHB"]), "Falta una dote del catálogo.");
comprobarEleccion($linaje["variantes"][0]["incrementos"] === [2], "El linaje debe tener un +2.");
comprobarEleccion(validarEleccionesRaza($linaje, ["atributos_raza" => ["str"]])["errores"] !== [], "Debe exigirse una dote.");
$seleccion = validarEleccionesRaza($linaje, [
    "atributos_raza" => ["wis"],
    "dotes_raza" => ["Magic Initiate|PHB"]
]);
comprobarEleccion($seleccion["errores"] === [] && $seleccion["bonificadores"]["wis"] === 2, "La elección de linaje no se guardó correctamente.");
comprobarEleccion($seleccion["elecciones"]["dotes"] === ["Magic Initiate|PHB"], "La dote seleccionada no se guardó.");

$semielfo = cargarDatosEleccionesRaza(["raza" => "Half-Elf|PHB"]);
comprobarEleccion(validarEleccionesRaza($semielfo, ["atributos_raza" => ["str", "str"]])["errores"] !== [], "No deben repetirse atributos.");
$seleccion = validarEleccionesRaza($semielfo, ["atributos_raza" => ["str", "wis"]]);
comprobarEleccion($seleccion["errores"] === [] && $seleccion["bonificadores"]["cha"] === 2 && $seleccion["bonificadores"]["str"] === 1 && $seleccion["bonificadores"]["wis"] === 1, "Bonificaciones de semielfo incorrectas.");

$lineage = cargarDatosEleccionesRaza(["raza" => "Aarakocra|MPMM"]);
comprobarEleccion($lineage["variantes"][0]["incrementos"] === [2, 1] && $lineage["variantes"][1]["incrementos"] === [1, 1, 1], "No se leyó el reparto del libro de linajes.");
$seleccion = validarEleccionesRaza($lineage, ["reparto" => "1", "atributos_raza" => ["str", "dex", "con"]]);
comprobarEleccion($seleccion["errores"] === [] && $seleccion["bonificadores"]["str"] === 1 && $seleccion["bonificadores"]["dex"] === 1 && $seleccion["bonificadores"]["con"] === 1, "El reparto de tres atributos es incorrecto.");
$lineageConMaximo = cargarDatosEleccionesRaza(["raza" => "Aarakocra|MPMM", "atributos" => ["fuerza" => 20]]);
comprobarEleccion(validarEleccionesRaza($lineageConMaximo, ["atributos_raza" => ["str", "dex"]])["errores"] !== [], "El linaje no debe superar el límite del libro.");

$humano = cargarDatosEleccionesRaza(["raza" => "Human|XPHB"]);
comprobarEleccion(isset($humano["dotes"]["Alert|XPHB"]) && !isset($humano["dotes"]["Alert|PHB"]), "La categoría de dotes no se respetó.");
comprobarEleccion(validarEleccionesRaza($humano, ["dotes_raza" => ["Alert|PHB"]])["errores"] !== [], "Debe rechazarse una dote fuera de categoría.");

$elfo = cargarDatosEleccionesRaza(["raza" => "Elf|PHB"]);
$seleccion = validarEleccionesRaza($elfo, []);
comprobarEleccion($seleccion["errores"] === [] && $seleccion["bonificadores"]["dex"] === 2, "Los incrementos fijos no se aplicaron.");

echo "Elecciones de raza: pruebas correctas.\n";
