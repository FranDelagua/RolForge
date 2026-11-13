<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.html");
    exit;
}


/*
 * Comprobar que existe un personaje en creación
 */

if (!isset($_SESSION["personaje_en_creacion"])) {
    header("Location: crear-personaje.php");
    exit;
}


$personaje = $_SESSION["personaje_en_creacion"];

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Crear personaje - RolForge</title>

    <link
        rel="stylesheet"
        href="../Api/global.css"
    >

</head>


<body>

<main class="crear-personaje">

    <header class="cabecera">

        <div class="logo">
            ⚔ ROLFORGE ⚔
        </div>

        <div class="usuario">

            👤 <?= htmlspecialchars($_SESSION["usuario"]) ?>

            <a href="crear-personaje.php">
                Volver
            </a>

        </div>

    </header>


    <section class="formulario-personaje">

        <h1>Crear personaje</h1>

        <p>
            Datos guardados correctamente
        </p>


        <section class="seccion-creacion">

            <h2>Resumen</h2>

            <p>
                Nombre:
                <strong>
                    <?= htmlspecialchars($personaje["nombre"]) ?>
                </strong>
            </p>

            <p>
                Nivel:
                <strong>
                    <?= $personaje["nivel"] ?>
                </strong>
            </p>

            <p>
                Raza:
                <strong>
                    <?= htmlspecialchars($personaje["raza"]) ?>
                </strong>
            </p>

            <p>
                Clase:
                <strong>
                    <?= htmlspecialchars($personaje["clase"]) ?>
                </strong>
            </p>

            <p>
                Método:
                <strong>
                    <?= htmlspecialchars($personaje["metodo_atributos"]) ?>
                </strong>
            </p>

            <h2>Atributos</h2>

            <?php foreach ($personaje["atributos"] as $nombre => $valor): ?>

                <p>
                    <?= ucfirst(htmlspecialchars($nombre)) ?>:
                    <strong>
                        <?= $valor ?>
                    </strong>
                </p>

            <?php endforeach; ?>

        </section>


        <section class="acciones-creacion">

            <button
                type="button"
                onclick="history.back()"
            >
                ← Volver
            </button>

        </section>

    </section>

</main>

</body>

</html>