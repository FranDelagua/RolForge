<?php

require_once __DIR__ . "/autenticacion.php";
iniciarSesionSegura();
require_once __DIR__ . "/conexion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die("Método no permitido.");
}

$usuario = $_POST["usuario"] ?? "";
$email = $_POST["email"] ?? "";
$password = $_POST["password"] ?? "";
$confirmar = $_POST["confirmar"] ?? "";
if (!is_string($usuario) || !is_string($email) || !is_string($password) || !is_string($confirmar)) {
    http_response_code(400);
    die("Los datos del registro no son válidos.");
}
$usuario = trim($usuario);
$email = trim($email);
if (strlen($usuario) < 3 || strlen($usuario) > 50 || preg_match('/[\x00-\x1F\x7F]/', $usuario)) {
    http_response_code(400);
    die("El nombre de usuario debe tener entre 3 y 50 bytes y no contener caracteres de control.");
}
if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die("Introduce un correo electrónico válido.");
}
if ($password === "" || strlen($password) > 72 || $password !== $confirmar) {
    http_response_code(400);
    die("Las contraseñas deben coincidir y contener entre 1 y 72 bytes.");
}

$comprobar = $conexion->prepare("SELECT id FROM users WHERE username = ?");
$comprobar->execute([$usuario]);
if ($comprobar->fetch()) {
    http_response_code(409);
    die("Ese nombre de usuario ya está registrado.");
}
$comprobar = $conexion->prepare("SELECT id FROM users WHERE email = ?");
$comprobar->execute([$email]);
if ($comprobar->fetch()) {
    http_response_code(409);
    die("Ese correo ya está registrado.");
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    http_response_code(500);
    die("No se pudo proteger la contraseña.");
}

try {
    $consulta = $conexion->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $consulta->execute([$usuario, $email, $passwordHash]);
} catch (PDOException $error) {
    if ($error->getCode() === "23000") {
        http_response_code(409);
        die("El nombre de usuario o el correo ya están registrados.");
    }
    error_log("Error al registrar un usuario: " . $error->getMessage());
    http_response_code(500);
    die("No se pudo completar el registro.");
}

autenticarUsuario([
    "id" => $conexion->lastInsertId(),
    "username" => $usuario,
    "email" => $email,
    "foto_perfil" => null
]);
header("Location: ../Pagina/index.php");
exit;
