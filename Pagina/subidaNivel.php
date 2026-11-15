<?php

require_once __DIR__ . "/../Api/autenticacion.php";
exigirUsuario();
require_once __DIR__ . "/../Api/conexion.php";
require_once __DIR__ . "/../Api/ficha-personaje.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if (!$id) {
    http_response_code(404);
    die("Personaje no encontrado.");
}
$consulta = $conexion->prepare("SELECT id, name, level, experience FROM characters WHERE id = ? AND user_id = ?");
$consulta->execute([$id, $_SESSION["usuario_id"]]);
$personaje = $consulta->fetch(PDO::FETCH_ASSOC);
if (!$personaje) {
    http_response_code(404);
    die("Personaje no encontrado.");
}
$consulta = $conexion->prepare(
    "SELECT entity_type, entity_name, source, source_id
     FROM character_entities
     WHERE character_id = ? AND entity_type IN ('class', 'subclass')
     ORDER BY CASE entity_type WHEN 'class' THEN 0 ELSE 1 END, id"
);
$consulta->execute([$id]);
$entidades = $consulta->fetchAll(PDO::FETCH_ASSOC);
$clase = null;
$subclase = null;
foreach ($entidades as $entidad) {
    if ($entidad["entity_type"] === "class") $clase = $entidad;
    if ($entidad["entity_type"] === "subclass") $subclase = $entidad;
}
$nivelActual = (int)$personaje["level"];
if ($nivelActual >= 20) {
    http_response_code(409);
    die("Este personaje ya ha alcanzado el nivel máximo.");
}
$nivelSiguiente = $nivelActual + 1;
$rasgosSiguientes = [];
if ($clase !== null) {
    try {
        $datosClase = datosArchivoClase(["tipo" => "class", "name" => $clase["entity_name"], "source" => $clase["source"]]);
        foreach ($datosClase["class"] ?? [] as $registroClase) {
            if (($registroClase["name"] ?? null) !== $clase["entity_name"] ||
                ($registroClase["source"] ?? null) !== $clase["source"]) continue;
            foreach ($registroClase["classFeatures"] ?? [] as $referencia) {
                $ref = is_array($referencia) ? ($referencia["classFeature"] ?? null) : $referencia;
                if (!is_string($ref)) continue;
                $partes = explode("|", $ref);
                if ((int)($partes[3] ?? 0) !== $nivelSiguiente) continue;
                $rasgo = ["entity_name" => $partes[0], "source" => $partes[4] ?? $clase["source"], "source_id" => "classFeature|" . $ref];
                $rasgosSiguientes[] = ["nombre" => $rasgo["entity_name"], "datos" => rasgoFicha($datosClase, $rasgo)];
            }
            break;
        }
        if ($subclase !== null) {
            $identidadSubclase = referenciaSubclase5e([
                "name" => $subclase["entity_name"], "source" => $subclase["source"],
                "className" => $clase["entity_name"], "classSource" => $clase["source"],
                "shortName" => (explode("|", $subclase["source_id"])[4] ?? "")
            ]);
            foreach ($datosClase["subclass"] ?? [] as $registroSubclase) {
                if (($registroSubclase["name"] ?? null) !== $identidadSubclase["name"] ||
                    ($registroSubclase["source"] ?? null) !== $identidadSubclase["source"] ||
                    ($registroSubclase["className"] ?? null) !== $identidadSubclase["className"] ||
                    ($registroSubclase["classSource"] ?? null) !== $identidadSubclase["classSource"] ||
                    ($registroSubclase["shortName"] ?? null) !== $identidadSubclase["shortName"]) continue;
                foreach ($registroSubclase["subclassFeatures"] ?? [] as $ref) {
                    if (!is_string($ref)) continue;
                    $partes = explode("|", $ref);
                    if ((int)($partes[5] ?? 0) !== $nivelSiguiente) continue;
                    $rasgo = ["entity_name" => $partes[0], "source" => $partes[6] ?? $subclase["source"], "source_id" => "subclassFeature|" . $ref];
                    $rasgosSiguientes[] = ["nombre" => $rasgo["entity_name"], "datos" => rasgoFicha($datosClase, $rasgo)];
                }
                break;
            }
        }
    } catch (RuntimeException | InvalidArgumentException $error) {
        error_log("No se pudieron cargar los avances para la subida de nivel: " . $error->getMessage());
        http_response_code(500);
        die("No se pudieron consultar los avances de clase.");
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir de nivel - RolForge</title>
    <link rel="stylesheet" href="../Api/global.css">
</head>
<body>
<main class="crear-personaje">
    <header class="cabecera">
        <div class="logo">⚔ ROLFORGE ⚔</div>
        <div class="usuario">👤 <?= htmlspecialchars($_SESSION["usuario"], ENT_QUOTES, "UTF-8") ?> <a href="personaje.php?id=<?= (int)$personaje["id"] ?>">Volver a la ficha</a></div>
    </header>
    <div class="formulario-personaje ficha-personaje">
        <h1>Subir de nivel</h1>
        <p><?= htmlspecialchars($personaje["name"], ENT_QUOTES, "UTF-8") ?> · Nivel <?= $nivelActual ?> → Nivel <?= $nivelSiguiente ?></p>
        <section class="seccion-creacion">
            <h2>Avances del siguiente nivel</h2>
            <?php if ($rasgosSiguientes): ?>
                <?php foreach ($rasgosSiguientes as $rasgo): ?>
                    <details class="ficha-elemento">
                        <summary><?= htmlspecialchars($rasgo["nombre"], ENT_QUOTES, "UTF-8") ?></summary>
                        <?php if ($rasgo["datos"] !== null && !empty($rasgo["datos"]["entries"])): ?>
                            <?= renderizarEntradaDote5e($rasgo["datos"]["entries"]) ?>
                        <?php else: ?>
                            <p>La descripción de este rasgo no está disponible en los datos locales.</p>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Los datos locales no indican rasgos nuevos de clase o subclase para el siguiente nivel.</p>
            <?php endif; ?>
        </section>
        <section class="seccion-creacion">
            <h2>Próximos pasos</h2>
            <p>Esta pantalla prepara la subida de nivel y muestra los rasgos disponibles. El nivel y las estadísticas del personaje todavía no se han modificado.</p>
            <p>La aplicación del aumento de puntos de golpe, espacios de conjuro y elecciones de clase/subclase debe completarse antes de confirmar el nuevo nivel.</p>
        </section>
        <div class="acciones-creacion">
            <a class="boton-subir-nivel" href="personaje.php?id=<?= (int)$personaje["id"] ?>">Volver a la ficha</a>
        </div>
    </div>
</main>
</body>
</html>
