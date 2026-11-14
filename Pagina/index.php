<?php

require_once __DIR__ . "/../Api/autenticacion.php";
exigirUsuario();

$usuario_id = $_SESSION["usuario_id"];

require_once "../Api/conexion.php";

// Obtener los personajes del usuario
$consulta = $conexion->prepare(
    "SELECT id, name, level
     FROM characters
     WHERE user_id = ?
     ORDER BY updated_at DESC, id DESC"
);

$consulta->execute([$usuario_id]);

$personajes = $consulta->fetchAll(PDO::FETCH_ASSOC);
$mensajeGuardado = $_SESSION["mensaje_personaje_guardado"] ?? "";
unset($_SESSION["mensaje_personaje_guardado"]);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>RolForge</title>

    <link rel="stylesheet" href="../Api/global.css">

</head>

<body>

    <main class="inicio">

        <!-- Barra superior -->
        <header class="barra-superior">

            <nav class="navegacion-principal">

                <a href="#" class="enlace-menu">
                    Personajes públicos
                </a>

                <a href="#" class="enlace-menu">
                    Importar
                </a>

            </nav>


            <div class="acciones-cuenta">

                <div class="perfil">
                    <script>
                        document.addEventListener("click", function (event) {

                            const perfil = document.querySelector(".perfil");
                            const menu = document.getElementById("menu-perfil");

                            // Si haces clic fuera del perfil completo, cerrar el menú
                            if (!perfil.contains(event.target)) {
                                menu.classList.remove("mostrar");
                            }

                        });
                    </script>
                    <!-- Foto de perfil -->
                    <button type="button" class="boton-perfil"
                        onclick="document.getElementById('menu-perfil').classList.toggle('mostrar')">
                        <img src="<?php echo !empty($_SESSION["foto_perfil"])
                            ? htmlspecialchars($_SESSION["foto_perfil"])
                            : "img/default.png"; ?>" alt="Foto de perfil" class="foto-perfil">

                    </button>


                    <!-- Recuadro del perfil -->
                    <div id="menu-perfil" class="menu-perfil">

                        <!-- Información del usuario -->
                        <div class="cabecera-perfil">



                            <div class="datos-perfil">

                                <strong>
                                    <?php echo htmlspecialchars($_SESSION["usuario"]); ?>
                                </strong>

                            </div>

                        </div>


                        <!-- Opciones -->
                        <div class="opciones-perfil">

                            <a href="editar-perfil.php">
                                ✏️ Editar perfil
                            </a>

                            <a href="../Api/logout.php">
                                🚪 Cerrar sesión
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </header>


        <!-- Biblioteca de personajes -->
        <section class="biblioteca-personajes">

            <div class="cabecera-personajes">

                <h1>Mis personajes</h1>

                <button type="button" class="boton-crear" onclick="window.location.href='crear-personaje.php'">
                    + Crear personaje
                </button>

            </div>


            <div class="rejilla-personajes">

                <?php if ($mensajeGuardado !== ""): ?>
                    <p role="status"><?= htmlspecialchars($mensajeGuardado) ?></p>
                <?php endif; ?>
                <?php if (empty($personajes)): ?>

                    <p class="sin-personajes">
                        No tienes personajes todavía.
                    </p>

                <?php else: ?>

                    <?php foreach ($personajes as $personaje): ?>

                        <article class="tarjeta-personaje">

                            <div class="ilustracion-personaje">

                                <span>
                                    <?php
                                    echo htmlspecialchars(
                                        "⚔️"
                                    );
                                    ?>
                                </span>

                            </div>


                            <div class="informacion-personaje">

                                <h2>
                                    <?php
                                    echo htmlspecialchars(
                                        $personaje["name"]
                                    );
                                    ?>
                                </h2>


                                <span class="nivel-personaje">

                                    Nivel
                                    <?php
                                    echo htmlspecialchars(
                                        $personaje["level"]
                                    );
                                    ?>

                                </span>


                                <div class="acciones-personaje">

                                    <a href="personaje.php?id=<?= (int)$personaje["id"] ?>">Ver personaje</a>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </section>

    </main>

</body>

</html>