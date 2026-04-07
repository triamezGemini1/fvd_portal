<?php
/**
 * Ajustes de UI orientados a pantallas de laptop ~13" (aprox. 1280–1440px de ancho).
 * Usar estas constantes en CSS (vía variables inline) o en plantillas PHP.
 */

declare(strict_types=1);

/**
 * Identidad cromática FVD — regla 80 / 15 / 5
 * -------------------------------------------------------------------------
 * 80% Azul: superficie predominante (fondos de página y estructura).
 * 15% Amarillo: interacción, énfasis, bordes superiores de tarjetas, botones de acción.
 * 5% Rojo: acentos críticos puntuales (p. ej. salida de sesión, estados urgentes).
 *
 * Valores base alineados al logo (muestreo azul V / amarillo F). Rojo: tono institucional discreto.
 */
$FVD_AZUL = '#2E3092';
$FVD_AMARILLO = '#FFF200';
$FVD_ROJO = '#BE123C';

/** Tarjetas/paneles en admin: azul ligeramente más claro que el fondo general */
$FVD_AZUL_TARJETA = '#3A3EB5';

define('FVD_UI_COLOR_AZUL', $FVD_AZUL);
define('FVD_UI_COLOR_AMARILLO', $FVD_AMARILLO);
define('FVD_UI_COLOR_ROJO', $FVD_ROJO);
define('FVD_UI_COLOR_AZUL_CARD', $FVD_AZUL_TARJETA);
// Alias legacy (CSS --fvd-dorado); preferir FVD_UI_COLOR_AMARILLO en PHP nuevo.
define('FVD_UI_COLOR_DORADO', $FVD_AMARILLO);

// Tipografía (rem ≈ 16px root)
define('FVD_UI_FONT_XS', '0.75rem');    // 12px
define('FVD_UI_FONT_SM', '0.8125rem'); // 13px
define('FVD_UI_FONT_BASE', '0.9375rem'); // 15px — cuerpo principal cómodo en 13"
define('FVD_UI_FONT_MD', '1rem');       // 16px
define('FVD_UI_FONT_LG', '1.125rem');   // 18px
define('FVD_UI_FONT_XL', '1.25rem');    // 20px
define('FVD_UI_FONT_H3', '1.125rem');
define('FVD_UI_FONT_H2', '1.35rem');
define('FVD_UI_FONT_H1', '1.6rem');

define('FVD_UI_LINE_TIGHT', '1.25');
define('FVD_UI_LINE_NORMAL', '1.45');
define('FVD_UI_LINE_RELAXED', '1.6');

// Espaciado vertical / horizontal (px para layouts densos en 13")
define('FVD_UI_SPACE_XS', '4px');
define('FVD_UI_SPACE_SM', '8px');
define('FVD_UI_SPACE_MD', '12px');
define('FVD_UI_SPACE_LG', '16px');
define('FVD_UI_SPACE_XL', '20px');
define('FVD_UI_SPACE_2XL', '24px');

// Paddings de componentes habituales
define('FVD_UI_PAD_INPUT_Y', '8px');
define('FVD_UI_PAD_INPUT_X', '12px');
define('FVD_UI_PAD_BTN_Y', '8px');
define('FVD_UI_PAD_BTN_X', '14px');
define('FVD_UI_PAD_CARD', '14px');
define('FVD_UI_PAD_TABLE_CELL_Y', '8px');
define('FVD_UI_PAD_TABLE_CELL_X', '10px');
define('FVD_UI_PAD_NAVBAR_Y', '10px');
define('FVD_UI_PAD_NAVBAR_X', '16px');
define('FVD_UI_PAD_PAGE', '16px'); // margen interior del área de contenido principal

// Contenedor: ancho máximo legible en 13"
define('FVD_UI_CONTAINER_MAX', '1200px');
define('FVD_UI_SIDEBAR_WIDTH', '220px');

// Perfil compacto laptop 13" (14px cuerpo, paddings reducidos) — layout admin
define('FVD_UI_FONT_BODY_13IN', '0.875rem'); // 14px con root 16px
define('FVD_UI_FONT_H1_COMPACT', '1.35rem');
define('FVD_UI_FONT_H2_COMPACT', '1.15rem');
define('FVD_UI_FONT_H3_COMPACT', '1rem');
define('FVD_UI_PAD_PAGE_COMPACT', '12px');
define('FVD_UI_PAD_CARD_COMPACT', '10px');
define('FVD_UI_PAD_NAVBAR_Y_COMPACT', '8px');
define('FVD_UI_PAD_NAVBAR_X_COMPACT', '12px');
define('FVD_UI_PAD_INPUT_Y_COMPACT', '6px');
define('FVD_UI_PAD_INPUT_X_COMPACT', '10px');
define('FVD_UI_PAD_TABLE_CELL_Y_COMPACT', '6px');
define('FVD_UI_PAD_TABLE_CELL_X_COMPACT', '8px');
