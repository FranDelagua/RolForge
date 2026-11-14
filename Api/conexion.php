<?php

$host = getenv("ROLFORGE_DB_HOST") ?: "localhost";
$db = getenv("ROLFORGE_DB_NAME") ?: "rolforge";
$user = getenv("ROLFORGE_DB_USER") ?: "root";
$password = getenv("ROLFORGE_DB_PASSWORD");
if ($password === false) {
    $password = "";
}

try {

    $conexion = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $password
    );

    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {

    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    http_response_code(500);
    if (PHP_SAPI === "cli") {
        fwrite(STDERR, "No se pudo conectar con la base de datos.\n");
        exit(1);
    }
    die("No se pudo conectar con la base de datos.");

}