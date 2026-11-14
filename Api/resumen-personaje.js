(function () {
    const formularioGuardar = document.getElementById("formulario-guardar-personaje");
    const botonGuardar = formularioGuardar.querySelector("button");
    const estado = document.getElementById("estado-elecciones");
    const mensaje = document.getElementById("estado-guardado-eleccion");
    const idsIniciales = Array.from(document.querySelectorAll("[data-id-decision]")).map(function (elemento) {
        return elemento.dataset.idDecision;
    });
    let cola = Promise.resolve();
    let pendientesEnvios = 0;
    let pendientes = botonGuardar.disabled;
    let error = false;
    let idsActuales = idsIniciales;

    function actualizarBoton() {
        botonGuardar.disabled = pendientes || pendientesEnvios > 0 || error;
    }

    function mostrarPendientes(lista) {
        document.getElementById("indicacion-resumen").textContent = lista.length
            ? "Datos guardados temporalmente. Revisa las elecciones pendientes."
            : "Todas las elecciones están completas. Revisa el personaje antes de guardarlo.";
        estado.replaceChildren();
        const titulo = document.createElement("h2");
        if (!lista.length) {
            titulo.textContent = "Personaje completo";
            const texto = document.createElement("p");
            texto.textContent = "Todas las elecciones están completas.";
            estado.append(titulo, texto);
            return;
        }
        const texto = document.createElement("p");
        texto.textContent = "El personaje no puede guardarse todavía.";
        titulo.textContent = "Decisiones pendientes";
        const ul = document.createElement("ul");
        lista.forEach(function (decision) {
            const li = document.createElement("li");
            const titulo = decision.decision.replace(/\|[a-zA-Z0-9-]+/g, "")
                .replace(/weaponSimple/g, "arma sencilla").replace(/weaponMartial/g, "arma marcial")
                .replace(/focusSpellcastingArcane/g, "foco arcano");
            li.textContent = decision.entidad.split("|")[0] + " — " + titulo + ". " + decision.motivo;
            ul.appendChild(li);
        });
        estado.append(texto, titulo, ul);
    }

    document.querySelectorAll("[data-guardar-eleccion]").forEach(function (formulario) {
        formulario.addEventListener("change", function (evento) {
            if (evento.target.type === "search") return;
            const datos = new FormData(formulario);
            pendientesEnvios++;
            mensaje.textContent = "Guardando selección...";
            actualizarBoton();
            cola = cola.then(async function () {
                const respuesta = await fetch(formulario.action, {
                    method: "POST", body: datos, headers: {"X-Requested-With": "XMLHttpRequest"}
                });
                const resultado = await respuesta.json();
                if (!respuesta.ok || resultado.error) throw new Error(resultado.error || "No se pudo guardar la selección.");
                pendientes = resultado.pendientes.length > 0;
                idsActuales = resultado.decisiones;
                mostrarPendientes(resultado.pendientes);
            }).catch(function (fallo) {
                error = true;
                mensaje.textContent = fallo.message + " Recarga la página y vuelve a intentarlo.";
            }).then(function () {
                pendientesEnvios--;
                if (!pendientesEnvios && !error) {
                    if (JSON.stringify(idsActuales) !== JSON.stringify(idsIniciales)) {
                        location.reload();
                        return;
                    }
                    mensaje.textContent = "Selección guardada.";
                }
                actualizarBoton();
            });
        });
    });
    formularioGuardar.addEventListener("submit", function (evento) {
        if (pendientes || pendientesEnvios || error) {
            evento.preventDefault();
            return;
        }
        botonGuardar.disabled = true;
        botonGuardar.textContent = "Guardando personaje...";
    });
})();
