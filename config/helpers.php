<?php
/**
 * Obtiene la tasa oficial del dólar desde una API (ejemplo pydolarve.org)
 * Devuelve float o null si falla.
 */
function obtenerTasaOficial() {
    try {
        $url = "https://pydolarve.org/api/v1/dollar"; 
        $response = file_get_contents($url);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        // ⚠️ Ajusta según el formato de la API
        if (isset($data['monitors']['bcv']['price'])) {
            return (float) $data['monitors']['bcv']['price'];
        }

        return null;
    } catch (Exception $e) {
        return null;
    }
}
