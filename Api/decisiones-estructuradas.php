<?php

function decisionesEstructuradas(array $personaje, array $raza, array $clase, array $background, ?array $subclase): array {
    $resultado = ["decisiones" => [], "pendientes" => [], "elecciones" => [], "objetos" => [], "competencias" => [], "habilidades" => [], "conjuros" => [], "advertencias" => [], "detalles_equipo" => [], "especie" => []];
    $guardadas = $personaje["elecciones_adicionales"] ?? [];
    $decidir = function ($tipo, $origen, $clave, $titulo, $opciones, $cantidad = 1, $referencia = false) use (&$resultado, $guardadas) {
        $id = hash("sha256", $tipo . "|" . $origen . "|" . $clave);
        $seleccion = $guardadas[$id] ?? [];
        $valida = is_array($seleccion) && array_is_list($seleccion) && count($seleccion) <= $cantidad &&
            count(array_filter($seleccion, "is_string")) === count($seleccion) &&
            count(array_unique($seleccion, SORT_REGULAR)) === count($seleccion);
        if ($valida) {
            foreach ($seleccion as $valor) {
                if (!is_string($valor) || !isset($opciones[$valor])) {
                    $valida = false;
                    break;
                }
            }
        }
        $resultado["decisiones"][$id] = ["id" => $id, "entidad" => $origen, "titulo" => $titulo, "opciones" => $opciones, "cantidad" => $cantidad, "seleccion" => $valida ? $seleccion : []];
        if (!$valida || count($seleccion) !== $cantidad) {
            $resultado["pendientes"][] = ["tipo" => "eleccion", "entidad" => $origen, "decision" => $titulo, "motivo" => "Selecciona " . $cantidad . " opción(es)."];
            return [];
        }
        foreach ($seleccion as $i => $valor) {
            $partes = $referencia ? explode("|", $valor, 2) : [];
            $resultado["elecciones"][] = ["source_type" => $tipo, "source_id" => $origen, "choice_key" => $clave . "." . $i,
                "value" => $referencia ? $partes[0] : $valor, "value_source" => $referencia ? $partes[1] : null,
                "value_source_id" => $referencia ? $valor : null];
        }
        return $seleccion;
    };

    $refRaza = $raza["name"] . "|" . $raza["source"];
    if (isset($raza["size"]) && is_array($raza["size"])) {
        if (count($raza["size"]) > 1) {
            $opciones = array_combine($raza["size"], $raza["size"]);
            $seleccion = $decidir("species", $refRaza, "tamano", "Elegir tamaño", $opciones);
            if ($seleccion) {
                $resultado["especie"]["size"] = $seleccion[0];
            }
        } elseif (count($raza["size"]) === 1) {
            $resultado["especie"]["size"] = $raza["size"][0];
        }
    }
    if (isset($raza["speed"])) {
        $resultado["especie"]["speed"] = $raza["speed"];
    }
    $textos = [];
    $entries = $raza["entries"] ?? [];
    if (is_array($entries)) {
        array_walk_recursive($entries, function ($valor) use (&$textos) {
            if (is_string($valor)) {
                $textos[] = $valor;
            }
        });
    }
    $tieneAlternativas = false;
    foreach ($textos as $texto) {
        if (preg_match('/\bgain one of the following options of your choice\b/i', $texto)) {
            $tieneAlternativas = true;
        }
    }
    $habilidadesRaza = $raza["skillProficiencies"] ?? [];
    if ($tieneAlternativas && count($raza["_versions"] ?? []) > 1) {
        $opciones = [];
        $variantes = [];
        foreach ($raza["_versions"] as $version) {
            if (is_string($version["name"] ?? null) && is_string($version["source"] ?? null)) {
                $clave = $version["name"] . "|" . $version["source"];
                $opciones[$clave] = $version["name"] . " (" . $version["source"] . ")";
                $variantes[$clave] = $version;
            }
        }
        if ($opciones) {
            $seleccion = $decidir("species", $refRaza, "variante", "Elegir rasgo de especie", $opciones, 1, true);
            if ($seleccion) {
                $version = $variantes[$seleccion[0]];
                $resultado["especie"]["variante"] = $seleccion[0];
                $habilidadesRaza = array_key_exists("skillProficiencies", $version) ? ($version["skillProficiencies"] ?? []) : $habilidadesRaza;
                $vision = array_key_exists("darkvision", $version) ? $version["darkvision"] : ($raza["darkvision"] ?? null);
                if ($vision !== null) {
                    $resultado["especie"]["darkvision"] = $vision;
                }
            } else {
                $habilidadesRaza = [];
            }
        }
    } elseif (isset($raza["darkvision"])) {
        $resultado["especie"]["darkvision"] = $raza["darkvision"];
    }
    if (count($habilidadesRaza) === 1 && is_int($habilidadesRaza[0]["any"] ?? null)) {
        $datos = json_decode(file_get_contents(__DIR__ . "/../data/5etools/skills.json"), true);
        $opciones = [];
        foreach ($datos["skill"] ?? [] as $habilidad) {
            if (($habilidad["source"] ?? "") === "PHB") {
                $opciones[strtolower($habilidad["name"])] = $habilidad["name"];
            }
        }
        if (!$opciones) {
            throw new RuntimeException("No se pudieron leer las habilidades de 5etools.");
        }
        $resultado["habilidades"] = $decidir("species", $refRaza, "habilidades.any", "Elegir habilidad de especie", $opciones, $habilidadesRaza[0]["any"]);
    }

    $idiomasJson = json_decode(file_get_contents(__DIR__ . "/../data/5etools/languages.json"), true);
    if (!isset($idiomasJson["language"]) || !is_array($idiomasJson["language"])) {
        throw new RuntimeException("No se pudieron leer los idiomas de 5etools.");
    }
    $idiomas = [];
    foreach ($idiomasJson["language"] as $idioma) {
        if (($idioma["type"] ?? "") === "standard" && is_string($idioma["name"] ?? null) && is_string($idioma["source"] ?? null)) {
            $clave = $idioma["name"] . "|" . $idioma["source"];
            $idiomas[$clave] = $idioma["name"] . " (" . $idioma["source"] . ")";
        }
    }
    foreach (["species" => $raza, "background" => $background] as $tipo => $origen) {
        $grupos = $origen["languageProficiencies"] ?? [];
        if (count($grupos) !== 1 || !is_array($grupos[0])) {
            continue;
        }
        foreach ($grupos[0] as $regla => $valor) {
            if ($valor === true) {
                $candidatos = [];
                foreach ($idiomasJson["language"] as $idioma) {
                    if (isset($idioma["name"], $idioma["source"]) && strcasecmp($idioma["name"], $regla) === 0) {
                        $candidatos[$idioma["source"]] = $idioma;
                    }
                }
                $idioma = $candidatos[$origen["source"]] ?? $candidatos["PHB"] ?? (count($candidatos) === 1 ? reset($candidatos) : null);
                $resultado["competencias"][] = ["proficiency_type" => "language", "name" => $idioma["name"] ?? $regla,
                    "source" => $idioma["source"] ?? null, "source_id" => $idioma !== null ? $idioma["name"] . "|" . $idioma["source"] : null];
                if ($idioma === null) {
                    $resultado["advertencias"][] = ["entidad" => $origen["name"] . "|" . $origen["source"], "decision" => "Idioma: " . $regla, "motivo" => "No se pudo identificar inequívocamente su fuente en 5etools."];
                }
                continue;
            }
            if ($regla !== "anyStandard" || !is_int($valor) || $valor < 1) {
                continue;
            }
            $ref = $origen["name"] . "|" . $origen["source"];
            $seleccion = $decidir($tipo, $ref, "idiomas." . $regla, "Elegir " . $valor . " idioma(s)", $idiomas, $valor, true);
            foreach ($seleccion as $idioma) {
                [$nombre, $fuente] = explode("|", $idioma, 2);
                $resultado["competencias"][] = ["proficiency_type" => "language", "name" => $nombre, "source" => $fuente, "source_id" => $idioma];
            }
        }
    }

    $catalogo = [];
    foreach (["items-base.json" => "baseitem", "items.json" => "item"] as $archivo => $coleccion) {
        $datos = json_decode(file_get_contents(__DIR__ . "/../data/5etools/" . $archivo), true);
        if (!isset($datos[$coleccion]) || !is_array($datos[$coleccion])) {
            throw new RuntimeException("No se pudieron leer los objetos de 5etools.");
        }
        foreach ($datos[$coleccion] as $item) {
            if (is_string($item["name"] ?? null) && is_string($item["source"] ?? null)) {
                $catalogo[strtolower($item["name"] . "|" . $item["source"])] = $item;
            }
        }
    }
    $etiqueta = function ($item) {
        if (is_string($item)) {
            return $item;
        }
        return $item["displayName"] ?? $item["item"] ?? $item["special"] ?? $item["equipmentType"] ?? "Opción no soportada";
    };
    $procesarItem = function ($item, $tipo, $origen, $ruta) use (&$resultado, $catalogo, $decidir) {
        $cantidad = 1;
        if (is_array($item) && isset($item["equipmentType"])) {
            $categoria = $item["equipmentType"];
            $opciones = [];
            foreach ($catalogo as $candidato) {
                $compatible = ($categoria === "weaponSimple" && ($candidato["weaponCategory"] ?? "") === "simple") ||
                    ($categoria === "weaponMartial" && ($candidato["weaponCategory"] ?? "") === "martial") ||
                    ($categoria === "focusSpellcastingArcane" && ($candidato["scfType"] ?? "") === "arcane");
                if ($compatible) {
                    $clave = $candidato["name"] . "|" . $candidato["source"];
                    $opciones[$clave] = $candidato["name"] . " (" . $candidato["source"] . ")";
                }
            }
            if (!$opciones) {
                $resultado["advertencias"][] = ["entidad" => $origen, "decision" => "Equipo: " . $categoria, "motivo" => "La categoría de equipo todavía no se interpreta."];
                return;
            }
            $seleccion = $decidir($tipo, $origen, $ruta, "Elegir objeto: " . $categoria, $opciones, 1, true);
            if (!$seleccion) {
                return;
            }
            $item = $seleccion[0];
        }
        if (is_array($item)) {
            $cantidad = $item["quantity"] ?? 1;
            if (isset($item["special"]) && is_string($item["special"])) {
                $resultado["objetos"][] = ["name" => $item["special"], "source" => null, "source_id" => null, "quantity" => $cantidad, "equipped" => 0];
                return;
            }
            $original = $item;
            $item = $item["item"] ?? null;
        }
        if (!is_string($item) || substr_count($item, "|") !== 1 || !is_int($cantidad) || $cantidad < 1) {
            $resultado["advertencias"][] = ["entidad" => $origen, "decision" => "Equipo", "motivo" => "Un objeto de equipo no se pudo interpretar."];
            return;
        }
        $datos = $catalogo[strtolower($item)] ?? null;
        [$nombre, $fuente] = explode("|", $item, 2);
        if ($datos !== null) {
            $nombre = $datos["name"];
            $fuente = $datos["source"];
        }
        $ref = $nombre . "|" . $fuente;
        $resultado["objetos"][] = ["name" => $nombre, "source" => $fuente, "source_id" => $ref, "quantity" => $cantidad, "equipped" => 0];
        if (isset($original["containsValue"])) {
            $resultado["detalles_equipo"][] = ["item" => $ref, "containsValue" => $original["containsValue"]];
        }
    };
    foreach (["class" => [$clase, $clase["startingEquipment"]["defaultData"] ?? []], "background" => [$background, $background["startingEquipment"] ?? []]] as $tipo => [$origen, $grupos]) {
        $ref = $origen["name"] . "|" . $origen["source"];
        foreach ($grupos as $indice => $grupo) {
            if (!is_array($grupo)) {
                $resultado["advertencias"][] = ["entidad" => $ref, "decision" => "Equipo inicial", "motivo" => "La estructura del equipo no está soportada."];
                continue;
            }
            $alternativas = [];
            foreach ($grupo as $clave => $items) {
                if ($clave !== "_" && is_string($clave) && preg_match('/^[a-z]$/', $clave) && is_array($items)) {
                    $alternativas[$clave] = strtoupper($clave) . ": " . implode(", ", array_map($etiqueta, $items));
                }
            }
            $elegida = null;
            if ($alternativas) {
                $seleccion = $decidir($tipo, $ref, "equipo." . $indice . ".alternativa", "Elegir equipo inicial", $alternativas);
                $elegida = $seleccion[0] ?? null;
            }
            foreach ($grupo as $clave => $items) {
                if (($clave !== "_" && $clave !== $elegida) || !is_array($items)) {
                    continue;
                }
                foreach ($items as $posicion => $item) {
                    $procesarItem($item, $tipo, $ref, "equipo." . $indice . "." . $clave . "." . $posicion);
                }
            }
        }
    }

    $nivel = (int)$personaje["nivel"];
    $cantrips = $clase["cantripProgression"][$nivel - 1] ?? 0;
    $conocidos = $clase["spellsKnownProgression"][$nivel - 1] ?? 0;
    if ((is_int($cantrips) && $cantrips > 0) || (is_int($conocidos) && $conocidos > 0)) {
        $maxNivel = 0;
        foreach ($clase["classTableGroups"] ?? [] as $tabla) {
            foreach ($tabla["colLabels"] ?? [] as $i => $etiqueta) {
                if (is_string($etiqueta) && stripos($etiqueta, "Slot Level") !== false) {
                    $celda = $tabla["rows"][$nivel - 1][$i] ?? "";
                    if (is_string($celda) && preg_match('/\b(\d+)(?:st|nd|rd|th)\b/i', $celda, $m)) {
                        $maxNivel = (int)$m[1];
                    }
                }
            }
        }
        $indice = json_decode(file_get_contents(__DIR__ . "/../data/5etools/spells/index.json"), true);
        $fuentes = json_decode(file_get_contents(__DIR__ . "/../data/5etools/generated/gendata-spell-source-lookup.json"), true);
        if (!is_array($indice) || !is_array($fuentes)) {
            throw new RuntimeException("No se pudieron leer los índices de conjuros.");
        }
        $opcionesNivel = [0 => [], 1 => []];
        $catalogoConjuros = [];
        foreach ($indice as $archivo) {
            $datos = json_decode(file_get_contents(__DIR__ . "/../data/5etools/spells/" . $archivo), true);
            if (!isset($datos["spell"]) || !is_array($datos["spell"])) {
                throw new RuntimeException("No se pudieron leer los conjuros de 5etools.");
            }
            foreach ($datos["spell"] as $conjuro) {
                $source = $conjuro["source"] ?? "";
                $nombre = $conjuro["name"] ?? "";
                $nivelConjuro = $conjuro["level"] ?? null;
                if ($source !== "" && $nombre !== "") {
                    $catalogoConjuros[strtolower($nombre . "|" . $source)] = $conjuro;
                }
                if (!is_int($nivelConjuro) || $nombre === "" || $source === "" ||
                    empty($fuentes[strtolower($source)][strtolower($nombre)]["class"][$clase["source"]][$clase["name"]])) {
                    continue;
                }
                $grupo = $nivelConjuro === 0 ? 0 : (($nivelConjuro <= $maxNivel) ? 1 : -1);
                if ($grupo >= 0) {
                    $opcionesNivel[$grupo][$nombre . "|" . $source] = $nombre . " (" . $source . ", nivel " . $nivelConjuro . ")";
                }
            }
        }
        foreach ($subclase["additionalSpells"] ?? [] as $grupo) {
            foreach ($grupo["expanded"] ?? [] as $nivelClave => $conjuros) {
                if (!preg_match('/^s(\d+)$/', (string)$nivelClave, $m) || (int)$m[1] > $maxNivel || !is_array($conjuros)) {
                    continue;
                }
                foreach ($conjuros as $referencia) {
                    if (!is_string($referencia)) {
                        continue;
                    }
                    $partes = explode("|", $referencia);
                    $nombre = $partes[0];
                    $source = $partes[1] ?? "PHB";
                    $conjuro = $catalogoConjuros[strtolower($nombre . "|" . $source)] ?? null;
                    if ($conjuro !== null) {
                        $opcionesNivel[1][$conjuro["name"] . "|" . $conjuro["source"]] = $conjuro["name"] . " (" . $conjuro["source"] . ", nivel " . $conjuro["level"] . ")";
                    }
                }
            }
        }
        $ref = $clase["name"] . "|" . $clase["source"];
        foreach ([0 => $cantrips, 1 => $conocidos] as $grupo => $cantidad) {
            if (!is_int($cantidad) || $cantidad < 1) {
                continue;
            }
            if (!$opcionesNivel[$grupo]) {
                $resultado["advertencias"][] = ["entidad" => $ref, "decision" => "Lista de conjuros", "motivo" => "No se pudo determinar una lista de conjuros válida."];
                continue;
            }
            asort($opcionesNivel[$grupo]);
            $seleccion = $decidir("class", $ref, "conjuros." . $grupo, $grupo === 0 ? "Elegir trucos conocidos" : "Elegir conjuros conocidos", $opcionesNivel[$grupo], $cantidad, true);
            foreach ($seleccion as $conjuro) {
                [$nombre, $fuente] = explode("|", $conjuro, 2);
                $resultado["conjuros"][] = ["name" => $nombre, "source" => $fuente, "source_id" => $conjuro, "prepared" => 0];
            }
        }
    }
    return $resultado;
}
