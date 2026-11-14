<?php

require_once __DIR__ . "/elecciones-raza.php";
require_once __DIR__ . "/dotes-5etools.php";
require_once __DIR__ . "/decisiones-estructuradas.php";

function cargarEntidadConstruccion($ruta, $coleccion, $referencia, $tipo) {
    $clave = claveReferencia5e($referencia, $tipo);
    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        throw new RuntimeException("No se pudieron leer los datos de " . $tipo . ".");
    }
    $datos = json_decode($contenido, true);
    if (!isset($datos[$coleccion]) || !is_array($datos[$coleccion])) {
        throw new RuntimeException("Los datos de " . $tipo . " no son válidos.");
    }
    foreach ($datos[$coleccion] as $entidad) {
        if (($entidad["name"] ?? "") . "|" . ($entidad["source"] ?? "") === $clave) {
            return $entidad;
        }
    }
    throw new InvalidArgumentException("La entidad " . $clave . " no existe en los datos locales.");
}

function cargarClaseConstruccion(array $referencia): array {
    $directorio = __DIR__ . "/../data/5etools/class/";
    $indice = json_decode(file_get_contents($directorio . "index.json"), true);
    if (!is_array($indice)) {
        throw new RuntimeException("No se pudo leer el índice de clases de 5etools.");
    }
    $clave = claveReferencia5e($referencia, "class");
    foreach ($indice as $archivo) {
        $datos = json_decode(file_get_contents($directorio . $archivo), true);
        if (!isset($datos["class"]) || !is_array($datos["class"])) {
            throw new RuntimeException("No se pudieron leer las clases de 5etools.");
        }
        foreach ($datos["class"] as $clase) {
            if (($clase["name"] ?? "") . "|" . ($clase["source"] ?? "") === $clave) {
                return $clase;
            }
        }
    }
    throw new InvalidArgumentException("La clase seleccionada no existe en los datos locales.");
}

function reglasHabilidadesClase(array $clase): array {
    $reglas = [];
    foreach ($clase["startingProficiencies"]["skills"] ?? [] as $indice => $regla) {
        $elige = $regla["choose"] ?? null;
        if (!is_array($elige) || !isset($elige["from"]) || !is_array($elige["from"]) ||
            !array_is_list($elige["from"]) || !$elige["from"] ||
            count(array_filter($elige["from"], "is_string")) !== count($elige["from"]) ||
            count(array_unique($elige["from"])) !== count($elige["from"]) ||
            !is_int($elige["count"] ?? 1) || ($elige["count"] ?? 1) < 1 ||
            ($elige["count"] ?? 1) > count($elige["from"]) ||
            count($regla) !== 1 || array_diff(array_keys($elige), ["from", "count"])) {
            throw new RuntimeException("La regla de habilidades de clase no está soportada.");
        }
        $reglas[$indice] = ["opciones" => $elige["from"], "cantidad" => $elige["count"] ?? 1];
    }
    return $reglas;
}

function validarHabilidadesClase(array $reglas, $seleccion, bool $parcial = false): bool {
    if (!is_array($seleccion) || array_diff(array_keys($seleccion), array_keys($reglas)) || (!$parcial && count($seleccion) !== count($reglas))) {
        return false;
    }
    $todas = [];
    foreach ($reglas as $indice => $regla) {
        $grupo = $seleccion[$indice] ?? ($parcial ? [] : null);
        if (!is_array($grupo) || !array_is_list($grupo) || count($grupo) > $regla["cantidad"] ||
            (!$parcial && count($grupo) !== $regla["cantidad"])) {
            return false;
        }
        foreach ($grupo as $habilidad) {
            if (!is_string($habilidad) || !in_array($habilidad, $regla["opciones"], true) || in_array($habilidad, $todas, true)) {
                return false;
            }
            $todas[] = $habilidad;
        }
    }
    return true;
}

function dotesFijasTrasfondo(array $background): array {
    $concesiones = $background["feats"] ?? [];
    if (count($concesiones) !== 1) {
        return [];
    }
    $catalogo = cargarDotes5e();
    $dotes = [];
    foreach ($concesiones as $grupo) {
        if (!is_array($grupo)) {
            throw new RuntimeException("La concesión de dotes del trasfondo no es válida.");
        }
        foreach ($grupo as $clave => $valor) {
            if ($valor !== true || !is_string($clave) || substr_count($clave, "|") !== 1) {
                continue;
            }
            [$nombre, $fuente] = explode("|", $clave);
            $dote = buscarDote5e($catalogo, $nombre, $fuente);
            if ($dote === null) {
                throw new RuntimeException("No se encontró la dote concedida " . $clave . ".");
            }
            $dotes[] = $dote;
        }
    }
    return $dotes;
}

function variantesDote5e(array $dote): array {
    $primeraEntrada = $dote["entries"][0] ?? null;
    if (!is_string($primeraEntrada) ||
        !preg_match('/\bchoose one of the benefits listed below\b/i', $primeraEntrada)) {
        return [];
    }
    $variantes = [];
    foreach ($dote["_versions"] ?? [] as $variante) {
        if (!is_array($variante) || !is_string($variante["name"] ?? null) ||
            !is_string($variante["source"] ?? null) ||
            strpos($variante["name"], $dote["name"] . "; ") !== 0) {
            throw new RuntimeException("Una variante de la dote no tiene una referencia compatible.");
        }
        $clave = $variante["name"] . "|" . $variante["source"];
        $variantes[$clave] = ["tipo" => "feat", "name" => $variante["name"], "source" => $variante["source"]];
    }
    return $variantes;
}

function nombreVarianteDote5e(array $dote, string $nombre): string {
    $nombre = str_replace($dote["name"] . "; ", "", $nombre);
    $coincidencias = [];
    foreach ($dote["entries"] ?? [] as $entrada) {
        $titulo = is_array($entrada) ? ($entrada["name"] ?? null) : null;
        if (is_string($titulo) && (strcasecmp($titulo, $nombre) === 0 || stripos($titulo, $nombre . " ") === 0)) {
            $coincidencias[] = $titulo;
        }
    }
    return count($coincidencias) === 1 ? $coincidencias[0] : $nombre;
}

function datosArchivoClase(array $referencia): array {
    $directorio = __DIR__ . "/../data/5etools/class/";
    $indice = json_decode(file_get_contents($directorio . "index.json"), true);
    if (!is_array($indice)) {
        throw new RuntimeException("No se pudo leer el índice de clases de 5etools.");
    }
    $clave = claveReferencia5e($referencia, "class");
    foreach ($indice as $archivo) {
        $datos = json_decode(file_get_contents($directorio . $archivo), true);
        if (!is_array($datos) || !isset($datos["class"]) || !is_array($datos["class"])) {
            throw new RuntimeException("No se pudieron leer las clases de 5etools.");
        }
        foreach ($datos["class"] as $clase) {
            if (($clase["name"] ?? "") . "|" . ($clase["source"] ?? "") === $clave) {
                return $datos;
            }
        }
    }
    throw new InvalidArgumentException("La clase seleccionada no existe en los datos locales.");
}

function entidadesRasgosConstruccion(array $datos, array $clase, ?array $subclase, int $nivel): array {
    $entidades = [];
    $advertencias = [];
    $vistos = [];
    $cola = [];
    foreach ($clase["classFeatures"] ?? [] as $entrada) {
        $ref = is_array($entrada) ? ($entrada["classFeature"] ?? null) : $entrada;
        if (is_string($ref)) {
            $cola[] = ["classFeature", $ref];
        }
    }
    if ($subclase !== null) {
        $identidad = referenciaSubclase5e($subclase);
        $encontrada = null;
        foreach ($datos["subclass"] ?? [] as $candidata) {
            if (($candidata["name"] ?? "") === $identidad["name"] &&
                ($candidata["source"] ?? "") === $identidad["source"] &&
                ($candidata["className"] ?? "") === $identidad["className"] &&
                ($candidata["classSource"] ?? "") === $identidad["classSource"] &&
                ($candidata["shortName"] ?? "") === $identidad["shortName"]) {
                $encontrada = $candidata;
                break;
            }
        }
        if ($encontrada === null) {
            throw new InvalidArgumentException("La subclase seleccionada no existe en los datos de la clase.");
        }
        foreach ($encontrada["subclassFeatures"] ?? [] as $ref) {
            if (is_string($ref)) {
                $cola[] = ["subclassFeature", $ref];
            }
        }
    }
    while ($cola) {
        [$tipo, $ref] = array_shift($cola);
        $partes = explode("|", $ref);
        $indiceNivel = $tipo === "classFeature" ? 3 : 5;
        if (!isset($partes[$indiceNivel]) || !ctype_digit($partes[$indiceNivel])) {
            $advertencias[] = ["entidad" => $clase["name"] . " | " . $clase["source"], "source_id" => $ref, "decision" => "Referencia de rasgo", "motivo" => "No se pudo interpretar el nivel del rasgo."];
            continue;
        }
        if ((int)$partes[$indiceNivel] > $nivel) {
            continue;
        }
        $nombreClase = $partes[1] ?? "";
        $fuenteClase = !empty($partes[2]) ? $partes[2] : "PHB";
        $fuente = $tipo === "classFeature" ? ($partes[4] ?? "") : ($partes[6] ?? "");
        if ($fuente === "") {
            $fuente = $tipo === "classFeature" ? $fuenteClase : ($partes[4] ?? "");
        }
        $coincidencia = null;
        foreach ($datos[$tipo] ?? [] as $rasgo) {
            if (($rasgo["name"] ?? "") !== ($partes[0] ?? "") ||
                ($rasgo["className"] ?? "") !== $nombreClase ||
                ($rasgo["classSource"] ?? "") !== $fuenteClase ||
                ($rasgo["source"] ?? "") !== $fuente ||
                ($rasgo["level"] ?? null) !== (int)$partes[$indiceNivel]) {
                continue;
            }
            if ($tipo === "subclassFeature" &&
                (($rasgo["subclassShortName"] ?? "") !== ($partes[3] ?? "") ||
                 ($rasgo["subclassSource"] ?? "") !== ($partes[4] ?? ""))) {
                continue;
            }
            $coincidencia = $rasgo;
            break;
        }
        if ($coincidencia === null) {
            $advertencias[] = ["entidad" => $partes[0] . " | " . $fuente, "source_id" => $ref, "decision" => "Referencia de rasgo", "motivo" => "No se encontraron los datos del rasgo en el archivo de la clase."];
            if ($fuente !== "" && ($partes[0] ?? "") !== "") {
                $id = $tipo . "|" . $ref;
                if (!isset($vistos[$id])) {
                    $vistos[$id] = true;
                    $entidades[] = ["entity_type" => "feature", "entity_name" => $partes[0], "source" => $fuente, "source_id" => $id];
                }
            }
            continue;
        }
        $id = $tipo . "|" . $ref;
        if (isset($vistos[$id])) {
            continue;
        }
        $vistos[$id] = true;
        $entidades[] = ["entity_type" => "feature", "entity_name" => $coincidencia["name"], "source" => $coincidencia["source"], "source_id" => $id];
        $buscar = function ($nodo) use (&$buscar, &$cola) {
            if (!is_array($nodo)) {
                return;
            }
            if (($nodo["type"] ?? "") === "refClassFeature" && is_string($nodo["classFeature"] ?? null)) {
                $cola[] = ["classFeature", $nodo["classFeature"]];
            } elseif (($nodo["type"] ?? "") === "refSubclassFeature" && is_string($nodo["subclassFeature"] ?? null)) {
                $cola[] = ["subclassFeature", $nodo["subclassFeature"]];
            }
            foreach ($nodo as $valor) {
                if (is_array($valor)) {
                    $buscar($valor);
                }
            }
        };
        $buscar($coincidencia["entries"] ?? []);
    }
    return ["entidades" => $entidades, "advertencias" => $advertencias];
}

function resolverPersonaje(array $personaje): array {
    $pendientes = pendientesPersonaje($personaje);
    $elecciones = array_values(array_filter($pendientes, function ($p) { return ($p["tipo"] ?? "") === "eleccion"; }));
    $advertencias = array_values(array_filter($pendientes, function ($p) { return ($p["tipo"] ?? "") !== "eleccion"; }));
    $datosRaza = cargarDatosEleccionesRaza($personaje);
    $raza = $datosRaza["raza"];
    $datosClase = datosArchivoClase($personaje["clase"]);
    $clase = cargarClaseConstruccion($personaje["clase"]);
    $background = cargarEntidadConstruccion(__DIR__ . "/../data/5etools/backgrounds.json", "background", $personaje["background"], "background");
    $subclaseDatos = null;
    if (isset($personaje["subclase"])) {
        $identidad = referenciaSubclase5e($personaje["subclase"]);
        foreach ($datosClase["subclass"] ?? [] as $candidata) {
            if (($candidata["name"] ?? "") === $identidad["name"] && ($candidata["source"] ?? "") === $identidad["source"] &&
                ($candidata["className"] ?? "") === $identidad["className"] && ($candidata["classSource"] ?? "") === $identidad["classSource"] &&
                ($candidata["shortName"] ?? "") === $identidad["shortName"]) {
                $subclaseDatos = $candidata;
                break;
            }
        }
    }
    $estructuradas = decisionesEstructuradas($personaje, $raza, $clase, $background, $subclaseDatos);
    $elecciones = array_merge($elecciones, $estructuradas["pendientes"]);
    $advertencias = array_merge($advertencias, $estructuradas["advertencias"]);
    $rasgos = entidadesRasgosConstruccion($datosClase, $clase, $personaje["subclase"] ?? null, (int)$personaje["nivel"]);
    $entidades = $rasgos["entidades"];
    $advertencias = array_merge($advertencias, $rasgos["advertencias"]);
    $eleccionesResueltas = $estructuradas["elecciones"];
    foreach (dotesFijasTrasfondo($background) as $indice => $dote) {
        $id = $dote["name"] . "|" . $dote["source"];
        $entidades[] = ["entity_type" => "feat", "entity_name" => $dote["name"], "source" => $dote["source"], "source_id" => $id];
        $eleccionesResueltas[] = ["source_type" => "background", "source_id" => claveReferencia5e($personaje["background"], "background"), "choice_key" => "dotes." . $indice, "value" => $dote["name"], "value_source" => $dote["source"], "value_source_id" => $id];
        $variantes = variantesDote5e($dote);
        $clave = $personaje["background"]["elecciones"]["variantes_dotes"][$indice] ?? null;
        if (is_string($clave) && isset($variantes[$clave])) {
            $variante = $variantes[$clave];
            $eleccionesResueltas[] = ["source_type" => "feat", "source_id" => $id, "choice_key" => "variante", "value" => $variante["name"], "value_source" => $variante["source"], "value_source_id" => $clave];
        }
    }
    $mapaAtributos = ["str" => "fuerza", "dex" => "destreza", "con" => "constitucion", "int" => "inteligencia", "wis" => "sabiduria", "cha" => "carisma"];
    $atributos = [];
    foreach ($mapaAtributos as $clave => $nombre) {
        if (!isset($personaje["atributos"][$nombre]) || !is_int($personaje["atributos"][$nombre])) {
            throw new InvalidArgumentException("La característica " . $nombre . " no es válida.");
        }
        $atributos[$clave] = $personaje["atributos"][$nombre] + ($personaje["raza"]["bonificadores"][$clave] ?? 0);
        foreach ($personaje["raza"]["elecciones"]["dotes"] ?? [] as $dote) {
            $atributos[$clave] += $dote["elecciones"]["atributos"]["bonificadores"][$clave] ?? 0;
        }
    }
    $competentes = [];
    foreach ($estructuradas["habilidades"] as $nombre) {
        $competentes[strtolower($nombre)] = true;
    }
    foreach ([$raza, $background] as $origen) {
        if (count($origen["skillProficiencies"] ?? []) === 1) {
            foreach ($origen["skillProficiencies"][0] as $nombre => $valor) {
                if ($valor === true) {
                    $competentes[strtolower($nombre)] = true;
                }
            }
        }
    }
    try {
        $reglas = reglasHabilidadesClase($clase);
        $seleccion = $personaje["clase"]["elecciones"]["habilidades"] ?? [];
        if ($reglas && validarHabilidadesClase($reglas, $seleccion)) {
            foreach ($seleccion as $grupo) {
                foreach ($grupo as $nombre) {
                    $competentes[strtolower($nombre)] = true;
                }
            }
        }
    } catch (RuntimeException $error) {
        // El diagnóstico ya incluye esta regla no soportada.
    }
    $datosHabilidades = json_decode(file_get_contents(__DIR__ . "/../data/5etools/skills.json"), true);
    if (!isset($datosHabilidades["skill"]) || !is_array($datosHabilidades["skill"])) {
        throw new RuntimeException("No se pudieron leer las habilidades de 5etools.");
    }
    $habilidades = [];
    $bonoCompetencia = 2 + intdiv(max(0, (int)$personaje["nivel"] - 1), 4);
    foreach ($datosHabilidades["skill"] as $habilidad) {
        if (($habilidad["source"] ?? "") !== "PHB" || !isset($mapaAtributos[$habilidad["ability"] ?? ""])) {
            continue;
        }
        $nombre = $habilidad["name"];
        $proficiente = isset($competentes[strtolower($nombre)]) ? 1 : 0;
        $habilidades[] = ["skill" => $nombre, "proficient" => $proficiente, "expertise" => 0, "bonus" => (int)floor(($atributos[$habilidad["ability"]] - 10) / 2) + $proficiente * $bonoCompetencia];
    }
    $competencias = $estructuradas["competencias"];
    foreach ([$raza, $background] as $origen) {
        foreach (["toolProficiencies" => "tool"] as $campo => $tipo) {
            if (count($origen[$campo] ?? []) !== 1) {
                continue;
            }
            foreach ($origen[$campo][0] as $nombre => $valor) {
                if ($valor === true) {
                    $competencias[] = ["proficiency_type" => $tipo, "name" => $nombre, "source" => $origen["source"], "source_id" => $origen["name"] . "|" . $origen["source"]];
                }
            }
        }
    }
    foreach (["weapons" => "weapon", "armor" => "armor"] as $campo => $tipo) {
        foreach ($clase["startingProficiencies"][$campo] ?? [] as $nombre) {
            if (is_string($nombre)) {
                $competencias[] = ["proficiency_type" => $tipo, "name" => $nombre, "source" => $clase["source"], "source_id" => $clase["name"] . "|" . $clase["source"]];
            }
        }
        foreach ($clase["proficiency"] ?? [] as $nombre) {
            if (is_string($nombre)) {
                $competencias[] = ["proficiency_type" => "saving_throw", "name" => $nombre, "source" => $clase["source"], "source_id" => $clase["name"] . "|" . $clase["source"]];
            }
        }
    }
    $hp = 0;
    if ((int)$personaje["nivel"] === 1 && is_int($clase["hd"]["faces"] ?? null)) {
        $hp = max(1, $clase["hd"]["faces"] + (int)floor(($atributos["con"] - 10) / 2));
    }
    $magia = [];
    foreach (["spellcastingAbility", "casterProgression"] as $campo) {
        if (isset($clase[$campo])) {
            $magia[$campo] = $clase[$campo];
        }
    }
    foreach (["cantripProgression" => "cantripsKnown", "spellsKnownProgression" => "spellsKnown"] as $campo => $nombre) {
        if (isset($clase[$campo][(int)$personaje["nivel"] - 1])) {
            $magia[$nombre] = $clase[$campo][(int)$personaje["nivel"] - 1];
        }
    }
    foreach ($clase["classTableGroups"] ?? [] as $tabla) {
        foreach ($tabla["colLabels"] ?? [] as $i => $etiqueta) {
            $valor = $tabla["rows"][(int)$personaje["nivel"] - 1][$i] ?? null;
            if ($etiqueta === "Spell Slots" && is_int($valor)) {
                $magia["spellSlots"] = $valor;
            } elseif ($etiqueta === "Slot Level" && is_string($valor) && preg_match('/\b(\d+)(?:st|nd|rd|th)\b/i', $valor, $m)) {
                $magia["slotLevel"] = (int)$m[1];
            }
        }
    }
    $resolucion = [
        "combate" => ["hp" => $hp, "max_hp" => $hp, "temporary_hp" => 0, "armor_class" => 10 + (int)floor(($atributos["dex"] - 10) / 2)],
        "habilidades" => $habilidades, "competencias" => $competencias, "conjuros" => $estructuradas["conjuros"], "objetos" => $estructuradas["objetos"],
        "entidades" => $entidades, "elecciones" => $eleccionesResueltas,
        "datos" => ["reglas_no_soportadas" => $advertencias, "detalles_equipo" => $estructuradas["detalles_equipo"], "especie" => $estructuradas["especie"], "magia" => $magia]
    ];
    return ["resolucion" => $resolucion, "elecciones" => $elecciones, "advertencias" => $advertencias, "decisiones" => $estructuradas["decisiones"]];
}

function pendientesPersonaje(array $personaje): array {
    $pendientes = [];
    foreach (["raza" => "Especie", "clase" => "Clase", "background" => "Trasfondo"] as $campo => $nombre) {
        if (!isset($personaje[$campo])) {
            $pendientes[] = ["tipo" => "eleccion", "entidad" => $nombre, "decision" => "Seleccionar " . strtolower($nombre), "motivo" => "Falta la entidad en el personaje."];
        }
    }
    if ($pendientes) {
        return $pendientes;
    }
    if (!eleccionesRazaConfirmadas($personaje)) {
        $pendientes[] = ["tipo" => "eleccion", "entidad" => $personaje["raza"]["name"], "decision" => "Elecciones de especie y dote", "motivo" => "Falta una elección o no es válida."];
    }
    $raza = cargarDatosEleccionesRaza($personaje)["raza"];
    $pendientes[] = ["entidad" => $raza["name"] . " | " . $raza["source"], "decision" => "Efectos de especie", "motivo" => "Algunos rasgos y elecciones pueden no estar interpretados."];
    $dotesRaza = $personaje["raza"]["elecciones"]["dotes"] ?? [];
    $catalogoDotes = $dotesRaza ? cargarDotes5e() : [];
    foreach ($dotesRaza as $dote) {
        $datosDote = buscarDote5e($catalogoDotes, $dote["name"], $dote["source"]);
        if ($datosDote === null) {
            throw new InvalidArgumentException("La dote seleccionada no existe en los datos locales.");
        }
        $pendientes[] = ["entidad" => $dote["name"] . " | " . $dote["source"], "source_id" => $dote["name"] . "|" . $dote["source"], "decision" => "Efectos y elecciones adicionales de la dote", "motivo" => "La referencia y la mejora elegida se conservan; otros efectos pueden no estar interpretados."];
    }

    $directorio = __DIR__ . "/../data/5etools/";
    $claveClase = claveReferencia5e($personaje["clase"], "class");
    $clase = cargarClaseConstruccion($personaje["clase"]);
    $primerNivelSubclase = null;
    foreach ($clase["classFeatures"] ?? [] as $rasgo) {
        $referencia = is_array($rasgo) ? ($rasgo["classFeature"] ?? "") : $rasgo;
        if (!is_string($referencia)) {
            throw new RuntimeException("Una referencia de característica de clase no es válida.");
        }
        $partes = explode("|", $referencia);
        if (is_array($rasgo) && ($rasgo["gainSubclassFeature"] ?? false) === true) {
            if (!isset($partes[3]) || !ctype_digit($partes[3])) {
                throw new RuntimeException("No se pudo determinar el nivel de subclase.");
            }
            $nivel = (int)$partes[3];
            $primerNivelSubclase = $primerNivelSubclase === null ? $nivel : min($primerNivelSubclase, $nivel);
        }
        if (isset($partes[3]) && ctype_digit($partes[3]) && (int)$partes[3] <= ($personaje["nivel"] ?? 1)) {
            $fuenteRasgo = !empty($partes[4]) ? $partes[4] : $clase["source"];
            $pendientes[] = ["entidad" => $partes[0] . " | " . $fuenteRasgo, "source_id" => $referencia, "decision" => "Efectos del rasgo de clase", "motivo" => "La referencia se conserva; algunos efectos pueden no estar interpretados."];
        }
    }
    if ($primerNivelSubclase !== null && ($personaje["nivel"] ?? 1) >= $primerNivelSubclase && !isset($personaje["subclase"])) {
        $pendientes[] = ["tipo" => "eleccion", "entidad" => $claveClase, "decision" => "Seleccionar subclase", "motivo" => "La clase obtiene una subclase a este nivel."];
    }
    try {
        $reglas = reglasHabilidadesClase($clase);
        if ($reglas && !validarHabilidadesClase($reglas, $personaje["clase"]["elecciones"]["habilidades"] ?? [])) {
            foreach ($reglas as $regla) {
                $pendientes[] = ["tipo" => "eleccion", "entidad" => $claveClase, "decision" => "Elegir " . $regla["cantidad"] . " competencia(s) de habilidad: " . implode(", ", $regla["opciones"]), "motivo" => "Selecciona las habilidades de clase."];
            }
        }
    } catch (RuntimeException $error) {
        $pendientes[] = ["entidad" => $claveClase, "decision" => "Competencias de habilidad de clase", "motivo" => $error->getMessage()];
    }
    if (!empty($clase["startingEquipment"])) {
        $pendientes[] = ["entidad" => $claveClase, "decision" => "Equipo inicial de clase", "motivo" => "Algunas categorías y alternativas pueden no estar interpretadas."];
    }
    $pendientes[] = ["entidad" => $claveClase, "decision" => "Efectos sobre estadísticas de combate", "motivo" => "Se calculan valores básicos; no se aplican todos los posibles efectos."];

    $background = cargarEntidadConstruccion($directorio . "backgrounds.json", "background", $personaje["background"], "background");
    $nombreBackground = $background["name"] . " | " . $background["source"];
    if (isset($background["_copy"])) {
        $pendientes[] = ["entidad" => $nombreBackground, "decision" => "Datos heredados del trasfondo", "motivo" => "La referencia _copy del trasfondo todavía no se resuelve."];
    }
    foreach (["skillProficiencies" => "Habilidades", "toolProficiencies" => "Herramientas", "languageProficiencies" => "Idiomas", "skillToolLanguageProficiencies" => "Habilidades, herramientas e idiomas", "ability" => "Mejoras de características", "startingEquipment" => "Equipo inicial", "additionalSpells" => "Conjuros concedidos o ampliados"] as $campo => $decision) {
        if (!empty($background[$campo])) {
            $soportado = in_array($campo, ["skillProficiencies", "toolProficiencies", "languageProficiencies"], true) && count($background[$campo]) === 1;
            if ($soportado) {
                foreach ($background[$campo][0] as $clave => $valor) {
                    if ($valor !== true && !($campo === "languageProficiencies" && $clave === "anyStandard" && is_int($valor) && $valor > 0)) {
                        $soportado = false;
                    }
                }
            }
            if (!$soportado) {
                $pendientes[] = ["entidad" => $nombreBackground, "decision" => $decision . " del trasfondo", "motivo" => "Algunas concesiones u opciones pueden no estar interpretadas."];
            }
        }
    }
    foreach ($background["feats"] ?? [] as $grupo) {
        foreach ($grupo as $referencia => $valor) {
            if ($valor === true) {
                $pendientes[] = ["entidad" => $nombreBackground, "decision" => "Dote concedida: " . $referencia, "motivo" => "La referencia se conserva; algunos efectos pueden no estar interpretados."];
            } else {
                $pendientes[] = ["entidad" => $nombreBackground, "decision" => "Elección de dote", "motivo" => "La concesión de dote del trasfondo todavía no se resuelve."];
            }
        }
        foreach (dotesFijasTrasfondo($background) as $indice => $dote) {
            $variantes = variantesDote5e($dote);
            $seleccion = $personaje["background"]["elecciones"]["variantes_dotes"][$indice] ?? null;
            if ($variantes && (!is_string($seleccion) || !isset($variantes[$seleccion]))) {
                $pendientes[] = ["tipo" => "eleccion", "entidad" => $dote["name"] . " | " . $dote["source"], "decision" => "Elegir variante de la dote", "motivo" => "Selecciona una de las variantes disponibles."];
            }
        }
        $textos = [];
        $entries = $background["entries"] ?? [];
        if (is_array($entries)) {
            array_walk_recursive($entries, function ($texto) use (&$textos) {
                if (is_string($texto) && preg_match('/\bmust choose\b/i', $texto)) {
                    $textos[] = $texto;
                }
            });
        }
        foreach ($textos as $texto) {
            $pendientes[] = ["entidad" => $nombreBackground, "decision" => $texto, "motivo" => "La elección expresada en el texto todavía no se interpreta."];
        }
    }
    if (isset($personaje["subclase"])) {
        $pendientes[] = ["entidad" => $personaje["subclase"]["name"] . " | " . $personaje["subclase"]["source"], "decision" => "Efectos de subclase", "motivo" => "Las referencias se conservan; algunos efectos pueden no estar interpretados."];
    }
    return $pendientes;
}
