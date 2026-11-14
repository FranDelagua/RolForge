<?php

function guardarPersonaje(PDO $conexion, int $usuarioId, array $modelo): int {
    if ($usuarioId < 1) {
        throw new InvalidArgumentException("El usuario autenticado no es válido.");
    }
    foreach (["nombre", "nivel", "experiencia", "atributos", "combate", "entidades", "elecciones", "habilidades", "competencias", "conjuros", "objetos", "datos"] as $campo) {
        if (!array_key_exists($campo, $modelo)) {
            throw new InvalidArgumentException("Falta el campo de personaje: " . $campo);
        }
    }
    foreach (["atributos", "combate", "entidades", "elecciones", "habilidades", "competencias", "conjuros", "objetos", "datos"] as $campo) {
        if (!is_array($modelo[$campo])) {
            throw new InvalidArgumentException("El campo de personaje no es válido: " . $campo);
        }
    }
    foreach (["fuerza", "destreza", "constitucion", "inteligencia", "sabiduria", "carisma"] as $campo) {
        if (!isset($modelo["atributos"][$campo]) || !is_int($modelo["atributos"][$campo]) || $modelo["atributos"][$campo] < 1 || $modelo["atributos"][$campo] > 255) {
            throw new InvalidArgumentException("La característica no es válida: " . $campo);
        }
    }
    foreach (["hp", "max_hp", "temporary_hp", "armor_class"] as $campo) {
        if (!isset($modelo["combate"][$campo]) || !is_int($modelo["combate"][$campo])) {
            throw new InvalidArgumentException("Falta el dato de combate: " . $campo);
        }
    }
    if (!is_string($modelo["nombre"]) || trim($modelo["nombre"]) === "" || !is_int($modelo["nivel"]) || $modelo["nivel"] < 1 || $modelo["nivel"] > 255 || !is_int($modelo["experiencia"]) || $modelo["experiencia"] < 0) {
        throw new InvalidArgumentException("Los datos principales del personaje no son válidos.");
    }

    $conexion->beginTransaction();
    try {
        $consulta = $conexion->prepare(
            "INSERT INTO characters (user_id, name, level, experience, strength, dexterity, constitution, intelligence, wisdom, charisma, hp, max_hp, temporary_hp, armor_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $a = $modelo["atributos"];
        $c = $modelo["combate"];
        $consulta->execute([$usuarioId, $modelo["nombre"], $modelo["nivel"], $modelo["experiencia"], $a["fuerza"], $a["destreza"], $a["constitucion"], $a["inteligencia"], $a["sabiduria"], $a["carisma"], $c["hp"], $c["max_hp"], $c["temporary_hp"], $c["armor_class"]]);
        $id = (int)$conexion->lastInsertId();
        if ($id < 1) {
            throw new RuntimeException("No se pudo obtener el identificador del personaje.");
        }

        $tablas = [
            "entidades" => ["INSERT INTO character_entities (character_id, entity_type, entity_name, source, source_id) VALUES (?, ?, ?, ?, ?)", ["entity_type", "entity_name", "source", "source_id"]],
            "elecciones" => ["INSERT INTO character_choices (character_id, source_type, source_id, choice_key, value, value_source, value_source_id) VALUES (?, ?, ?, ?, ?, ?, ?)", ["source_type", "source_id", "choice_key", "value", "value_source", "value_source_id"]],
            "habilidades" => ["INSERT INTO character_skills (character_id, skill, proficient, expertise, bonus) VALUES (?, ?, ?, ?, ?)", ["skill", "proficient", "expertise", "bonus"]],
            "competencias" => ["INSERT INTO character_proficiencies (character_id, proficiency_type, name, source, source_id) VALUES (?, ?, ?, ?, ?)", ["proficiency_type", "name", "source", "source_id"]],
            "conjuros" => ["INSERT INTO character_spells (character_id, name, source, source_id, prepared) VALUES (?, ?, ?, ?, ?)", ["name", "source", "source_id", "prepared"]],
            "objetos" => ["INSERT INTO character_items (character_id, name, source, source_id, quantity, equipped) VALUES (?, ?, ?, ?, ?, ?)", ["name", "source", "source_id", "quantity", "equipped"]]
        ];
        foreach ($tablas as $tipo => [$sql, $campos]) {
            $consulta = $conexion->prepare($sql);
            foreach ($modelo[$tipo] as $fila) {
                if (!is_array($fila)) {
                    throw new InvalidArgumentException("Registro de " . $tipo . " no válido.");
                }
                $valores = [$id];
                foreach ($campos as $campo) {
                    if (!array_key_exists($campo, $fila)) {
                        throw new InvalidArgumentException("Falta " . $campo . " en " . $tipo . ".");
                    }
                    $valor = $fila[$campo];
                    $admiteNulo = ($tipo === "elecciones" && in_array($campo, ["value_source", "value_source_id"], true)) ||
                        (in_array($tipo, ["competencias", "objetos"], true) && in_array($campo, ["source", "source_id"], true));
                    $entero = in_array($campo, ["proficient", "expertise", "bonus", "prepared", "quantity", "equipped"], true);
                    if ($valor === null && $admiteNulo) {
                        $valores[] = null;
                    } elseif ($entero && is_int($valor)) {
                        $valores[] = $valor;
                    } elseif (!$entero && is_string($valor) && $valor !== "") {
                        $valores[] = $valor;
                    } else {
                        throw new InvalidArgumentException("El campo " . $campo . " de " . $tipo . " no es válido.");
                    }
                }
                $consulta->execute($valores);
            }
        }
        $consulta = $conexion->prepare("INSERT INTO character_data (character_id, data_key, data) VALUES (?, ?, ?)");
        foreach ($modelo["datos"] as $clave => $dato) {
            if (!is_string($clave) || $clave === "") {
                throw new InvalidArgumentException("La clave de datos adicionales no es válida.");
            }
            $consulta->execute([$id, $clave, json_encode($dato, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        }
        $conexion->commit();
        return $id;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        throw $error;
    }
}
