<?php

function iniciarSesionSegura() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        "httponly" => true,
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "samesite" => "Lax",
        "path" => "/"
    ]);
    session_start();
}

function autenticarUsuario($usuario) {
    session_regenerate_id(true);
    $_SESSION = [
        "usuario_id" => (int)$usuario["id"],
        "usuario" => $usuario["username"],
        "email" => $usuario["email"],
        "foto_perfil" => $usuario["foto_perfil"],
        "auth_schema" => "users"
    ];
}

function exigirUsuario() {
    iniciarSesionSegura();
    if (empty($_SESSION["usuario_id"]) || ($_SESSION["auth_schema"] ?? "") !== "users") {
        header("Location: login.html");
        exit;
    }
}
