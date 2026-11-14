<?php

require_once __DIR__ . "/../Api/razas-5etools.php";

function comprobarRaza($condicion, $mensaje) {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$ruta = __DIR__ . "/../data/5etools/";
$razas = resolverCopiasRazas(json_decode(file_get_contents($ruta . "races.json"), true)["race"]);
$fluff = resolverCopiasRazas(json_decode(file_get_contents($ruta . "fluff-races.json"), true)["raceFluff"]);

$linaje = $razas["Custom Lineage|TCE"];
comprobarRaza($linaje["ability"][0]["choose"]["amount"] === 2, "Falta la elección de característica.");
comprobarRaza($linaje["feats"][0]["any"] === 1, "Falta la dote de Custom Lineage.");
comprobarRaza(!isset($fluff["Custom Lineage|TCE"]), "Custom Lineage no debería recibir fluff ajeno.");

$centauro = $razas["Centaur|MOT"];
$rasgosCentauro = array_column($centauro["entries"], "name");
comprobarRaza(in_array("Charge", $rasgosCentauro, true), "Falta un rasgo heredado.");
$tamano = $centauro["entries"][array_search("Size", $rasgosCentauro, true)];
comprobarRaza(strpos($tamano["entries"][0], "Pheres") !== false, "No se aplicó el reemplazo del rasgo.");

$goblin = $razas["Goblin (Dankwood)|AWM"];
$rasgosGoblin = array_column($goblin["entries"], "name");
$edad = $goblin["entries"][array_search("Age", $rasgosGoblin, true)];
comprobarRaza(strpos($edad["entries"][0], "Dankwood goblins") !== false, "No se aplicó el reemplazo de texto.");
comprobarRaza(!in_array("Fury of the Small", $rasgosGoblin, true), "No se sustituyó el rasgo heredado.");
comprobarRaza(in_array("Speak with Small Beasts", $rasgosGoblin, true), "Falta el rasgo de la variante.");

$dragonborn = $fluff["Dragonborn|PHB"];
comprobarRaza(strpos(json_encode($dragonborn["entries"]), "Born of dragons") !== false, "Falta el fluff añadido.");
comprobarRaza(count($dragonborn["entries"]) > 1, "Falta el fluff heredado.");

echo "Razas de 5etools: pruebas correctas.\n";
