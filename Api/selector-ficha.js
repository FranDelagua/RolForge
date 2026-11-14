document.querySelectorAll("[data-selector-ficha]").forEach(function (contenedor) {
    const opciones = JSON.parse(contenedor.querySelector("[data-opciones]").textContent);
    const selector = contenedor.querySelector("[data-seleccion]");
    const buscador = contenedor.querySelector("[data-buscar]");
    const contenido = contenedor.querySelector("[data-contenido]");
    const descripcion = contenedor.querySelector("[data-descripcion]");
    const rasgos = contenedor.querySelector("[data-rasgos]");

    function actualizar() {
        const opcion = opciones[selector.value];
        contenedor.querySelector("[data-vacio]").hidden = Boolean(opcion);
        contenido.hidden = !opcion;
        if (!opcion) return;

        contenedor.querySelector("[data-nombre]").textContent = opcion.name;
        contenedor.querySelector("[data-origen]").textContent = opcion.source;
        descripcion.hidden = !opcion.descripcion;
        descripcion.querySelector("p").textContent = opcion.descripcion || "";
        const lista = rasgos.querySelector("ul");
        lista.replaceChildren();
        (opcion.detalles || []).forEach(function (detalle) {
            const elemento = document.createElement("li");
            const titulo = document.createElement("strong");
            titulo.textContent = detalle.nombre;
            elemento.appendChild(titulo);
            if (detalle.texto) {
                elemento.appendChild(document.createTextNode(" — " + detalle.texto));
            }
            lista.appendChild(elemento);
        });
        rasgos.hidden = lista.children.length === 0;
    }

    if (buscador) {
        buscador.addEventListener("input", function () {
            const texto = buscador.value.trim().toLocaleLowerCase();
            Array.from(selector.options).forEach(function (opcion) {
                if (opcion.value) {
                    opcion.hidden = opcion.value !== selector.value &&
                        !opcion.textContent.toLocaleLowerCase().includes(texto);
                }
            });
        });
    }
    selector.addEventListener("change", actualizar);
    actualizar();
});
