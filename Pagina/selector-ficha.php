<?php

require_once __DIR__ . "/../Api/texto-5etools.php";

function mostrarSelectorFicha($id, $nombre, $opciones, $seleccion, $nombreCampo = null) {
    $etiqueta = htmlspecialchars($nombre, ENT_QUOTES, "UTF-8");
    $idHtml = htmlspecialchars($id, ENT_QUOTES, "UTF-8");
    ?>
    <div class="campo selector-ficha" data-selector-ficha>
        <?php if (count($opciones) >= 50): ?>
            <label for="<?= $idHtml ?>-buscar">Buscar <?= $etiqueta ?></label>
            <input id="<?= $idHtml ?>-buscar" type="search" autocomplete="off" data-buscar>
        <?php endif; ?>
        <label for="<?= $idHtml ?>"><?= $etiqueta ?></label>
        <select id="<?= $idHtml ?>" name="<?= htmlspecialchars($nombreCampo ?? $id, ENT_QUOTES, "UTF-8") ?>" required data-seleccion>
            <option value="" disabled <?= $seleccion === "" ? "selected" : "" ?>>Elegir <?= $etiqueta ?></option>
            <?php foreach ($opciones as $clave => $opcion): ?>
                <option value="<?= htmlspecialchars($clave, ENT_QUOTES, "UTF-8") ?>" <?= $seleccion === $clave ? "selected" : "" ?>>
                    <?= htmlspecialchars($opcion["name"], ENT_QUOTES, "UTF-8") ?> — <?= htmlspecialchars($opcion["source"], ENT_QUOTES, "UTF-8") ?>
                </option>
            <?php endforeach; ?>
        </select>
        <section class="ficha-clase" aria-live="polite">
            <p class="ficha-clase-vacia" data-vacio>Selecciona una opción para conocerla.</p>
            <div class="ficha-clase-contenido" data-contenido hidden>
                <header class="ficha-clase-cabecera">
                    <h3 class="ficha-clase-nombre" data-nombre></h3>
                    <span class="ficha-clase-origen" data-origen></span>
                </header>
                <section class="ficha-clase-descripcion" data-descripcion hidden>
                    <h4>Descripción</h4>
                    <p></p>
                </section>
                <section class="ficha-clase-rasgos" data-rasgos hidden>
                    <h4>Información</h4>
                    <ul></ul>
                </section>
            </div>
        </section>
        <script type="application/json" data-opciones><?= json_encode($opciones, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    </div>
    <?php
}
