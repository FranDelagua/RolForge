<?php

require_once __DIR__ . "/autenticacion.php";
iniciarSesionSegura();

// Vaciar todas las variables de sesión
$_SESSION = [];

// Destruir la sesión
session_destroy();

// Volver al login
header("Location: ../Pagina/login.html");
exit;
?>