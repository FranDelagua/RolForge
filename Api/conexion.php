<?php

$host = "localhost";
$db = "rolforge";
$user = "root";
$password = "";

try {

    $conexion = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $password
    );

    $conexion->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    echo "";

} catch (PDOException $e) {

    echo "Error de conexión: " . $e->getMessage();

}