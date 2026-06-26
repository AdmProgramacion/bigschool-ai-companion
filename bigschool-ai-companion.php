<?php
/**
 * Plugin Name: BIG School AI Companion
 * Plugin URI:  https://github.com/AdmProgramacion/bigschool-ai-companion
 * Description: Integración de LearnDash con OpenAI para generar recomendaciones
 *              de estudio personalizadas y notificar al CRM (ActiveCampaign/Zoho).
 * Version:     1.0.0
 * Author:      BIG School Dev Team
 * License:     GPL-2.0-or-later
 * Text Domain: bigschool-ai
 */

declare(strict_types=1);

// Seguridad: si alguien accede directamente al archivo, lo bloqueamos.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constantes del plugin — centralizadas aquí, usadas en todo el código.
define( 'BIGSCHOOL_AI_VERSION',  '1.0.0' );
define( 'BIGSCHOOL_AI_DIR',      plugin_dir_path( __FILE__ ) );
define( 'BIGSCHOOL_AI_URL',      plugin_dir_url( __FILE__ ) );

/**
 * Carga automática de clases.
 * En lugar de hacer require_once de cada archivo manualmente,
 * este autoloader los carga cuando se necesitan.
 * Sigue el estándar PSR-4.
 */
spl_autoload_register( function ( string $class_name ): void {

    // Prefijo de nuestro namespace
    $prefix = 'BigSchool\\';

    // Si la clase no pertenece a nuestro namespace, ignoramos
    if ( strpos( $class_name, $prefix ) !== 0 ) {
        return;
    }

    // Convertimos namespace a ruta de archivo
    // BigSchool\Handler\LessonCompletionHandler
    // → includes/Handler/LessonCompletionHandler.php
    $relative_class = substr( $class_name, strlen( $prefix ) );
    $file = BIGSCHOOL_AI_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * Punto de entrada del plugin.
 * Usamos el hook 'plugins_loaded' para asegurarnos de que
 * WordPress y todos los plugins (incluido LearnDash) están cargados.
 *
 * En modo desarrollo (WP_DEBUG = true) carga el simulador de hooks
 * para poder testear el flujo completo sin LearnDash instalado.
 *
 * En producción solo carga el Handler real si LearnDash está activo,
 * evitando errores si LearnDash no está instalado o se desactiva.
 */

add_action( 'plugins_loaded', function (): void {

    // En desarrollo cargamos el simulador siempre,
    // independientemente de si LearnDash está instalado.
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $simulator = new BigSchool\Dev\HookSimulator();
        $simulator->register();
    }

    // El handler real solo se carga si LearnDash está instalado.
    if ( ! function_exists( 'learndash_get_lesson_list' ) ) {
        return;
    }

    $handler = new BigSchool\Handler\LessonCompletionHandler();
    $handler->register();
} );