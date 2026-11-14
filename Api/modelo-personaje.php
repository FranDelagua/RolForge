<?php

function referencia5e($tipo, $clave) {
    if (!is_string($clave)) {
        throw new InvalidArgumentException("La referencia de " . $tipo . " no es válida.");
    }
    $partes = explode("|", $clave);
    if (count($partes) !== 2 || $partes[0] === "" || $partes[1] === "") {
        throw new InvalidArgumentException("La referencia de " . $tipo . " no incluye nombre y fuente.");
    }
    return ["tipo" => $tipo, "name" => $partes[0], "source" => $partes[1]];
}

function claveReferencia5e($referencia, $tipo) {
    if (is_string($referencia)) {
        $referencia = referencia5e($tipo, $referencia);
    }
    if (
        !is_array($referencia) || ($referencia["tipo"] ?? null) !== $tipo ||
        !is_string($referencia["name"] ?? null) || !is_string($referencia["source"] ?? null) ||
        $referencia["name"] === "" || $referencia["source"] === "" ||
        strpos($referencia["name"], "|") !== false || strpos($referencia["source"], "|") !== false
    ) {
        throw new InvalidArgumentException("La referencia de " . $tipo . " no es válida.");
    }
    return $referencia["name"] . "|" . $referencia["source"];
}

function referenciaSubclase5e($subclase) {
    foreach (["name", "source", "shortName", "className", "classSource"] as $campo) {
        if (!isset($subclase[$campo]) || !is_string($subclase[$campo]) || $subclase[$campo] === "") {
            throw new InvalidArgumentException("La subclase no tiene una referencia completa en 5etools.");
        }
    }
    return [
        "tipo" => "subclass",
        "name" => $subclase["name"],
        "source" => $subclase["source"],
        "shortName" => $subclase["shortName"],
        "className" => $subclase["className"],
        "classSource" => $subclase["classSource"]
    ];
}

function buscarSubclase5e($clave, $clase) {
    $directorio = __DIR__ . "/../data/5etools/class/";
    $indice = json_decode(file_get_contents($directorio . "index.json"), true);
    if (!is_array($indice)) {
        throw new RuntimeException("No se pudo leer el índice de clases de 5etools.");
    }
    foreach ($indice as $archivo) {
        $datos = json_decode(file_get_contents($directorio . $archivo), true);
        if (!is_array($datos)) {
            throw new RuntimeException("No se pudieron leer las subclases de 5etools.");
        }
        foreach ($datos["subclass"] ?? [] as $subclase) {
            if (
                ($subclase["name"] ?? "") . "|" . ($subclase["source"] ?? "") === $clave &&
                ($subclase["className"] ?? "") === $clase["name"] &&
                ($subclase["classSource"] ?? "") === $clase["source"]
            ) {
                return referenciaSubclase5e($subclase);
            }
        }
    }
    throw new InvalidArgumentException("La subclase guardada no pertenece a la clase seleccionada.");
}

function normalizarPersonajeEnCreacion(&$personaje) {
    if (!is_array($personaje)) {
        throw new InvalidArgumentException("Los datos del personaje no son válidos.");
    }
    $actualizado = $personaje;
    foreach (["raza" => "race", "clase" => "class", "background" => "background"] as $campo => $tipo) {
        if (!isset($actualizado[$campo])) {
            continue;
        }
        if (is_string($actualizado[$campo])) {
            $actualizado[$campo] = referencia5e($tipo, $actualizado[$campo]);
        } else {
            claveReferencia5e($actualizado[$campo], $tipo);
        }
    }
    if (isset($actualizado["subclase"])) {
        if (is_string($actualizado["subclase"])) {
            if (!isset($actualizado["clase"])) {
                throw new InvalidArgumentException("Falta la clase de la subclase guardada.");
            }
            $actualizado["subclase"] = buscarSubclase5e($actualizado["subclase"], $actualizado["clase"]);
        } else {
            claveReferencia5e($actualizado["subclase"], "subclass");
            $identidad = referenciaSubclase5e($actualizado["subclase"]);
            if (
                !isset($actualizado["clase"]) ||
                $identidad["className"] !== $actualizado["clase"]["name"] ||
                $identidad["classSource"] !== $actualizado["clase"]["source"]
            ) {
                throw new InvalidArgumentException("La subclase guardada no pertenece a la clase seleccionada.");
            }
        }
    }
    if (isset($actualizado["elecciones_raza"])) {
        if (!isset($actualizado["raza"]) || !is_array($actualizado["elecciones_raza"])) {
            throw new InvalidArgumentException("Las elecciones de raza guardadas no son válidas.");
        }
        $elecciones = $actualizado["elecciones_raza"];
        $dotes = [];
        foreach ($elecciones["dotes"] ?? [] as $dote) {
            if (is_string($dote)) {
                $dote = referencia5e("feat", $dote);
                $dote["elecciones"] = [];
            } else {
                claveReferencia5e($dote, "feat");
            }
            $dotes[] = $dote;
        }
        $elecciones["dotes"] = $dotes;
        $actualizado["raza"]["elecciones"] = $elecciones;
        $actualizado["raza"]["bonificadores"] = $actualizado["bonificadores_raza"] ?? [];
        $actualizado["raza"]["confirmada"] = isset($actualizado["raza_confirmada"]) &&
            claveReferencia5e($actualizado["raza_confirmada"], "race") === claveReferencia5e($actualizado["raza"], "race");
    }
    unset($actualizado["elecciones_raza"], $actualizado["bonificadores_raza"], $actualizado["raza_confirmada"]);
    $personaje = $actualizado;
}

function prepararPersonajeEnCreacion() {
    if (!isset($_SESSION["personaje_en_creacion"])) {
        header("Location: crear-personaje.php");
        exit;
    }
    try {
        normalizarPersonajeEnCreacion($_SESSION["personaje_en_creacion"]);
    } catch (InvalidArgumentException $error) {
        error_log($error->getMessage());
        http_response_code(400);
        die("No se pudo recuperar el personaje en creación: " . htmlspecialchars($error->getMessage()));
    } catch (RuntimeException $error) {
        error_log($error->getMessage());
        http_response_code(500);
        die("No se pudieron leer los datos necesarios para recuperar el personaje.");
    }
}
