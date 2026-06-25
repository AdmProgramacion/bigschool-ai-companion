<?php

declare(strict_types=1);

namespace BigSchool\Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CRMNotifierService
 *
 * Responsabilidad única: enviar los datos del alumno
 * y la recomendación de IA al CRM externo.
 *
 * Diseñado para ser compatible con:
 * - ActiveCampaign (webhook directo)
 * - Zoho CRM (webhook directo)
 * - n8n (como nodo receptor intermedio)
 * - Make (como webhook trigger)
 *
 * Si mañana cambiamos de CRM, solo tocamos esta clase.
 */
class CRMNotifierService {

    private const TIMEOUT = 15; // segundos

    private string $webhook_url;

    public function __construct() {
        // La URL del webhook tampoco va hardcodeada.
        // Mismo patrón que la API key de OpenAI.
        $this->webhook_url = defined( 'BIGSCHOOL_CRM_WEBHOOK_URL' )
            ? BIGSCHOOL_CRM_WEBHOOK_URL
            : '';
    }

    /**
     * Envía los datos del evento al CRM externo via webhook.
     *
     * @param array $data Datos del alumno y la recomendación de IA.
     *                    Esperamos: user_id, email, name,
     *                               lesson, course, ai_summary.
     *
     * @return bool|\WP_Error True si fue bien, WP_Error si falló.
     */
    public function notify( array $data ): bool|\WP_Error {

        // Validación: si no hay webhook configurado, avisamos
        if ( empty( $this->webhook_url ) ) {
            error_log( '[BIG School AI] CRMNotifierService: No hay webhook configurado.' );
            // No devolvemos error grave — el plugin sigue funcionando
            // aunque el CRM no esté configurado.
            return false;
        }

        // Construimos el payload que enviamos al CRM.
        // Estructura diseñada para ser compatible con n8n y Make
        // sin necesidad de transformación adicional.
        $payload = wp_json_encode([
            'event'     => 'lesson_completed',   // Tipo de evento
            'timestamp' => current_time( 'c' ),  // ISO 8601
            'source'    => 'bigschool_lms',       // Identificador del origen
            'student'   => [
                'id'    => absint( $data['user_id'] ?? 0 ),
                'email' => sanitize_email( $data['email'] ?? '' ),
                'name'  => sanitize_text_field( $data['name'] ?? '' ),
            ],
            'activity'  => [
                'lesson' => sanitize_text_field( $data['lesson'] ?? '' ),
                'course' => sanitize_text_field( $data['course'] ?? '' ),
            ],
            'ai_recommendation' => sanitize_textarea_field(
                $data['ai_summary'] ?? ''
            ),
        ]);

        // Enviamos el webhook
        $response = wp_remote_post(
            $this->webhook_url,
            [
                'timeout'     => self::TIMEOUT,
                'headers'     => [
                    'Content-Type' => 'application/json',
                    // Token de seguridad para verificar que el webhook
                    // viene de nuestro plugin y no de terceros.
                    'X-BigSchool-Token' => defined( 'BIGSCHOOL_WEBHOOK_TOKEN' )
                        ? BIGSCHOOL_WEBHOOK_TOKEN
                        : '',
                ],
                'body' => $payload,
            ]
        );

        // Manejo de errores de conexión
        if ( is_wp_error( $response ) ) {
            error_log( sprintf(
                '[BIG School AI] Error enviando webhook CRM: %s',
                $response->get_error_message()
            ) );
            return $response;
        }

        // Comprobamos el código HTTP de respuesta
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            error_log( sprintf(
                '[BIG School AI] CRM webhook devolvió HTTP %d para user %d.',
                $status_code,
                absint( $data['user_id'] ?? 0 )
            ) );
            return new \WP_Error(
                'crm_webhook_error',
                sprintf( 'CRM devolvió HTTP %d.', $status_code )
            );
        }

        // Todo correcto — registramos el éxito en el log
        error_log( sprintf(
            '[BIG School AI] Webhook CRM enviado correctamente para user %d.',
            absint( $data['user_id'] ?? 0 )
        ) );

        return true;
    }
}