<?php

require_once __DIR__ . "/../Api/autenticacion.php";
exigirUsuario();
require_once __DIR__ . "/../Api/razas-5etools.php";
require_once __DIR__ . "/../Api/modelo-personaje.php";

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

    try {
        $raza = referencia5e("race", $raza);
        $clase = referencia5e("class", $clase);
    } catch (InvalidArgumentException $error) {
        http_response_code(400);
        die("La raza o la clase seleccionada no es válida.");
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
    $personaje = [
        "nombre" => $nombre,
        "nivel" => 1,
        "raza" => $raza,
        "clase" => $clase,
        "metodo_atributos" => $metodo,
        "atributos" => $atributos
    ];
    if (isset($_SESSION["personaje_en_creacion"])) {
        prepararPersonajeEnCreacion();
        $anterior = $_SESSION["personaje_en_creacion"];
        if (
            isset($anterior["raza"], $anterior["clase"]) &&
            claveReferencia5e($anterior["raza"], "race") === claveReferencia5e($raza, "race") &&
            claveReferencia5e($anterior["clase"], "class") === claveReferencia5e($clase, "class")
        ) {
            $personaje = array_replace($anterior, $personaje, ["raza" => $anterior["raza"], "clase" => $anterior["clase"]]);
        }
    }
    $_SESSION["personaje_en_creacion"] = $personaje;

    /*
     * Pasamos al siguiente paso.
     */
    header("Location: elecciones-raza.php");
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
$informacionClases = [];

$rutaIndiceFluffClases = "../data/5etools/class/fluff-index.json";
$fluffPorClase = [];

if (file_exists($rutaIndiceFluffClases)) {
    $indiceFluffClases = json_decode(
        file_get_contents($rutaIndiceFluffClases),
        true
    );

    if (is_array($indiceFluffClases)) {
        foreach ($indiceFluffClases as $archivoFluff) {
            if (!is_string($archivoFluff)) {
                continue;
            }

            $rutaFluffClase = "../data/5etools/class/" . $archivoFluff;

            if (!file_exists($rutaFluffClase)) {
                continue;
            }

            $datosFluffClase = json_decode(
                file_get_contents($rutaFluffClase),
                true
            );

            if (!isset($datosFluffClase["classFluff"]) || !is_array($datosFluffClase["classFluff"])) {
                continue;
            }

            foreach ($datosFluffClase["classFluff"] as $fluffClase) {
                $claveFluffClase = ($fluffClase["name"] ?? "") . "|" . ($fluffClase["source"] ?? "");
                $fluffPorClase[$claveFluffClase] = $fluffClase;
            }
        }
    }
}


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

        $nombreClase = $clase["name"];
        $sourceClase = $clase["source"] ?? "";
        $claveFluffClase = $nombreClase . "|" . $sourceClase;
        $rasgosNivelUno = [];

        foreach ($clase["classFeatures"] ?? [] as $referenciaRasgo) {
            if (is_array($referenciaRasgo)) {
                $referenciaRasgo = $referenciaRasgo["classFeature"] ?? "";
            }

            if (!is_string($referenciaRasgo)) {
                continue;
            }

            $partesReferencia = explode("|", $referenciaRasgo);

            if (count($partesReferencia) < 4 || (int)$partesReferencia[3] !== 1) {
                continue;
            }

            $nombreRasgo = $partesReferencia[0];

            foreach ($datosClase["classFeature"] ?? [] as $rasgoClase) {
                if (
                    ($rasgoClase["name"] ?? "") === $nombreRasgo &&
                    ($rasgoClase["className"] ?? "") === $nombreClase &&
                    ($rasgoClase["classSource"] ?? "") === $sourceClase &&
                    (int)($rasgoClase["level"] ?? 0) === 1
                ) {
                    $rasgosNivelUno[] = [
                        "name" => $rasgoClase["name"],
                        "entries" => $rasgoClase["entries"] ?? []
                    ];
                    break;
                }
            }
        }

        $informacionClases[] = [
            "name" => $nombreClase,
            "source" => $sourceClase,
            "edition" => $clase["edition"] ?? "",
            "description" => $fluffPorClase[$claveFluffClase]["entries"] ?? [],
            "hd" => $clase["hd"] ?? null,
            "startingProficiencies" => $clase["startingProficiencies"] ?? [],
            "features" => $rasgosNivelUno
        ];

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

$rutaFluffRazas = "../data/5etools/fluff-races.json";

if (!file_exists($rutaFluffRazas)) {
    die("No se ha encontrado el archivo de información de razas de 5etools.");
}

$datosFluffRazas = json_decode(
    file_get_contents($rutaFluffRazas),
    true
);

if (!isset($datosFluffRazas["raceFluff"]) || !is_array($datosFluffRazas["raceFluff"])) {
    die("No se pudo leer la información de razas de 5etools.");
}

try {
    $razasResueltas = resolverCopiasRazas($razas);
    $fluffPorRaza = resolverCopiasRazas($datosFluffRazas["raceFluff"]);
} catch (RuntimeException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    die("No se pudieron resolver los datos heredados de las razas de 5etools.");
}

$directorioImagenesRazas = realpath(__DIR__ . "/../data/5etools/img");
$informacionRazas = [];

foreach ($razas as $raza) {
    $nombreRaza = $raza["name"] ?? "";
    $sourceRaza = $raza["source"] ?? "";
    $claveRaza = $nombreRaza . "|" . $sourceRaza;
    $raza = $razasResueltas[$claveRaza];
    $fluffRaza = $fluffPorRaza[$claveRaza] ?? [];
    $rutaImagen = null;

    if ($directorioImagenesRazas !== false && isset($fluffRaza["images"]) && is_array($fluffRaza["images"])) {
        foreach ($fluffRaza["images"] as $imagen) {
            $rutaRelativa = $imagen["href"]["path"] ?? "";

            if (($imagen["href"]["type"] ?? "") !== "internal" || $rutaRelativa === "") {
                continue;
            }

            $rutaFisica = realpath(
                $directorioImagenesRazas . DIRECTORY_SEPARATOR .
                str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $rutaRelativa)
            );

            $prefijoImagenes = $directorioImagenesRazas . DIRECTORY_SEPARATOR;

            if (
                $rutaFisica !== false &&
                is_file($rutaFisica) &&
                strpos(strtolower($rutaFisica), strtolower($prefijoImagenes)) === 0
            ) {
                $rutaImagen = "../data/5etools/img/" . str_replace("\\", "/", $rutaRelativa);
                break;
            }
        }
    }

    $informacionRazas[$claveRaza] = [
        "name" => $nombreRaza,
        "source" => $sourceRaza,
        "entries" => $raza["entries"] ?? [],
        "ability" => $raza["ability"] ?? [],
        "size" => $raza["size"] ?? [],
        "sizeEntry" => $raza["sizeEntry"] ?? null,
        "lore" => $fluffRaza["entries"] ?? [],
        "image" => $rutaImagen
    ];
}


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

                    <select id="raza" name="raza" required>

                        <option value="" selected disabled>
                            Elegir raza
                        </option>

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

                    <section id="ficha-raza" class="ficha-raza" aria-live="polite">
                        <p class="ficha-raza-vacia">
                            Selecciona una raza para conocerla.
                        </p>

                        <div class="ficha-raza-contenido" hidden>
                            <div class="ficha-raza-imagen-marco">
                                <img class="ficha-raza-imagen" alt="" hidden>
                            </div>

                            <div class="ficha-raza-detalles">
                                <header class="ficha-raza-cabecera">
                                    <h3 class="ficha-raza-nombre"></h3>
                                    <span class="ficha-raza-origen"></span>
                                </header>

                                <section class="ficha-raza-lore" hidden>
                                    <h4>Sobre esta raza</h4>
                                    <p></p>
                                </section>

                                <section class="ficha-raza-rasgos" hidden>
                                    <h4>Rasgos raciales</h4>
                                    <ul></ul>
                                </section>
                            </div>
                        </div>
                    </section>

                </div>


                <!-- CLASE -->

                <div class="campo">

                    <label for="clase">
                        Clase
                    </label>

                    <select id="clase" name="clase" required>

                        <option value="" selected disabled>
                            Elegir clase
                        </option>

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

                    <section id="ficha-clase" class="ficha-clase" aria-live="polite">
                        <p class="ficha-clase-vacia">
                            Selecciona una clase para conocerla.
                        </p>

                        <div class="ficha-clase-contenido" hidden>
                            <header class="ficha-clase-cabecera">
                                <h3 class="ficha-clase-nombre"></h3>
                                <span class="ficha-clase-origen"></span>
                            </header>

                            <section class="ficha-clase-descripcion" hidden>
                                <h4>Sobre esta clase</h4>
                                <p></p>
                            </section>

                            <section class="ficha-clase-informacion" hidden>
                                <h4>Información básica</h4>
                                <ul></ul>
                            </section>

                            <section class="ficha-clase-rasgos" hidden>
                                <h4>Nivel 1</h4>
                                <ul></ul>
                            </section>
                        </div>
                    </section>

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

                        <button type="submit" onclick="prepararEnvio(event)">
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
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;

        const informacionClases = <?= json_encode(
            $informacionClases,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;

        const informacionRazas = <?= json_encode(
            $informacionRazas,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;


        /*
         * ==========================================
         * FICHA DE RAZA
         * ==========================================
         */

        const selectorRaza = document.getElementById("raza");
        const fichaRaza = document.getElementById("ficha-raza");
        const mensajeRazaVacia = fichaRaza.querySelector(".ficha-raza-vacia");
        const contenidoFichaRaza = fichaRaza.querySelector(".ficha-raza-contenido");
        const imagenFichaRaza = fichaRaza.querySelector(".ficha-raza-imagen");
        const loreFichaRaza = fichaRaza.querySelector(".ficha-raza-lore");
        const rasgosFichaRaza = fichaRaza.querySelector(".ficha-raza-rasgos");

        function obtenerTextos(contenido, textos) {

            if (typeof contenido === "string") {
                const texto = contenido
                    .replace(/\{@[a-zA-Z]+\s+([^}]+)\}/g, "$1")
                    .replace(/\{@[^}]+\}/g, "")
                    .replace(/\s+/g, " ")
                    .trim();

                if (texto !== "") {
                    textos.push(texto);
                }

                return;
            }

            if (Array.isArray(contenido)) {
                contenido.forEach(function (elemento) {
                    obtenerTextos(elemento, textos);
                });

                return;
            }

            if (contenido && typeof contenido === "object") {
                if (Array.isArray(contenido.entries)) {
                    obtenerTextos(contenido.entries, textos);
                } else if (Array.isArray(contenido.items)) {
                    obtenerTextos(contenido.items, textos);
                } else if (typeof contenido.text === "string") {
                    obtenerTextos(contenido.text, textos);
                }
            }
        }

        function resumirTextoRaza(texto, limite) {
            if (texto.length <= limite) {
                return texto;
            }

            const corte = texto.lastIndexOf(" ", limite);

            return texto.slice(0, corte > 0 ? corte : limite).trim() + "…";
        }

        function obtenerTextosRasgoRaza(contenido, textos) {
            if (typeof contenido === "string") {
                const texto = contenido.replace(
                    /\{@([a-zA-Z0-9]+)\s+([^}]+)\}/g,
                    function (_, tipo, contenidoEtiqueta) {
                        const partes = contenidoEtiqueta.split("|");
                        return tipo.toLowerCase() === "filter" ? partes[0] : (partes[2] || partes[0]);
                    }
                ).replace(/\{@[^}]+\}/g, "").replace(/\s+/g, " ").trim();
                if (texto) textos.push(texto);
                return;
            }
            if (Array.isArray(contenido)) {
                contenido.forEach(function (elemento) {
                    obtenerTextosRasgoRaza(elemento, textos);
                });
                return;
            }
            if (contenido && typeof contenido === "object") {
                if ((contenido.type === "item" || contenido.type === "itemSpell") && contenido.name) {
                    textos.push(contenido.name);
                }
                if (contenido.type === "table") {
                    obtenerTextosRasgoRaza(contenido.colLabels, textos);
                    obtenerTextosRasgoRaza(contenido.rows, textos);
                } else {
                    obtenerTextosRasgoRaza(contenido.entries, textos);
                    obtenerTextosRasgoRaza(contenido.items, textos);
                    obtenerTextosRasgoRaza(contenido.entry, textos);
                    obtenerTextosRasgoRaza(contenido.text, textos);
                }
            }
        }

        function describirAtributosRaza(variantes) {
            const nombres = {
                str: "Fuerza", dex: "Destreza", con: "Constitución",
                int: "Inteligencia", wis: "Sabiduría", cha: "Carisma"
            };
            if (!Array.isArray(variantes)) return "";
            return variantes.map(function (variante) {
                const partes = Object.keys(nombres).filter(function (clave) {
                    return typeof variante[clave] === "number";
                }).map(function (clave) {
                    const valor = variante[clave];
                    return nombres[clave] + " " + (valor > 0 ? "+" : "") + valor;
                });
                if (variante.choose && Array.isArray(variante.choose.from)) {
                    const eleccion = variante.choose;
                    const opciones = eleccion.from.map(function (clave) {
                        return nombres[clave] || clave;
                    }).join(", ");
                    partes.push("Elegir " + (eleccion.count || 1) + " entre " + opciones +
                        " (+" + (eleccion.amount || 1) + ")");
                }
                return partes.join("; ");
            }).filter(Boolean).join(" / ");
        }

        function actualizarFichaRaza() {

            const claveRaza = selectorRaza.value;
            const informacion = informacionRazas[claveRaza];

            mensajeRazaVacia.hidden = Boolean(informacion);
            contenidoFichaRaza.hidden = !informacion;
            fichaRaza.classList.remove("ficha-raza-con-imagen");
            imagenFichaRaza.hidden = true;
            imagenFichaRaza.removeAttribute("src");
            loreFichaRaza.hidden = true;
            rasgosFichaRaza.hidden = true;

            if (!informacion) {
                return;
            }

            fichaRaza.querySelector(".ficha-raza-nombre").textContent =
                informacion.name;
            fichaRaza.querySelector(".ficha-raza-origen").textContent =
                informacion.source;

            const textosLore = [];
            obtenerTextos(informacion.lore, textosLore);

            if (textosLore.length > 0) {
                loreFichaRaza.querySelector("p").textContent =
                    resumirTextoRaza(textosLore[0], 420);
                loreFichaRaza.hidden = false;
            }

            const rasgos = [];
            const entradas = Array.isArray(informacion.entries) ? informacion.entries : [];
            const nombresRasgos = entradas.map(function (entrada) {
                return entrada && typeof entrada.name === "string" ? entrada.name.toLowerCase() : "";
            });
            const atributosRaza = describirAtributosRaza(informacion.ability);
            if (atributosRaza && !nombresRasgos.includes("ability score increase") &&
                !nombresRasgos.includes("ability score improvement")) {
                rasgos.push({ nombre: "Atributos", descripcion: atributosRaza });
            }
            if (!nombresRasgos.includes("size")) {
                const textosTamano = [];
                obtenerTextosRasgoRaza(informacion.sizeEntry && informacion.sizeEntry.entries, textosTamano);
                if (textosTamano.length === 0 && Array.isArray(informacion.size)) {
                    const tamanos = { T: "Diminuto", S: "Pequeño", M: "Mediano", L: "Grande", H: "Enorme", G: "Gargantuesco", V: "Variable" };
                    informacion.size.forEach(function (tamano) {
                        if (tamanos[tamano]) textosTamano.push(tamanos[tamano]);
                    });
                }
                if (textosTamano.length) {
                    rasgos.push({ nombre: "Tamaño", descripcion: textosTamano.join(" / ") });
                }
            }

            if (entradas.length) {
                entradas.forEach(function (entrada) {
                    if (typeof entrada === "string") {
                        const textos = [];
                        obtenerTextosRasgoRaza(entrada, textos);
                        if (textos.length) {
                            rasgos.push({ nombre: "Información", descripcion: textos.join(" ") });
                        }
                        return;
                    }
                    if (
                        !entrada ||
                        typeof entrada !== "object" ||
                        typeof entrada.name !== "string"
                    ) {
                        return;
                    }

                    const textosRasgo = [];
                    obtenerTextosRasgoRaza(entrada.entries || entrada.entry || entrada.items, textosRasgo);

                    if (textosRasgo.length) {
                        rasgos.push({
                            nombre: entrada.name,
                            descripcion: textosRasgo.join(" ")
                        });
                    }
                });
            }

            const listaRasgos = rasgosFichaRaza.querySelector("ul");
            listaRasgos.replaceChildren();

            rasgos.forEach(function (rasgo) {
                const elemento = document.createElement("li");
                const nombre = document.createElement("strong");

                nombre.textContent = rasgo.nombre;
                elemento.appendChild(nombre);

                if (rasgo.descripcion !== "") {
                    elemento.appendChild(
                        document.createTextNode(" — " + rasgo.descripcion)
                    );
                }

                listaRasgos.appendChild(elemento);
            });

            rasgosFichaRaza.hidden = rasgos.length === 0;

            if (informacion.image) {
                imagenFichaRaza.onload = function () {
                    imagenFichaRaza.hidden = false;
                };

                imagenFichaRaza.onerror = function () {
                    imagenFichaRaza.hidden = true;
                    imagenFichaRaza.removeAttribute("src");
                };

                imagenFichaRaza.src = informacion.image;
                imagenFichaRaza.alt =
                    "Ilustración de " + fichaRaza.querySelector(".ficha-raza-nombre").textContent;
            }
        }

        selectorRaza.addEventListener("change", actualizarFichaRaza);

        /*
         * ==========================================
         * FICHA DE CLASE
         * ==========================================
         */

        const selectorClase = document.getElementById("clase");
        const fichaClase = document.getElementById("ficha-clase");
        const mensajeClaseVacia = fichaClase.querySelector(".ficha-clase-vacia");
        const contenidoFichaClase = fichaClase.querySelector(".ficha-clase-contenido");
        const descripcionFichaClase = fichaClase.querySelector(".ficha-clase-descripcion");
        const informacionFichaClase = fichaClase.querySelector(".ficha-clase-informacion");
        const rasgosFichaClase = fichaClase.querySelector(".ficha-clase-rasgos");

        function resumirTexto(texto, limite) {
            if (texto.length <= limite) {
                return texto;
            }

            const corte = texto.lastIndexOf(" ", limite);

            return texto.slice(0, corte > 0 ? corte : limite).trim() + "…";
        }

        function describirCompetencia(valor) {
            if (typeof valor === "string") {
                return valor
                    .replace(/\{@[a-zA-Z]+\s+([^}|]+)(?:\|[^}]*)?\}/g, "$1")
                    .replace(/\{@[^}]+\}/g, "")
                    .trim();
            }

            if (Array.isArray(valor)) {
                return valor.map(describirCompetencia).filter(Boolean).join(", ");
            }

            if (!valor || typeof valor !== "object") {
                return "";
            }

            if (valor.choose && typeof valor.choose === "object") {
                const opciones = describirCompetencia(valor.choose.from);
                const cantidad = valor.choose.count || 1;
                return opciones ? "elige " + cantidad + ": " + opciones : "";
            }

            if (typeof valor.any === "number") {
                return "elige " + valor.any + " opciones";
            }

            return Object.keys(valor).filter(function (clave) {
                return valor[clave] === true;
            }).join(", ");
        }

        function actualizarFichaClase() {
            const indice = selectorClase.value;
            const clase = indice === "" ? null : clases[Number(indice)];
            const informacion = indice === "" ? null : informacionClases[Number(indice)];
            const coincideClase = clase && informacion &&
                clase.name === informacion.name &&
                (clase.source || "") === informacion.source &&
                (clase.edition || "") === informacion.edition;

            mensajeClaseVacia.hidden = Boolean(coincideClase);
            contenidoFichaClase.hidden = !coincideClase;

            if (!coincideClase) {
                return;
            }

            fichaClase.querySelector(".ficha-clase-nombre").textContent =
                informacion.name;
            fichaClase.querySelector(".ficha-clase-origen").textContent =
                [informacion.source, informacion.edition].filter(Boolean).join(" · ");

            const textosDescripcion = [];
            obtenerTextos(informacion.description, textosDescripcion);
            const textoDescripcion = resumirTexto(textosDescripcion.join(" "), 500);

            if (textoDescripcion !== "") {
                descripcionFichaClase.querySelector("p").textContent = textoDescripcion;
                descripcionFichaClase.hidden = false;
            } else {
                descripcionFichaClase.hidden = true;
            }

            const datosBasicos = [];
            if (informacion.hd && informacion.hd.faces) {
                datosBasicos.push("Dado de golpe: d" + informacion.hd.faces);
            }

            const competencias = informacion.startingProficiencies || {};
            [
                ["Armadura", competencias.armor],
                ["Armas", competencias.weapons],
                ["Herramientas", competencias.tools],
                ["Habilidades", competencias.skills]
            ].forEach(function (dato) {
                const descripcion = describirCompetencia(dato[1]);
                if (descripcion !== "") {
                    datosBasicos.push(dato[0] + ": " + descripcion);
                }
            });

            const listaInformacion = informacionFichaClase.querySelector("ul");
            listaInformacion.replaceChildren();
            datosBasicos.forEach(function (dato) {
                const elemento = document.createElement("li");
                elemento.textContent = dato;
                listaInformacion.appendChild(elemento);
            });
            informacionFichaClase.hidden = datosBasicos.length === 0;

            const listaRasgos = rasgosFichaClase.querySelector("ul");
            listaRasgos.replaceChildren();
            (informacion.features || []).slice(0, 6).forEach(function (rasgo) {
                const elemento = document.createElement("li");
                const nombre = document.createElement("strong");
                const textos = [];

                nombre.textContent = rasgo.name;
                elemento.appendChild(nombre);
                obtenerTextos(rasgo.entries, textos);

                if (textos.length > 0) {
                    elemento.appendChild(
                        document.createTextNode(" — " + resumirTexto(textos.join(" "), 180))
                    );
                }

                listaRasgos.appendChild(elemento);
            });
            rasgosFichaClase.hidden = listaRasgos.children.length === 0;
        }

        selectorClase.addEventListener("change", actualizarFichaClase);


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
        function prepararEnvio(event) {

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

            if (raza === "") {
                alert("Debes seleccionar una raza para el personaje.");
                event.preventDefault();
                return;
            }


            /*
             * Clase
             */
            const indiceClase =
                document.getElementById("clase").value;

            if (indiceClase === "") {
                alert("Debes seleccionar una clase para el personaje.");
                event.preventDefault();
                return;
            }


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
                clases[Number(indiceClase)].name + "|" +
                (clases[Number(indiceClase)].source || "");

            document.getElementById("metodo-hidden").value =
                metodo;

            document.getElementById("atributos-hidden").value =
                JSON.stringify(atributosFinales);
        }

    </script>

</body>

</html>