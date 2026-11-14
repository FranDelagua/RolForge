<?php

require_once __DIR__ . "/../Api/conexion.php";
require_once __DIR__ . "/../Api/persistencia-personaje.php";

function comprobarMysql($condicion, $mensaje) {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$conexion->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES'");
$conexion->exec("CREATE TEMPORARY TABLE characters (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL, level TINYINT UNSIGNED NOT NULL, experience INT UNSIGNED NOT NULL, strength TINYINT UNSIGNED NOT NULL, dexterity TINYINT UNSIGNED NOT NULL, constitution TINYINT UNSIGNED NOT NULL, intelligence TINYINT UNSIGNED NOT NULL, wisdom TINYINT UNSIGNED NOT NULL, charisma TINYINT UNSIGNED NOT NULL, hp INT NOT NULL, max_hp INT NOT NULL, temporary_hp INT NOT NULL, armor_class INT NOT NULL) ENGINE=InnoDB");
$conexion->exec("CREATE TEMPORARY TABLE character_entities (character_id INT UNSIGNED NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_name VARCHAR(150) NOT NULL, source VARCHAR(50) NOT NULL, source_id VARCHAR(255) NOT NULL) ENGINE=InnoDB");
$conexion->exec("CREATE TEMPORARY TABLE character_choices (character_id INT UNSIGNED NOT NULL, source_type VARCHAR(50) NOT NULL, source_id VARCHAR(255) NOT NULL, choice_key VARCHAR(100) NOT NULL, value VARCHAR(255) NOT NULL, value_source VARCHAR(50), value_source_id VARCHAR(255)) ENGINE=InnoDB");
$conexion->exec("CREATE TEMPORARY TABLE character_skills (character_id INT UNSIGNED NOT NULL, skill VARCHAR(50) NOT NULL, proficient TINYINT(1) NOT NULL, expertise TINYINT(1) NOT NULL, bonus SMALLINT NOT NULL) ENGINE=InnoDB");
$conexion->exec("CREATE TEMPORARY TABLE character_proficiencies (character_id INT UNSIGNED NOT NULL, proficiency_type VARCHAR(50) NOT NULL, name VARCHAR(150) NOT NULL, source VARCHAR(50), source_id VARCHAR(255)) ENGINE=InnoDB");
$conexion->exec("CREATE TEMPORARY TABLE character_spells (character_id INT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL, source VARCHAR(50) NOT NULL, source_id VARCHAR(255) NOT NULL, prepared TINYINT(1) NOT NULL) ENGINE=InnoDB");
$conexion->exec("CREATE TEMPORARY TABLE character_items (character_id INT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL, source VARCHAR(50), source_id VARCHAR(255), quantity INT UNSIGNED NOT NULL, equipped TINYINT(1) NOT NULL) ENGINE=InnoDB");
$conexion->exec("CREATE TEMPORARY TABLE character_data (character_id INT UNSIGNED NOT NULL, data_key VARCHAR(100) NOT NULL, data LONGTEXT NOT NULL) ENGINE=InnoDB");

$modelo = [
    "nombre" => "Prueba", "nivel" => 1, "experiencia" => 0,
    "atributos" => ["fuerza" => 10, "destreza" => 12, "constitucion" => 14, "inteligencia" => 10, "sabiduria" => 10, "carisma" => 10],
    "combate" => ["hp" => 12, "max_hp" => 12, "temporary_hp" => 0, "armor_class" => 11],
    "entidades" => [["entity_type" => "species", "entity_name" => "Elf", "source" => "PHB", "source_id" => "Elf|PHB"]],
    "elecciones" => [["source_type" => "species", "source_id" => "Elf|PHB", "choice_key" => "ejemplo", "value" => "valor", "value_source" => null, "value_source_id" => null]],
    "habilidades" => [["skill" => "perception", "proficient" => 1, "expertise" => 0, "bonus" => 2]],
    "competencias" => [["proficiency_type" => "language", "name" => "elvish", "source" => "PHB", "source_id" => "Elf|PHB"]],
    "conjuros" => [["name" => "Shield", "source" => "PHB", "source_id" => "Shield|PHB", "prepared" => 1]],
    "objetos" => [["name" => "Longsword", "source" => "PHB", "source_id" => "Longsword|PHB", "quantity" => 1, "equipped" => 1]],
    "datos" => ["notas" => ["contenido" => "Ejemplo"]]
];
$id = guardarPersonaje($conexion, 7, $modelo);
$consulta = $conexion->prepare("SELECT user_id, name FROM characters WHERE id = ?");
$consulta->execute([$id]);
$fila = $consulta->fetch(PDO::FETCH_ASSOC);
comprobarMysql((int)$fila["user_id"] === 7 && $fila["name"] === "Prueba", "No se guardó el personaje.");
foreach (["character_entities", "character_choices", "character_skills", "character_proficiencies", "character_spells", "character_items", "character_data"] as $tabla) {
    comprobarMysql((int)$conexion->query("SELECT COUNT(*) FROM " . $tabla)->fetchColumn() === 1, "No se guardó " . $tabla . ".");
}
$incorrecto = $modelo;
$incorrecto["datos"] = [str_repeat("x", 101) => "error"];
try {
    guardarPersonaje($conexion, 7, $incorrecto);
    throw new RuntimeException("La prueba debía provocar un error SQL.");
} catch (PDOException $error) {
    comprobarMysql((int)$conexion->query("SELECT COUNT(*) FROM characters")->fetchColumn() === 1, "No se revirtió la inserción principal.");
    comprobarMysql((int)$conexion->query("SELECT COUNT(*) FROM character_items")->fetchColumn() === 1, "No se revirtieron los objetos.");
}
echo "Persistencia MySQL: inserciones y rollback correctos en tablas temporales.\n";
