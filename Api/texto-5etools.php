<?php

function primerTexto($contenido) {
    if (is_string($contenido)) {
        $texto = preg_replace_callback('/\{@([a-zA-Z0-9]+)\s+([^}]+)\}/u', function ($coincidencia) {
            $partes = explode("|", $coincidencia[2]);
            return strtolower($coincidencia[1]) === "filter" ? $partes[0] : ($partes[2] ?? $partes[0]);
        }, $contenido);
        return trim(preg_replace('/\s+/u', ' ', $texto));
    }
    if (is_array($contenido)) {
        foreach (["entries", "items", "entry", "text"] as $campo) {
            if (isset($contenido[$campo])) {
                return primerTexto($contenido[$campo]);
            }
        }
        if (isset($contenido["type"])) {
            return "";
        }
        foreach ($contenido as $elemento) {
            $texto = primerTexto($elemento);
            if ($texto !== "") {
                return $texto;
            }
        }
    }
    return "";
}
