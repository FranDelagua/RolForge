<?php

require_once __DIR__ . "/autenticacion.php";
iniciarSesionSegura();
if (empty($_SESSION["usuario_id"]) || ($_SESSION["auth_schema"] ?? "") !== "users") {
    http_response_code(401);
    die("Debes iniciar sesión para guardar el personaje.");
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die("Método no permitido.");
}
if (!isset($_SESSION["token_guardar_personaje"]) || !is_string($_POST["token"] ?? null) || !hash_equals($_SESSION["token_guardar_personaje"], $_POST["token"])) {
    http_response_code(403);
    die("La solicitud de guardado no es válida.");
}
if (!isset($_SESSION["personaje_en_creacion"])) {
    http_response_code(409);
    die("No hay ningún personaje en creación.");
}

require_once __DIR__ . "/resolucion-personaje.php";
require_once __DIR__ . "/modelo-persistencia.php";
require_once __DIR__ . "/persistencia-personaje.php";

try {
    $personaje = $_SESSION["personaje_en_creacion"];
    normalizarPersonajeEnCreacion($personaje);
    $resultado = resolverPersonaje($personaje);
    if ($resultado["elecciones"]) {
        $_SESSION["error_guardar_personaje"] = "El personaje no puede guardarse todavía. Revisa las decisiones pendientes.";
        header("Location: ../Pagina/crear-subclase.php");
        exit;
    }
    $personaje["resolucion"] = $resultado["resolucion"];
    $modelo = modeloParaPersistir($personaje);
    require_once __DIR__ . "/conexion.php";
    guardarPersonaje($conexion, (int)$_SESSION["usuario_id"], $modelo);
    unset($_SESSION["personaje_en_creacion"], $_SESSION["token_guardar_personaje"]);
    $_SESSION["mensaje_personaje_guardado"] = "Personaje guardado correctamente.";
    header("Location: ../Pagina/index.php");
    exit;
} catch (InvalidArgumentException $error) {
    error_log("Datos de personaje no válidos: " . $error->getMessage());
    $_SESSION["error_guardar_personaje"] = "No se pudo guardar el personaje: " . $error->getMessage();
} catch (RuntimeException $error) {
    error_log("Error al guardar el personaje: " . $error->getMessage());
    $_SESSION["error_guardar_personaje"] = "No se pudo guardar el personaje. Inténtalo de nuevo.";
} catch (JsonException $error) {
    error_log("Error al codificar los datos del personaje: " . $error->getMessage());
    $_SESSION["error_guardar_personaje"] = "No se pudo guardar el personaje por un error en sus datos.";
}
header("Location: ../Pagina/crear-subclase.php");
exit;
