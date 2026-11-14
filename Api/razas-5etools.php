<?php

function reemplazarTextoRaza($valor, $buscar, $reemplazo, $flags) {
    if (is_string($valor)) {
        $resultado = preg_replace(
            "~" . str_replace("~", "\\~", $buscar) . "~" . ($flags === "i" ? "i" : ""),
            $reemplazo,
            $valor
        );
        if ($resultado === null) {
            throw new RuntimeException("Expresión de reemplazo de raza no válida.");
        }
        return $resultado;
    }
    if (is_array($valor)) {
        foreach ($valor as $clave => $elemento) {
            $valor[$clave] = reemplazarTextoRaza($elemento, $buscar, $reemplazo, $flags);
        }
    }
    return $valor;
}

function resolverCopiasRazas($registros) {
    $indice = [];
    foreach ($registros as $registro) {
        $indice[($registro["name"] ?? "") . "|" . ($registro["source"] ?? "")] = $registro;
    }
    $resueltos = [];
    $visitando = [];

    $resolver = function ($clave) use (&$resolver, &$indice, &$resueltos, &$visitando) {
        if (isset($resueltos[$clave])) {
            return $resueltos[$clave];
        }
        if (isset($visitando[$clave]) || !isset($indice[$clave])) {
            throw new RuntimeException("Referencia de raza no válida: " . $clave);
        }
        $visitando[$clave] = true;
        $registro = $indice[$clave];
        if (isset($registro["_copy"])) {
            $copia = $registro["_copy"];
            $origen = ($copia["name"] ?? "") . "|" . ($copia["source"] ?? "");
            $base = $resolver($origen);
            foreach ($copia["_mod"] ?? [] as $campo => $modificaciones) {
                if (!in_array($campo, ["entries", "images"], true)) {
                    throw new RuntimeException("Campo heredado de raza no compatible: " . $campo);
                }
                if (isset($modificaciones["mode"])) {
                    $modificaciones = [$modificaciones];
                }
                foreach ($modificaciones as $modificacion) {
                    $modo = $modificacion["mode"] ?? "";
                    if ($modo === "replaceTxt") {
                        $base[$campo] = reemplazarTextoRaza(
                            $base[$campo] ?? [],
                            $modificacion["replace"],
                            $modificacion["with"],
                            $modificacion["flags"] ?? ""
                        );
                        continue;
                    }
                    $items = $modificacion["items"] ?? [];
                    if (!is_array($items) || !array_is_list($items)) {
                        $items = [$items];
                    }
                    $contenido = $base[$campo] ?? [];
                    if ($modo === "prependArr") {
                        $base[$campo] = array_merge($items, $contenido);
                    } elseif ($modo === "appendArr") {
                        $base[$campo] = array_merge($contenido, $items);
                    } elseif ($modo === "replaceArr") {
                        $encontrado = false;
                        foreach ($contenido as $posicion => $entrada) {
                            if (is_array($entrada) && ($entrada["name"] ?? null) === ($modificacion["replace"] ?? null)) {
                                array_splice($contenido, $posicion, 1, $items);
                                $encontrado = true;
                                break;
                            }
                        }
                        if (!$encontrado) {
                            throw new RuntimeException("No se encontró el rasgo heredado de " . $clave . ".");
                        }
                        $base[$campo] = $contenido;
                    } else {
                        throw new RuntimeException("Modificación de raza no compatible: " . $modo);
                    }
                }
            }
            unset($registro["_copy"]);
            $registro = array_replace($base, $registro);
        }
        unset($visitando[$clave]);
        $resueltos[$clave] = $registro;
        return $registro;
    };

    foreach (array_keys($indice) as $clave) {
        $resolver($clave);
    }
    return $resueltos;
}
