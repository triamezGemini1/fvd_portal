<?php

declare(strict_types=1);

/**
 * Lee el tipo de cambio oficial EUR (Bs./EUR) desde la página pública del BCV.
 */
final class BcvEuroFetcher
{
    private const URL = 'https://www.bcv.org.ve/seccionportal/tipo-de-cambio-oficial-del-bcv';

    /**
     * @return array{ok:bool, rate?:float, raw?:string, error?:string}
     */
    public static function fetchOfficialEuroRate(): array
    {
        $html = self::httpGet(self::URL);
        if ($html === null) {
            return ['ok' => false, 'error' => 'No se pudo conectar con el sitio del BCV.'];
        }

        if (!preg_match('/<div\s+id="euro"[^>]*>.*?<strong>\s*([^<]+?)\s*<\/strong>/si', $html, $m)) {
            return ['ok' => false, 'error' => 'No se encontró la tasa del euro en la página del BCV (formato cambiado).'];
        }

        $raw = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        try {
            $rate = self::parseSpanishNumber($raw);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Valor de euro ilegible: ' . $raw];
        }

        if ($rate <= 0) {
            return ['ok' => false, 'error' => 'Tasa del euro no válida.'];
        }

        return ['ok' => true, 'rate' => round($rate, 10), 'raw' => $raw];
    }

    private static function parseSpanishNumber(string $s): float
    {
        $s = trim(str_replace(["\xc2\xa0", ' '], '', $s));
        if ($s === '') {
            throw new InvalidArgumentException('empty');
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $s)) {
            return (float) str_replace(['.', ','], ['', '.'], $s);
        }
        if (preg_match('/^\d+,\d+$/', $s)) {
            return (float) str_replace(',', '.', $s);
        }
        if (preg_match('/^\d+(\.\d+)?$/', $s)) {
            return (float) $s;
        }

        $clean = preg_replace('/[^\d.,\-]/', '', $s) ?? '';

        return (float) str_replace(',', '.', $clean);
    }

    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FVD-Portal/1.0; +https://www.bcv.org.ve)',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code < 200 || $code >= 400) {
                return null;
            }

            return (string) $body;
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 20,
                'header' => "User-Agent: Mozilla/5.0 (compatible; FVD-Portal/1.0)\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        return $body !== false ? (string) $body : null;
    }
}
