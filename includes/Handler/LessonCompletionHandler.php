<?php

declare(strict_types=1);

namespace BigSchool\Handler;

use BigSchool\Builder\PromptBuilder;
use BigSchool\Service\OpenAIService;
use BigSchool\Service\CRMNotifierService;

// Seguridad: bloqueo de acceso directo.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LessonCompletionHandler
 *
 * Responsabilidad única (principio S de SOLID):
 * Este clase SOLO escucha el evento de LearnDash y coordina
 * el flujo. No sabe nada de OpenAI ni del CRM.
 * Eso lo delega a los Services.
 */
class LessonCompletionHandler {

    private OpenAIService $openai_service;
    private CRMNotifierService $crm_notifier;
    private PromptBuilder $prompt_builder;

    public function __construct() {
        $this->openai_service = new OpenAIService();
        $this->crm_notifier   = new CRMNotifierService();
        $this->prompt_builder = new PromptBuilder();
    }

    /**
     * Registra el hook de LearnDash en WordPress.
     * Se llama desde el archivo principal del plugin.
     */
    public function register(): void {
        add_action(
            'learndash_lesson_completed', // Hook de LearnDash
            [ $this, 'handle' ],          // Nuestro método
            10,                           // Prioridad
            1                             // Número de argumentos
        );
    }

    /**
     * Método principal que se ejecuta cuando un alumno
     * completa una lección.
     *
     * @param array $data Datos que envía LearnDash:
     *                    $data['user']   → WP_User
     *                    $data['lesson'] → WP_Post
     *                    $data['course'] → WP_Post
     */
    public function handle( array $data ): void {

        // 1. Extraemos y sanitizamos los datos del evento
        $user    = $data['user']   ?? null;
        $lesson  = $data['lesson'] ?? null;
        $course  = $data['course'] ?? null;

        // Si faltan datos esenciales, salimos sin hacer nada
        if ( ! $user instanceof \WP_User || ! $lesson instanceof \WP_Post ) {
            error_log( '[BIG School AI] handle(): Datos del evento incompletos.' );
            return;
        }

        $user_id    = absint( $user->ID );
        $user_email = sanitize_email( $user->user_email );
        $user_name  = sanitize_text_field( $user->display_name );
        $lesson_title = sanitize_text_field( $lesson->post_title );
        $course_title = $course instanceof \WP_Post
            ? sanitize_text_field( $course->post_title )
            : 'Curso desconocido';

        // 2. Construimos el prompt para OpenAI
        $prompt = $this->prompt_builder->build(
            $user_name,
            $lesson_title,
            $course_title
        );

        // 3. Llamamos a OpenAI y recibimos la recomendación
        $ai_response = $this->openai_service->complete( $prompt );

        // Si OpenAI falla, registramos el error y salimos
        if ( is_wp_error( $ai_response ) ) {
            error_log( sprintf(
                '[BIG School AI] Error OpenAI para user %d: %s',
                $user_id,
                $ai_response->get_error_message()
            ) );
            return;
        }

        // 4. Guardamos la respuesta en los metadatos del usuario
        update_user_meta(
            $user_id,
            'bigschool_ai_last_recommendation',
            sanitize_textarea_field( $ai_response )
        );

        // 5. Notificamos al CRM externo (ActiveCampaign / n8n)
        $this->crm_notifier->notify([
            'user_id'    => $user_id,
            'email'      => $user_email,
            'name'       => $user_name,
            'lesson'     => $lesson_title,
            'course'     => $course_title,
            'ai_summary' => $ai_response,
        ]);
    }
}