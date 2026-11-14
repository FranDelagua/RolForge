<?php

if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Esta migración solo puede ejecutarse desde la consola.\n");
}

require_once __DIR__ . "/conexion.php";

try {
    if ((int)$conexion->query("SELECT COUNT(*) FROM users")->fetchColumn() !== 0) {
        throw new RuntimeException("La tabla users no está vacía. Resuelve los posibles conflictos antes de migrar.");
    }
    if ((int)$conexion->query("SELECT COUNT(*) FROM personajes")->fetchColumn() !== 0) {
        throw new RuntimeException("Hay personajes antiguos: su migración aún no está implementada.");
    }
    $columna = $conexion->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $columna->execute(["users", "foto_perfil"]);
    if ((int)$columna->fetchColumn() === 0) {
        // MySQL confirma los cambios de esquema independientemente de la transacción de datos.
        $conexion->exec("ALTER TABLE users ADD COLUMN foto_perfil VARCHAR(255) NULL");
    }

    $conexion->beginTransaction();
    $conexion->exec(
        "INSERT INTO users (id, username, email, password_hash, foto_perfil, created_at)
         SELECT id, usuario, email, password, foto_perfil, fecha_registro FROM usuarios"
    );
    $total = $conexion->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $conexion->commit();
    echo "Cuentas migradas: " . $total . ". Las tablas antiguas no se han modificado.\n";
} catch (PDOException $error) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    fwrite(STDERR, "No se pudo migrar las cuentas; revisa los registros del servidor.\n");
    error_log($error->getMessage());
    exit(1);
} catch (RuntimeException $error) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
