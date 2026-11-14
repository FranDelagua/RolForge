(function () {
    document.querySelectorAll("[data-selector-multiple]").forEach(function (selector) {
        const originales = selector.querySelector("[data-controles-originales]");
        const opciones = Array.from(originales.querySelectorAll('input[type="checkbox"]')).map(function (input) {
            return {input: input, etiqueta: input.parentElement.textContent.trim()};
        });
        const maximo = selector.dataset.max === "" ? null : Number(selector.dataset.max);
        originales.hidden = true;

        const buscar = document.createElement("input");
        buscar.type = "search";
        buscar.placeholder = "Buscar opciones...";
        buscar.setAttribute("aria-label", "Buscar opciones");
        const columnas = document.createElement("div");
        columnas.className = "selector-multiple-columnas";
        const disponibles = document.createElement("div");
        const elegidas = document.createElement("div");
        const tituloDisponibles = document.createElement("h3");
        tituloDisponibles.textContent = "Disponibles";
        const tituloElegidas = document.createElement("h3");
        tituloElegidas.textContent = "Seleccionados";
        const listaDisponibles = document.createElement("ul");
        const listaElegidas = document.createElement("ul");
        disponibles.append(tituloDisponibles, listaDisponibles);
        elegidas.append(tituloElegidas, listaElegidas);
        columnas.append(disponibles, elegidas);
        const contador = document.createElement("p");
        contador.setAttribute("aria-live", "polite");
        selector.append(buscar, columnas, contador);

        function cambiar(opcion, seleccionada) {
            const cantidad = opciones.filter(function (item) { return item.input.checked; }).length;
            if (seleccionada && maximo !== null && cantidad >= maximo) return;
            opcion.input.checked = seleccionada;
            dibujar();
            opcion.input.dispatchEvent(new Event("change", {bubbles: true}));
        }

        function fila(opcion, seleccionada, cantidad) {
            const elemento = document.createElement("li");
            const nombre = document.createElement("span");
            nombre.textContent = opcion.etiqueta;
            const boton = document.createElement("button");
            boton.type = "button";
            boton.textContent = seleccionada ? "× Quitar" : "+ Añadir";
            boton.setAttribute("aria-label", (seleccionada ? "Quitar " : "Añadir ") + opcion.etiqueta);
            boton.disabled = !seleccionada && maximo !== null && cantidad >= maximo;
            boton.addEventListener("click", function () { cambiar(opcion, !seleccionada); });
            elemento.addEventListener("dblclick", function (evento) {
                if (!evento.target.closest("button")) cambiar(opcion, !seleccionada);
            });
            elemento.append(nombre, boton);
            return elemento;
        }

        function dibujar() {
            listaDisponibles.replaceChildren();
            listaElegidas.replaceChildren();
            const filtro = buscar.value.trim().toLocaleLowerCase();
            const cantidad = opciones.filter(function (item) { return item.input.checked; }).length;
            opciones.forEach(function (opcion) {
                if (opcion.input.checked) {
                    listaElegidas.appendChild(fila(opcion, true, cantidad));
                } else if (opcion.etiqueta.toLocaleLowerCase().includes(filtro)) {
                    listaDisponibles.appendChild(fila(opcion, false, cantidad));
                }
            });
            contador.textContent = maximo === null ? cantidad + " seleccionados" : cantidad + " / " + maximo + " seleccionados";
            if (cantidad < Number(selector.dataset.min)) contador.textContent += " (mínimo " + selector.dataset.min + ")";
        }
        buscar.addEventListener("input", dibujar);
        dibujar();
    });
})();
