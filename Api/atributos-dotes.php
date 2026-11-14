<?php

function variantesAtributosDote($dote) {
    $atributos = ["str", "dex", "con", "int", "wis", "cha"];
    $variantes = [];
    foreach ($dote["ability"] ?? [] as $regla) {
        if (!is_array($regla)) {
            throw new RuntimeException("La mejora de atributos de la dote no es válida.");
        }
        $fijos = [];
        foreach ($atributos as $atributo) {
            if (isset($regla[$atributo])) {
                if (!is_int($regla[$atributo]) || $regla[$atributo] < 1) {
                    throw new RuntimeException("La mejora de atributos de la dote no es compatible.");
                }
                $fijos[$atributo] = $regla[$atributo];
            }
        }
        $opciones = [];
        $incrementos = [];
        $nota = "";
        if (isset($regla["choose"])) {
            $elige = $regla["choose"];
            if (
                !is_array($elige) || !isset($elige["from"]) || !is_array($elige["from"]) ||
                !$elige["from"] || array_diff($elige["from"], $atributos) ||
                !is_int($elige["count"] ?? 1) || ($elige["count"] ?? 1) < 1 ||
                !is_int($elige["amount"] ?? 1) || ($elige["amount"] ?? 1) < 1
            ) {
                throw new RuntimeException("La elección de atributos de la dote no es compatible.");
            }
            $opciones = $elige["from"];
            $incrementos = array_fill(0, $elige["count"] ?? 1, $elige["amount"] ?? 1);
            $nota = $elige["entry"] ?? "";
        }
        $maximo = $regla["max"] ?? 20;
        if (!is_int($maximo) || $maximo < 1 || !is_string($nota) || (!$fijos && !$incrementos)) {
            throw new RuntimeException("La mejora de atributos de la dote no es compatible.");
        }
        $variantes[] = [
            "fijos" => $fijos,
            "opciones" => $opciones,
            "incrementos" => $incrementos,
            "maximo" => $maximo,
            "nota" => $nota
        ];
    }
    return $variantes;
}
