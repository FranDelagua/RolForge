<?php

require_once __DIR__ . "/razas-5etools.php";

function cargarDatosEleccionesRaza($personaje) {
    $directorio = __DIR__ . "/../data/5etools/";
    $datos = json_decode(file_get_contents($directorio . "races.json"), true);
    if (!isset($datos["race"]) || !is_array($datos["race"])) {
        throw new RuntimeException("No se pudieron leer las razas de 5etools.");
    }
    $razas = resolverCopiasRazas($datos["race"]);
    $clave = $personaje["raza"] ?? "";
    if (!is_string($clave) || !isset($razas[$clave])) {
        throw new RuntimeException("La raza seleccionada no existe en 5etools.");
    }
    $raza = $razas[$clave];
    $atributos = ["str", "dex", "con", "int", "wis", "cha"];
    $fijos = array_fill_keys($atributos, 0);
    $variantes = [];
    $limite = null;
    $regla = $raza["ability"] ?? [];
    if (count($regla) > 1) {
        throw new RuntimeException("La raza contiene variantes de atributos todavía no compatibles.");
    }
    if ($regla) {
        foreach ($atributos as $atributo) {
            $fijos[$atributo] = $regla[0][$atributo] ?? 0;
        }
        if (isset($regla[0]["choose"])) {
            $elige = $regla[0]["choose"];
            if (!isset($elige["from"]) || !is_array($elige["from"])) {
                throw new RuntimeException("La elección de atributos de la raza no es compatible.");
            }
            $variantes[] = [
                "opciones" => $elige["from"],
                "incrementos" => array_fill(0, $elige["count"] ?? 1, $elige["amount"] ?? 1)
            ];
        }
    } elseif (is_string($raza["lineage"] ?? null)) {
        $fuente = $raza["lineage"];
        if (!preg_match('/^[a-z0-9-]+$/i', $fuente)) {
            throw new RuntimeException("La fuente del linaje no es válida.");
        }
        $rutaLibro = $directorio . "book/book-" . strtolower($fuente) . ".json";
        if (!is_file($rutaLibro)) {
            throw new RuntimeException("No se encontró el libro de la regla de linaje " . $fuente . ".");
        }
        $libro = json_decode(file_get_contents($rutaLibro), true);
        if (!is_array($libro)) {
            throw new RuntimeException("No se pudo leer el libro de la regla de linaje.");
        }
        $textos = [];
        array_walk_recursive($libro, function ($valor) use (&$textos) {
            if (is_string($valor)) {
                $textos[] = $valor;
            }
        });
        foreach ($textos as $texto) {
            if (preg_match('/increase one of those scores by (\d+) and increase a different score by (\d+), or you increase three different scores by (\d+)/i', $texto, $coincidencias)) {
                $variantes = [
                    ["opciones" => $atributos, "incrementos" => [(int)$coincidencias[1], (int)$coincidencias[2]]],
                    ["opciones" => $atributos, "incrementos" => array_fill(0, 3, (int)$coincidencias[3])]
                ];
            }
            if (preg_match('/none of the scores can be raised above (\d+)/i', $texto, $coincidenciasLimite)) {
                $limite = (int)$coincidenciasLimite[1];
            }
        }
        if (!$variantes) {
            throw new RuntimeException("No se pudo interpretar la regla de atributos del linaje " . $fuente . ".");
        }
    }

    $concesiones = $raza["feats"] ?? [];
    if (count($concesiones) > 1) {
        throw new RuntimeException("La raza contiene concesiones alternativas de dotes todavía no compatibles.");
    }
    $cantidadDotes = 0;
    $categorias = null;
    if ($concesiones) {
        $concesion = $concesiones[0];
        if (isset($concesion["any"])) {
            $cantidadDotes = $concesion["any"];
        } elseif (isset($concesion["anyFromCategory"]["category"], $concesion["anyFromCategory"]["count"])) {
            $categorias = $concesion["anyFromCategory"]["category"];
            $cantidadDotes = $concesion["anyFromCategory"]["count"];
        } else {
            throw new RuntimeException("La concesión de dotes de la raza todavía no es compatible.");
        }
    }
    $dotes = [];
    if ($cantidadDotes) {
        $datosDotes = json_decode(file_get_contents($directorio . "feats.json"), true);
        if (!isset($datosDotes["feat"]) || !is_array($datosDotes["feat"])) {
            throw new RuntimeException("No se pudieron leer las dotes de 5etools.");
        }
        foreach ($datosDotes["feat"] as $dote) {
            if (empty($dote["name"]) || empty($dote["source"])) {
                continue;
            }
            if ($categorias !== null && !in_array($dote["category"] ?? "", $categorias, true)) {
                continue;
            }
            $dotes[$dote["name"] . "|" . $dote["source"]] = $dote;
        }
        if (!$dotes) {
            throw new RuntimeException("No hay dotes disponibles para la raza.");
        }
    }
    return [
        "raza" => $raza,
        "fijos" => $fijos,
        "variantes" => $variantes,
        "limite" => $limite,
        "atributosBase" => $personaje["atributos"] ?? [],
        "cantidadDotes" => $cantidadDotes,
        "dotes" => $dotes
    ];
}

function validarEleccionesRaza($datos, $entrada) {
    $errores = [];
    $bonificadores = $datos["fijos"];
    $seleccion = [];
    $indice = $entrada["reparto"] ?? "0";
    if ($datos["variantes"]) {
        if (!is_string($indice) || !ctype_digit($indice) || !isset($datos["variantes"][(int)$indice])) {
            return ["errores" => ["Selecciona un reparto de atributos válido."]];
        }
        $variante = $datos["variantes"][(int)$indice];
        $seleccion = $entrada["atributos_raza"] ?? [];
        if (!is_array($seleccion) || !array_is_list($seleccion) || count($seleccion) !== count($variante["incrementos"])) {
            $errores[] = "Selecciona todos los atributos requeridos.";
        } elseif (count(array_unique($seleccion, SORT_REGULAR)) !== count($seleccion)) {
            $errores[] = "Elige un atributo distinto para cada incremento.";
        } else {
            foreach ($seleccion as $posicion => $atributo) {
                if (!is_string($atributo) || !in_array($atributo, $variante["opciones"], true) || !array_key_exists($atributo, $bonificadores)) {
                    $errores[] = "Selecciona atributos válidos para la raza.";
                    break;
                }
                $bonificadores[$atributo] += $variante["incrementos"][$posicion];
            }
        }
    }
    $dotes = $entrada["dotes_raza"] ?? [];
    if (!is_array($dotes) || !array_is_list($dotes) || count($dotes) !== $datos["cantidadDotes"]) {
        $errores[] = "Selecciona las dotes requeridas para la raza.";
    } else {
        foreach ($dotes as $dote) {
            if (!is_string($dote) || !isset($datos["dotes"][$dote])) {
                $errores[] = "Selecciona una dote válida para la raza.";
                break;
            }
        }
    }
    if ($errores) {
        return ["errores" => $errores];
    }
    if ($datos["limite"] !== null) {
        $nombres = ["str" => "fuerza", "dex" => "destreza", "con" => "constitucion", "int" => "inteligencia", "wis" => "sabiduria", "cha" => "carisma"];
        foreach ($bonificadores as $clave => $bonificador) {
            $base = $datos["atributosBase"][$nombres[$clave]] ?? null;
            if ($bonificador && is_numeric($base) && $base + $bonificador > $datos["limite"]) {
                $errores[] = "Una puntuación no puede superar " . $datos["limite"] . " con la bonificación racial.";
                break;
            }
        }
    }
    if ($errores) {
        return ["errores" => $errores];
    }
    return [
        "errores" => [],
        "bonificadores" => $bonificadores,
        "elecciones" => ["reparto" => (int)$indice, "atributos" => $seleccion, "dotes" => $dotes]
    ];
}
