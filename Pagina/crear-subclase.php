<?php

require_once __DIR__ . "/../Api/autenticacion.php";
exigirUsuario();
require_once __DIR__ . "/../Api/elecciones-raza.php";

/*
 * Comprobar que existe un personaje en creación
 */

prepararPersonajeEnCreacion();


$personaje = $_SESSION["personaje_en_creacion"];
exigirEleccionesRazaConfirmadas($personaje);

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
            Datos guardados temporalmente. Las decisiones adicionales todavía no se han resuelto y el personaje no está finalizado.
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
                    <?= htmlspecialchars(claveReferencia5e($personaje["raza"], "race")) ?>
                </strong>
            </p>

            <p>
                Clase:
                <strong>
                    <?= htmlspecialchars(claveReferencia5e($personaje["clase"], "class")) ?>
                </strong>
            </p>

            <?php if (isset($personaje["background"])): ?>
                <p>
                    Background:
                    <strong>
                        <?= htmlspecialchars(claveReferencia5e($personaje["background"], "background")) ?>
                    </strong>
                </p>
            <?php endif; ?>

            <?php if (isset($personaje["subclase"])): ?>
                <p>
                    Subclase:
                    <strong>
                        <?= htmlspecialchars(claveReferencia5e($personaje["subclase"], "subclass")) ?>
                    </strong>
                </p>
            <?php endif; ?>

            <p>
                Método:
                <strong>
                    <?= htmlspecialchars($personaje["metodo_atributos"]) ?>
                </strong>
            </p>

            <h2>Atributos</h2>

            <?php $clavesAtributos = [
                "fuerza" => "str", "destreza" => "dex", "constitucion" => "con",
                "inteligencia" => "int", "sabiduria" => "wis", "carisma" => "cha"
            ]; ?>
            <?php $nombresAtributos = [
                "str" => "Fuerza", "dex" => "Destreza", "con" => "Constitución",
                "int" => "Inteligencia", "wis" => "Sabiduría", "cha" => "Carisma"
            ]; ?>
            <?php $bonusDotes = [];
            foreach ($personaje["raza"]["elecciones"]["dotes"] ?? [] as $dote) {
                foreach ($dote["elecciones"]["atributos"]["bonificadores"] ?? [] as $atributo => $bonus) {
                    $bonusDotes[$atributo] = ($bonusDotes[$atributo] ?? 0) + $bonus;
                }
            } ?>
            <?php foreach ($personaje["atributos"] as $nombre => $valor): ?>

                <p>
                    <?= ucfirst(htmlspecialchars($nombre)) ?>:
                    <strong>
                        <?= (int)$valor + (int)($personaje["raza"]["bonificadores"][$clavesAtributos[$nombre] ?? ""] ?? 0) + (int)($bonusDotes[$clavesAtributos[$nombre] ?? ""] ?? 0) ?>
                    </strong>
                </p>

            <?php endforeach; ?>

            <?php if (!empty($personaje["raza"]["elecciones"]["dotes"])): ?>
                <h2>Dotes de raza (provisionales)</h2>
                <?php foreach ($personaje["raza"]["elecciones"]["dotes"] as $dote): ?>
                    <p><?= htmlspecialchars(claveReferencia5e($dote, "feat")) ?></p>
                    <?php foreach ($dote["elecciones"]["atributos"]["bonificadores"] ?? [] as $atributo => $bonus): ?>
                        <p><?= htmlspecialchars($nombresAtributos[$atributo] ?? $atributo) ?> +<?= (int)$bonus ?></p>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>

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