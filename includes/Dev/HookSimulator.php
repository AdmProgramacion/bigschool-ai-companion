<?php

declare(strict_types=1);

namespace BigSchool\Dev;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * HookSimulator
 *
 * SOLO PARA DESARROLLO Y TESTING.
 * Simula los hooks de LearnDash sin necesidad de tenerlo instalado.
 * En producción esta clase nunca se carga.
 *
 * Permite verificar el flujo completo:
 * LearnDash Hook → OpenAI → CRM
 * ...sin depender del entorno de producción.
 */
class HookSimulator {

    /**
     * Registra la página de testing en el menú de WP Admin.
     */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_post_bigschool_simulate_hook', [ $this, 'handle_simulation' ] );
    }

    /**
     * Añade la página al menú de WordPress.
     */
    public function add_menu_page(): void {
        add_menu_page(
            'BIG School AI — Simulador',     // Título de la página
            'BIG School AI',                  // Título en el menú
            'manage_options',                 // Capacidad requerida (solo admins)
            'bigschool-ai-simulator',         // Slug de la página
            [ $this, 'render_page' ],         // Función que renderiza el contenido
            'dashicons-welcome-learn-more',   // Icono del menú
            30                                // Posición en el menú
        );
    }

    /**
     * Renderiza la página del simulador en WP Admin.
     */
    public function render_page(): void {

        // Verificamos que el usuario tiene permisos
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No tienes permisos para acceder a esta página.' );
        }

        // Recogemos el resultado de la última simulación si existe
        $last_result = get_option( 'bigschool_ai_last_simulation_result', '' );
        $last_time   = get_option( 'bigschool_ai_last_simulation_time', '' );

        ?>
        <div class="wrap">
            <h1>🤖 BIG School AI Companion — Simulador de Hooks</h1>
            <p style="color:#666;">
                Esta herramienta simula el hook <code>learndash_lesson_completed</code>
                sin necesidad de tener LearnDash instalado.<br>
                Útil para desarrollo, testing y verificación del flujo completo.
            </p>

            <div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:4px;margin:20px 0;">
                <h2>Datos del evento simulado</h2>
                <table class="form-table">
                    <tr>
                        <th>Alumno</th>
                        <td><strong>Ana García</strong> (ana@bigschool.com)</td>
                    </tr>
                    <tr>
                        <th>Lección</th>
                        <td><strong>Introducción a los modelos de lenguaje GPT</strong></td>
                    </tr>
                    <tr>
                        <th>Curso</th>
                        <td><strong>Máster en Inteligencia Artificial Aplicada</strong></td>
                    </tr>
                    <tr>
                        <th>Hook disparado</th>
                        <td><code>learndash_lesson_completed</code></td>
                    </tr>
                </table>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'bigschool_simulate_hook', 'bigschool_nonce' ); ?>
                    <input type="hidden" name="action" value="bigschool_simulate_hook">
                    <p>
                        <button type="submit" class="button button-primary button-large">
                            ▶ Simular evento LearnDash
                        </button>
                    </p>
                </form>
            </div>

            <?php if ( $last_result ) : ?>
            <div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:4px;margin:20px 0;">
                <h2>Resultado de la última simulación</h2>
                <p style="color:#666;">Ejecutada el: <strong><?php echo esc_html( $last_time ); ?></strong></p>
                <div style="background:#f0f0f0;padding:15px;border-radius:4px;font-family:monospace;">
                    <?php echo nl2br( esc_html( $last_result ) ); ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Procesa la simulación cuando se pulsa el botón.
     */
    public function handle_simulation(): void {

        // Seguridad: verificamos nonce y permisos
        if ( ! isset( $_POST['bigschool_nonce'] ) ||
             ! wp_verify_nonce( $_POST['bigschool_nonce'], 'bigschool_simulate_hook' ) ) {
            wp_die( 'Verificación de seguridad fallida.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No tienes permisos.' );
        }

        // Creamos datos ficticios que simulan lo que enviaría LearnDash
        $fake_user          = new \stdClass();
        $fake_user->ID      = 1;
        $fake_user->user_email   = 'ana@bigschool.com';
        $fake_user->display_name = 'Ana García';

        $fake_lesson              = new \stdClass();
        $fake_lesson->ID          = 101;
        $fake_lesson->post_title  = 'Introducción a los modelos de lenguaje GPT';

        $fake_course              = new \stdClass();
        $fake_course->ID          = 10;
        $fake_course->post_title  = 'Máster en Inteligencia Artificial Aplicada';

        // Disparamos el hook exactamente igual que lo haría LearnDash
        do_action( 'learndash_lesson_completed', [
            'user'   => $fake_user,
            'lesson' => $fake_lesson,
            'course' => $fake_course,
        ] );

        // Guardamos el resultado para mostrarlo en la página
        $result = sprintf(
            " Hook disparado: learndash_lesson_completed\n" .
            " Alumno: %s (%s)\n" .
            " Lección: %s\n" .
            " Curso: %s\n" .
            " Timestamp: %s\n\n" .
            "Revisa el log del servidor para ver la respuesta completa de OpenAI y el envío al CRM.",
            $fake_user->display_name,
            $fake_user->user_email,
            $fake_lesson->post_title,
            $fake_course->post_title,
            current_time( 'Y-m-d H:i:s' )
        );

        update_option( 'bigschool_ai_last_simulation_result', $result );
        update_option( 'bigschool_ai_last_simulation_time', current_time( 'Y-m-d H:i:s' ) );

        // Redirigimos de vuelta a la página del simulador
        wp_redirect( admin_url( 'admin.php?page=bigschool-ai-simulator&simulated=1' ) );
        exit;
    }
}