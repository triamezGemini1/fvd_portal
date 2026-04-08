-- Diagnóstico (solo lectura) para revisar consistencia contable EUR.
-- No modifica datos.

SELECT
    d.torneo_id,
    d.asociacion_id,
    d.monto_total AS deuda_bs_referencial,
    d.monto_total_eur AS deuda_eur_contable,
    COALESCE(SUM(r.monto_total), 0) AS pagado_bs_referencial,
    COALESCE(SUM(r.monto_dolares), 0) AS pagado_eur_contable,
    CASE
        WHEN COALESCE(d.monto_total_eur, 0) > 0
            THEN GREATEST(ROUND(d.monto_total_eur - COALESCE(SUM(r.monto_dolares), 0), 6), 0)
        ELSE NULL
    END AS saldo_eur_contable
FROM deuda_asociaciones d
LEFT JOIN relacion_pagos r
    ON r.torneo_id = d.torneo_id
   AND r.asociacion_id = d.asociacion_id
GROUP BY d.torneo_id, d.asociacion_id, d.monto_total, d.monto_total_eur
ORDER BY d.torneo_id DESC, d.asociacion_id ASC;
