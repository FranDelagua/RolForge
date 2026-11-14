<?php

function mostrarSelectorMultiple(string $nombre, array $opciones, array $seleccion, int $minimo, ?int $maximo, string $titulo): void {
    $escapar = function ($valor) { return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8"); };
    ?>
    <fieldset class="selector-multiple" data-selector-multiple data-min="<?= $minimo ?>" data-max="<?= $maximo === null ? "" : $maximo ?>">
        <legend><?= $escapar($titulo) ?></legend>
        <div data-controles-originales>
            <?php foreach ($opciones as $valor => $etiqueta): ?>
                <label><input type="checkbox" name="<?= $escapar($nombre) ?>" value="<?= $escapar($valor) ?>" <?= in_array((string)$valor, $seleccion, true) ? "checked" : "" ?>> <?= $escapar($etiqueta) ?></label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <?php
}
