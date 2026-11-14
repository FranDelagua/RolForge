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

$dosOpciones = [
    "atributos_raza" => ["wis"],
    "dotes_raza" => ["Fey Teleportation|XGE"]
];
comprobarEleccion(validarEleccionesRaza($linaje, $dosOpciones)["errores"] !== [], "Debe exigirse la característica de la dote.");
$dosOpciones["dotes_atributos"] = [["atributos" => ["str"]]];
comprobarEleccion(validarEleccionesRaza($linaje, $dosOpciones)["errores"] !== [], "Debe rechazarse una característica fuera de las opciones.");
$dosOpciones["dotes_atributos"] = [["atributos" => ["cha"]]];
$resultadoDote = validarEleccionesRaza($linaje, $dosOpciones);
comprobarEleccion($resultadoDote["errores"] === [] && $resultadoDote["elecciones_dotes"][0]["bonificadores"] === ["cha" => 1], "La elección de carisma de la dote no se guardó.");
comprobarEleccion(variantesAtributosDote($linaje["dotes"]["Fey Teleportation|XGE"])[0]["opciones"] === ["int", "cha"], "No se respetaron las opciones de la dote.");

$cualquierAtributo = ["atributos_raza" => ["wis"], "dotes_raza" => ["Resilient|PHB"], "dotes_atributos" => [["atributos" => ["str"]]]];
comprobarEleccion(count(variantesAtributosDote($linaje["dotes"]["Resilient|PHB"])[0]["opciones"]) === 6, "Deben ofrecerse las seis características.");
comprobarEleccion(validarEleccionesRaza($linaje, $cualquierAtributo)["elecciones_dotes"][0]["bonificadores"] === ["str" => 1], "No se guardó la característica elegida libremente.");

$doteFija = validarEleccionesRaza($linaje, ["atributos_raza" => ["wis"], "dotes_raza" => ["Actor|PHB"]]);
comprobarEleccion($doteFija["errores"] === [] && $doteFija["elecciones_dotes"][0]["bonificadores"] === ["cha" => 1], "No se aplicó la mejora fija de la dote.");
$repartos = ["atributos_raza" => ["wis"], "dotes_raza" => ["Ability Score Improvement|XPHB"], "dotes_atributos" => [["reparto" => "1", "atributos" => ["str", "dex"]]]];
comprobarEleccion(validarEleccionesRaza($linaje, $repartos)["elecciones_dotes"][0]["bonificadores"] === ["str" => 1, "dex" => 1], "No se aplicó el reparto alternativo de la dote.");
$repartos["dotes_atributos"][0]["atributos"] = ["str", "str"];
comprobarEleccion(validarEleccionesRaza($linaje, $repartos)["errores"] !== [], "No deben repetirse características en el reparto.");

$linajeConBase = cargarDatosEleccionesRaza(["raza" => "Custom Lineage|TCE", "atributos" => ["carisma" => 19]]);
$dosOpciones["atributos_raza"] = ["cha"];
comprobarEleccion(validarEleccionesRaza($linajeConBase, $dosOpciones)["errores"] !== [], "La dote no debe superar el máximo tras la bonificación racial.");
$linajeMaximoEspecial = cargarDatosEleccionesRaza(["raza" => "Custom Lineage|TCE", "atributos" => ["carisma" => 20]]);
$maximoEspecial = ["atributos_raza" => ["wis"], "dotes_raza" => ["Boon of Terror|FRHoF"]];
comprobarEleccion(validarEleccionesRaza($linajeMaximoEspecial, $maximoEspecial)["errores"] === [], "Debe respetarse el máximo especial de la dote.");

$personajeConfirmado = [
    "raza" => [
        "tipo" => "race", "name" => "Custom Lineage", "source" => "TCE", "confirmada" => true,
        "bonificadores" => $resultadoDote["bonificadores"],
        "elecciones" => [
            "reparto" => 0, "atributos" => ["wis"],
            "dotes" => [["tipo" => "feat", "name" => "Fey Teleportation", "source" => "XGE", "elecciones" => ["atributos" => $resultadoDote["elecciones_dotes"][0]]]]
        ]
    ]
];
comprobarEleccion(eleccionesRazaConfirmadas($personajeConfirmado), "No se reconoció la elección confirmada de la dote.");
unset($personajeConfirmado["raza"]["elecciones"]["dotes"][0]["elecciones"]["atributos"]);
comprobarEleccion(!eleccionesRazaConfirmadas($personajeConfirmado), "Una sesión antigua no debe omitir la elección obligatoria.");

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
