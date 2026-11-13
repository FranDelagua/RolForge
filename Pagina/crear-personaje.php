<?php

session_start();
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $raza = $_POST["raza"] ?? "";
    $clase = $_POST["clase"] ?? "";
    $metodo = $_POST["metodo"] ?? "";

    if ($nombre === "") {
        die("Debes introducir un nombre para el personaje.");
    }

    if ($raza === "" || $clase === "") {
        die("Debes seleccionar una raza y una clase.");
    }

    if ($metodo !== "puntos" && $metodo !== "personalizado") {
        die("Método de atributos no válido.");
    }

    /*
     * Atributos enviados desde JavaScript
     */
    $atributos = json_decode(
        $_POST["atributos"] ?? "{}",
        true
    );

    if (!is_array($atributos)) {
        die("Los atributos no son válidos.");
    }

    /*
     * Guardamos temporalmente el personaje
     * mientras seguimos con su creación.
     */
    $_SESSION["personaje_en_creacion"] = [
        "nombre" => $nombre,
        "nivel" => 1,
        "raza" => $raza,
        "clase" => $clase,
        "metodo_atributos" => $metodo,
        "atributos" => $atributos
    ];

    /*
     * Pasamos al siguiente paso.
     */
    header("Location: crear-subclase.php");
    exit;
}
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.html");
    exit;
}

$nivel = 1;


/*
 * ==========================================
 * CARGAR CLASES DE 5ETOOLS
 * ==========================================
 */

$rutaClases = "../data/5etools/class/index.json";

if (!file_exists($rutaClases)) {
    die("No se ha encontrado la carpeta de datos de 5etools.");
}

$indiceClases = json_decode(
    file_get_contents($rutaClases),
    true
);

if (!is_array($indiceClases)) {
    die("No se pudo leer el índice de clases de 5etools.");
}


$clases = [];


foreach ($indiceClases as $archivo) {

    $rutaClase = "../data/5etools/class/" . $archivo;

    if (!file_exists($rutaClase)) {
        continue;
    }

    $datosClase = json_decode(
        file_get_contents($rutaClase),
        true
    );

    if (!isset($datosClase["class"]) || !is_array($datosClase["class"])) {
        continue;
    }


    foreach ($datosClase["class"] as $clase) {

        if (!isset($clase["name"])) {
            continue;
        }

        /*
         * Guardamos la clase completa.
         * La necesitaremos después para detectar
         * características y subclases.
         */

        $clases[] = $clase;
    }
}


/*
 * ==========================================
 * CARGAR RAZAS DE 5ETOOLS
 * ==========================================
 */

$rutaRazas = "../data/5etools/races.json";

if (!file_exists($rutaRazas)) {
    die("No se ha encontrado el archivo de razas de 5etools.");
}

$datosRazas = json_decode(
    file_get_contents($rutaRazas),
    true
);

if (!isset($datosRazas["race"]) || !is_array($datosRazas["race"])) {
    die("No se pudieron leer las razas de 5etools.");
}

$razas = $datosRazas["race"];


/*
 * ==========================================
 * ATRIBUTOS
 * ==========================================
 */

$atributos = [
    "fuerza" => 10,
    "destreza" => 10,
    "constitucion" => 10,
    "inteligencia" => 10,
    "sabiduria" => 10,
    "carisma" => 10
];

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Crear personaje - RolForge</title>

    <link rel="stylesheet" href="../Api/global.css">

</head>


<body>

    <main class="crear-personaje">

        <header class="cabecera">

            <div class="logo">
                ⚔ ROLFORGE ⚔
            </div>

            <div class="usuario">

                👤 <?= htmlspecialchars($_SESSION["usuario"]) ?>

                <a href="../Pagina/index.php">
                    Volver
                </a>

            </div>

        </header>


        <section class="formulario-personaje">

            <h1>Crear personaje</h1>

            <p>
                Personaje de nivel <?= $nivel ?>
            </p>


            <!-- ============================= -->
            <!-- DATOS BÁSICOS                  -->
            <!-- ============================= -->

            <section class="seccion-creacion">

                <h2>Datos del personaje</h2>


                <div class="campo">

                    <label for="nombre">
                        Nombre
                    </label>

                    <input type="text" id="nombre" name="nombre" maxlength="100" required>

                </div>


                <!-- RAZA -->

                <div class="campo">

                    <label for="raza">
                        Raza
                    </label>

                    <select id="raza" name="raza">

                        <?php foreach ($razas as $raza): ?>

                            <?php

                            $nombreRaza = $raza["name"] ?? "";
                            $sourceRaza = $raza["source"] ?? "";

                            ?>

                            <option value="<?= htmlspecialchars($nombreRaza . "|" . $sourceRaza) ?>">

                                <?= htmlspecialchars($nombreRaza) ?>

                                <?php if ($sourceRaza !== ""): ?>

                                    — <?= htmlspecialchars($sourceRaza) ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CLASE -->

                <div class="campo">

                    <label for="clase">
                        Clase
                    </label>

                    <select id="clase" name="clase">

                        <?php foreach ($clases as $indice => $clase): ?>

                            <?php

                            $nombreClase = $clase["name"] ?? "";
                            $sourceClase = $clase["source"] ?? "";
                            $edition = $clase["edition"] ?? "";

                            ?>

                            <option value="<?= $indice ?>">

                                <?= htmlspecialchars($nombreClase) ?>

                                <?php if ($sourceClase !== ""): ?>

                                    — <?= htmlspecialchars($sourceClase) ?>

                                <?php endif; ?>

                                <?php if ($edition !== ""): ?>

                                    [<?= htmlspecialchars($edition) ?>]

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </section>


            <!-- ============================= -->
            <!-- ATRIBUTOS                     -->
            <!-- ============================= -->

            <section class="seccion-creacion">

                <h2>Atributos</h2>


                <div class="metodo-atributos">

                    <label>

                        <input type="radio" name="metodo" value="puntos" checked>

                        Sistema de puntos

                    </label>


                    <label>

                        <input type="radio" name="metodo" value="personalizado">

                        Personalizado

                    </label>

                </div>


                <!-- SISTEMA DE PUNTOS -->

                <div id="atributos-puntos">

                    <p>
                        Puntos disponibles:
                        <strong id="puntos-restantes">17</strong>
                    </p>


                    <?php foreach ($atributos as $nombre => $valor): ?>

                        <div class="atributo">

                            <span>
                                <?= ucfirst($nombre) ?>
                            </span>


                            <button type="button" onclick="cambiarAtributo(
                                '<?= $nombre ?>',
                                -1
                            )">
                                -
                            </button>


                            <span id="valor-<?= $nombre ?>">
                                <?= $valor ?>
                            </span>


                            <button type="button" onclick="cambiarAtributo(
                                '<?= $nombre ?>',
                                1
                            )">
                                +
                            </button>

                        </div>

                    <?php endforeach; ?>

                </div>


                <!-- PERSONALIZADO -->

                <div id="atributos-personalizado" style="display:none;">

                    <p>
                        Puedes establecer cada atributo
                        libremente entre 1 y 20.
                    </p>


                    <?php foreach ($atributos as $nombre => $valor): ?>

                        <div class="atributo">

                            <label for="personalizado-<?= $nombre ?>">
                                <?= ucfirst($nombre) ?>
                            </label>
                            <input type="number" id="personalizado-<?= $nombre ?>" min="1" max="20" value="<?= $valor ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>


            <!-- ============================= -->
            <!-- SIGUIENTE                     -->
            <!-- ============================= -->

            <section class="acciones-creacion">

                <form method="POST">

                    <input type="hidden" name="nombre" id="nombre-hidden">
                    <input type="hidden" name="raza" id="raza-hidden">
                    <input type="hidden" name="clase" id="clase-hidden">
                    <input type="hidden" name="metodo" id="metodo-hidden">
                    <input type="hidden" name="atributos" id="atributos-hidden">

                    <section class="acciones-creacion">

                        <button type="submit" onclick="prepararEnvio()">
                            Siguiente →
                        </button>

                    </section>

                </form>

            </section>

        </section>

    </main>


    <script>

        /*
         * Los datos completos de las clases de 5etools
         * llegan desde PHP a JavaScript.
         */

        const clases = <?= json_encode(
            $clases,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>;


        /*
         * ==========================================
         * ATRIBUTOS
         * ==========================================
         */

        const atributos = {

            fuerza: 10,
            destreza: 10,
            constitucion: 10,
            inteligencia: 10,
            sabiduria: 10,
            carisma: 10

        };

        let puntosDisponibles = 17;


        /*
         * Cambiar atributo mediante + / -
         */

        function cambiarAtributo(nombre, cantidad) {

            const actual = atributos[nombre];

            const nuevo = actual + cantidad;


            if (nuevo < 8) {
                return;
            }


            if (nuevo > 15) {
                return;
            }


            let coste;


            if (cantidad > 0) {

                coste = nuevo >= 14 ? 2 : 1;

                if (puntosDisponibles < coste) {
                    return;
                }

                puntosDisponibles -= coste;

            } else {

                coste = actual >= 14 ? 2 : 1;

                puntosDisponibles += coste;

            }


            atributos[nombre] = nuevo;


            document.getElementById(
                "valor-" + nombre
            ).textContent = nuevo;


            document.getElementById(
                "puntos-restantes"
            ).textContent = puntosDisponibles;

        }


        /*
         * ==========================================
         * CAMBIAR MÉTODO DE ATRIBUTOS
         * ==========================================
         */

        document
            .querySelectorAll('input[name="metodo"]')
            .forEach(function (radio) {

                radio.addEventListener(
                    "change",
                    function () {

                        const puntos =
                            document.getElementById(
                                "atributos-puntos"
                            );

                        const personalizado =
                            document.getElementById(
                                "atributos-personalizado"
                            );


                        if (this.value === "personalizado") {

                            puntos.style.display = "none";

                            personalizado.style.display = "block";

                        } else {

                            puntos.style.display = "block";

                            personalizado.style.display = "none";

                        }

                    }
                );

            });


        /*
         * ==========================================
         * SIGUIENTE PASO
         * ==========================================
         */
        function prepararEnvio() {

            const nombre =
                document
                    .getElementById("nombre")
                    .value
                    .trim();

            if (nombre === "") {

                alert(
                    "Debes introducir un nombre para el personaje."
                );

                event.preventDefault();

                return;
            }


            /*
             * Raza
             */
            const raza =
                document.getElementById("raza").value;


            /*
             * Clase
             */
            const indiceClase =
                document.getElementById("clase").value;


            /*
             * Método de atributos
             */
            const metodo =
                document.querySelector(
                    'input[name="metodo"]:checked'
                ).value;


            /*
             * Atributos
             */
            let atributosFinales;


            if (metodo === "puntos") {

                atributosFinales = {
                    ...atributos
                };

            } else {

                atributosFinales = {

                    fuerza:
                        Number(
                            document.getElementById(
                                "personalizado-fuerza"
                            ).value
                        ),

                    destreza:
                        Number(
                            document.getElementById(
                                "personalizado-destreza"
                            ).value
                        ),

                    constitucion:
                        Number(
                            document.getElementById(
                                "personalizado-constitucion"
                            ).value
                        ),

                    inteligencia:
                        Number(
                            document.getElementById(
                                "personalizado-inteligencia"
                            ).value
                        ),

                    sabiduria:
                        Number(
                            document.getElementById(
                                "personalizado-sabiduria"
                            ).value
                        ),

                    carisma:
                        Number(
                            document.getElementById(
                                "personalizado-carisma"
                            ).value
                        )
                };
            }


            /*
             * Guardamos los datos en los campos ocultos
             */

            document.getElementById("nombre-hidden").value =
                nombre;

            document.getElementById("raza-hidden").value =
                raza;

            document.getElementById("clase-hidden").value =
                indiceClase;

            document.getElementById("metodo-hidden").value =
                metodo;

            document.getElementById("atributos-hidden").value =
                JSON.stringify(atributosFinales);
        }

    </script>

</body>

</html>