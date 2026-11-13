<?php

require_once "conexion.php";


// Comprobar que el formulario se ha enviado mediante POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Método no permitido");
}


// Recoger los datos del formulario
$usuario = trim($_POST["usuario"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$confirmar = $_POST["confirmar"] ?? "";


// Comprobar que no haya campos vacíos
if (
    empty($usuario) ||
    empty($email) ||
    empty($password) ||
    empty($confirmar)
) {
    die("Todos los campos son obligatorios");
}


// Comprobar que las contraseñas coinciden
if ($password !== $confirmar) {
    die("Las contraseñas no coinciden");
}


// Comprobar si el usuario ya existe
$consulta = $conexion->prepare(
    "SELECT id FROM usuarios WHERE usuario = ?"
);

$consulta->execute([$usuario]);

if ($consulta->fetch()) {
    die("Ese nombre de usuario ya está registrado");
}


// Comprobar si el correo ya existe
$consulta = $conexion->prepare(
    "SELECT id FROM usuarios WHERE email = ?"
);

$consulta->execute([$email]);

if ($consulta->fetch()) {
    die("Ese correo ya está registrado");
}


// Encriptar la contraseña
$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);


// Guardar el usuario en la base de datos
$consulta = $conexion->prepare(
    "INSERT INTO usuarios (usuario, email, password)
     VALUES (?, ?, ?)"
);

$consulta->execute([
    $usuario,
    $email,
    $passwordHash
]);


// Registro correcto
echo "Usuario registrado correctamente";