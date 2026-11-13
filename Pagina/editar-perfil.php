<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.html");
    exit;
}

require_once "../Api/conexion.php";

$usuario_id = $_SESSION["usuario_id"];

$mensaje = "";
$error = "";


// =====================================================
// COMPROBAR DISPONIBILIDAD DEL NOMBRE DE USUARIO
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "GET" &&
    isset($_GET["comprobar_usuario"])
) {

    $usuario = trim($_GET["comprobar_usuario"]);

    if ($usuario === "") {
        echo "vacio";
        exit;
    }

    if (strlen($usuario) < 3) {
        echo "invalido";
        exit;
    }

    $consulta = $conexion->prepare(
        "SELECT id
         FROM usuarios
         WHERE usuario = ?
         AND id != ?
         LIMIT 1"
    );

    $consulta->execute([
        $usuario,
        $usuario_id
    ]);

    $usuarioExiste = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($usuarioExiste) {
        echo "ocupado";
    } else {
        echo "disponible";
    }

    exit;

}


// =====================================================
// OBTENER DATOS ACTUALES
// =====================================================

$consulta = $conexion->prepare(
    "SELECT usuario, email, foto_perfil
     FROM usuarios
     WHERE id = ?"
);

$consulta->execute([$usuario_id]);

$usuarioBD = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$usuarioBD) {
    die("Usuario no encontrado");
}


// =====================================================
// GUARDAR CAMBIOS
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usuario = trim($_POST["usuario"] ?? "");

    // Comprobar nombre
    if ($usuario === "") {

        $error = "El nombre de usuario es obligatorio.";

    } elseif (strlen($usuario) < 3) {

        $error = "El nombre de usuario debe tener al menos 3 caracteres.";

    } else {

        // Comprobar que el usuario no esté ocupado
        $consulta = $conexion->prepare(
            "SELECT id
             FROM usuarios
             WHERE usuario = ?
             AND id != ?"
        );

        $consulta->execute([
            $usuario,
            $usuario_id
        ]);

        if ($consulta->fetch()) {

            $error = "Ese nombre de usuario ya está ocupado.";

        } else {

            // Mantener la foto actual
            $foto_perfil = $usuarioBD["foto_perfil"];


            // =====================================================
            // SUBIR NUEVA FOTO
            // =====================================================

            if (
                isset($_FILES["foto_perfil"]) &&
                $_FILES["foto_perfil"]["error"] !== UPLOAD_ERR_NO_FILE
            ) {

                if ($_FILES["foto_perfil"]["error"] !== UPLOAD_ERR_OK) {

                    $error = "Ha ocurrido un error al subir la imagen.";

                } else {

                    $archivo = $_FILES["foto_perfil"];


                    // Máximo 5 MB
                    if ($archivo["size"] > 5 * 1024 * 1024) {

                        $error = "La imagen no puede superar los 5 MB.";

                    } else {

                        // Comprobar MIME real
                        $tipo = mime_content_type($archivo["tmp_name"]);

                        $tiposPermitidos = [
                            "image/jpeg",
                            "image/png",
                            "image/webp"
                        ];

                        if (!in_array($tipo, $tiposPermitidos)) {

                            $error = "Solo se permiten imágenes JPG, PNG o WEBP.";

                        } else {

                            // Crear carpeta si no existe
                            $carpeta = __DIR__ . "/img/perfiles/";

                            if (!is_dir($carpeta)) {
                                mkdir($carpeta, 0755, true);
                            }


                            // Extensión
                            if ($tipo === "image/jpeg") {
                                $extension = "jpg";
                            } elseif ($tipo === "image/png") {
                                $extension = "png";
                            } else {
                                $extension = "webp";
                            }


                            // Nombre único
                            $nombreArchivo =
                                "perfil_" .
                                $usuario_id .
                                "_" .
                                time() .
                                "." .
                                $extension;


                            $rutaCompleta =
                                $carpeta .
                                $nombreArchivo;


                            // Guardar imagen
                            if (
                                move_uploaded_file(
                                    $archivo["tmp_name"],
                                    $rutaCompleta
                                )
                            ) {

                                $foto_perfil =
                                    "img/perfiles/" .
                                    $nombreArchivo;

                            } else {

                                $error = "No se pudo guardar la imagen.";
                            }
                        }
                    }
                }
            }


            // =====================================================
            // ACTUALIZAR BASE DE DATOS
            // =====================================================

            if ($error === "") {

                $consulta = $conexion->prepare(
                    "UPDATE usuarios
                     SET usuario = ?, foto_perfil = ?
                     WHERE id = ?"
                );

                $consulta->execute([
                    $usuario,
                    $foto_perfil,
                    $usuario_id
                ]);


                // Actualizar sesión
                $_SESSION["usuario"] = $usuario;
                $_SESSION["foto_perfil"] = $foto_perfil;


                // Actualizar datos mostrados
                $usuarioBD["usuario"] = $usuario;
                $usuarioBD["foto_perfil"] = $foto_perfil;


                $mensaje = "Perfil actualizado correctamente.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editar perfil - RolForge</title>

    <link rel="stylesheet" href="../Api/global.css">

</head>


<body>

    <main class="editar-perfil">

        <section class="tarjeta-editar-perfil">

            <div class="cabecera-editar">

                <div>

                    <h1>Editar perfil</h1>

                    <p>
                        Modifica la información de tu perfil.
                    </p>

                </div>

            </div>


            <?php if ($mensaje !== ""): ?>

                <div class="mensaje-exito">

                    <?php echo htmlspecialchars($mensaje); ?>

                </div>

            <?php endif; ?>


            <?php if ($error !== ""): ?>

                <div class="mensaje-error">

                    <?php echo htmlspecialchars($error); ?>

                </div>

            <?php endif; ?>


            <form method="POST" enctype="multipart/form-data" id="formulario-perfil">

                <div class="contenido-editar">


                    <!-- ==========================================
                         PARTE IZQUIERDA
                    =========================================== -->

                    <div class="datos-editar">


                        <!-- USUARIO -->

                        <div class="campo-perfil">

                            <label for="usuario">
                                Nombre de usuario
                            </label>

                            <div class="campo-usuario">

                                <input type="text" id="usuario" name="usuario"
                                    value="<?php echo htmlspecialchars($usuarioBD["usuario"]); ?>" minlength="3"
                                    required autocomplete="off">

                                <span id="estado-usuario" class="estado-usuario"></span>

                            </div>

                            <small id="mensaje-usuario">
                                Comprueba que el nombre esté disponible.
                            </small>

                        </div>


                        <!-- EMAIL -->

                        <div class="campo-perfil">

                            <label for="email">
                                Correo electrónico
                            </label>

                            <input type="email" id="email" value="<?php echo htmlspecialchars($usuarioBD["email"]); ?>"
                                class="campo-bloqueado" disabled>

                            <small>
                                El correo electrónico no se puede modificar.
                            </small>

                        </div>


                    </div>


                    <!-- ==========================================
                         FOTO DE PERFIL
                    =========================================== -->

                    <div class="foto-editar-contenedor">

                        <label>
                            Foto de perfil
                        </label>


                        <img id="vista-previa" src="<?php
                        echo !empty($usuarioBD["foto_perfil"])
                            ? htmlspecialchars($usuarioBD["foto_perfil"])
                            : "img/default.png";
                        ?>" alt="Foto de perfil" class="foto-perfil-grande">


                        <label for="foto_perfil" class="boton-subir-foto">
                            🖼️ Subir nueva foto
                        </label>


                        <input type="file" id="foto_perfil" name="foto_perfil" accept="image/jpeg,image/png,image/webp"
                            hidden>

                        <small>
                            JPG, PNG o WEBP · Máximo 5 MB
                        </small>

                    </div>

                </div>


                <!-- ==========================================
                     BOTONES
                =========================================== -->

                <div class="botones-editar">

                    <a href="index.php" class="boton-cancelar">
                        Cancelar
                    </a>

                    <button type="submit" id="boton-guardar" disabled>
                        💾 Guardar cambios
                    </button>

                </div>

            </form>

        </section>

    </main>


    <!-- =====================================================
         JAVASCRIPT
    ====================================================== -->

    <script>

        const inputUsuario = document.getElementById("usuario");
        const estadoUsuario = document.getElementById("estado-usuario");
        const mensajeUsuario = document.getElementById("mensaje-usuario");

        const botonGuardar = document.getElementById("boton-guardar");

        const inputFoto = document.getElementById("foto_perfil");
        const vistaPrevia = document.getElementById("vista-previa");


        // Nombre original del usuario
        const usuarioOriginal = inputUsuario.value.trim();


        // Estado
        let usuarioValido = true;
        let fotoSeleccionada = false;

        let temporizador;


        // =====================================================
        // COMPROBAR USUARIO
        // =====================================================

        inputUsuario.addEventListener("input", function () {

            const usuario = inputUsuario.value.trim();

            clearTimeout(temporizador);


            // Si está vacío
            if (usuario === "") {

                estadoUsuario.textContent = "❌";

                estadoUsuario.className =
                    "estado-usuario incorrecto";

                mensajeUsuario.textContent =
                    "El nombre de usuario es obligatorio.";

                usuarioValido = false;

                actualizarBoton();

                return;
            }


            // Mínimo 3 caracteres
            if (usuario.length < 3) {

                estadoUsuario.textContent = "❌";

                estadoUsuario.className =
                    "estado-usuario incorrecto";

                mensajeUsuario.textContent =
                    "Debe tener al menos 3 caracteres.";

                usuarioValido = false;

                actualizarBoton();

                return;
            }


            // Si es exactamente el nombre actual
            if (usuario === usuarioOriginal) {

                estadoUsuario.textContent = "✅";

                estadoUsuario.className =
                    "estado-usuario correcto";

                mensajeUsuario.textContent =
                    "Este es tu nombre de usuario actual.";

                usuarioValido = true;

                actualizarBoton();

                return;
            }


            // Está comprobando
            estadoUsuario.textContent = "⏳";

            estadoUsuario.className =
                "estado-usuario comprobando";

            mensajeUsuario.textContent =
                "Comprobando disponibilidad...";

            usuarioValido = false;

            actualizarBoton();


            // Esperar 400 ms antes de consultar
            temporizador = setTimeout(function () {

                fetch(
                    "editar-perfil.php?comprobar_usuario=" +
                    encodeURIComponent(usuario)
                )

                    .then(response => response.text())

                    .then(resultado => {

                        resultado = resultado.trim();

    console.log("Nombre enviado:", usuario);
    console.log("Nombre original:", usuarioOriginal);
    console.log("Respuesta PHP:", resultado);ෆ
                        if (resultado === "disponible") {

                            estadoUsuario.textContent = "✅";

                            estadoUsuario.className =
                                "estado-usuario correcto";

                            mensajeUsuario.textContent =
                                "Nombre de usuario disponible.";

                            usuarioValido = true;

                        } else {

                            estadoUsuario.textContent = "❌";

                            estadoUsuario.className =
                                "estado-usuario incorrecto";

                            mensajeUsuario.textContent =
                                "Ese nombre de usuario ya está ocupado.";

                            usuarioValido = false;
                        }


                        actualizarBoton();

                    })

                    .catch(error => {

                        console.error(error);

                        estadoUsuario.textContent = "❌";

                        estadoUsuario.className =
                            "estado-usuario incorrecto";

                        mensajeUsuario.textContent =
                            "No se pudo comprobar el nombre de usuario.";

                        usuarioValido = false;

                        actualizarBoton();

                    });

            }, 400);

        });


        // =====================================================
        // FOTO
        // =====================================================

        inputFoto.addEventListener("change", function () {

            const archivo = inputFoto.files[0];


            if (!archivo) {

                fotoSeleccionada = false;

                actualizarBoton();

                return;
            }


            fotoSeleccionada = true;


            // Vista previa
            const lector = new FileReader();

            lector.onload = function (event) {

                vistaPrevia.src = event.target.result;

            };

            lector.readAsDataURL(archivo);


            actualizarBoton();

        });


        // =====================================================
        // ACTIVAR / DESACTIVAR BOTÓN
        // =====================================================

        function actualizarBoton() {

            /*
                    Se puede guardar si:
        
                    1. El usuario es válido
        
                    O
        
                    2. Se ha seleccionado una foto
                    */

            if (usuarioValido || fotoSeleccionada) {

                botonGuardar.disabled = false;

            } else {

                botonGuardar.disabled = true;

            }

        }


        // =====================================================
        // COMPROBACIÓN FINAL
        // =====================================================

        document
            .getElementById("formulario-perfil")
            .addEventListener("submit", function (event) {

                if (!usuarioValido) {

                    event.preventDefault();

                    alert(
                        "El nombre de usuario no es válido o ya está ocupado."
                    );

                    return;
                }

            });


        // Comprobar estado inicial
        actualizarBoton();

    </script>

</body>

</html>