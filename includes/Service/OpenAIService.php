<?php

declare(strict_types=1);

namespace BigSchool\Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * OpenAIService
 *
 * Responsabilidad única: comunicarse con la API de OpenAI.
 * El resto del plugin no sabe cómo funciona OpenAI.
 * Solo sabe que le pasa un prompt y recibe texto o un WP_Error.
 */
class OpenAIService {

    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL   = 'gpt-4o';
    private const TIMEOUT = 30; // segundos

    private string $api_key;

    public function __construct() {
        // La API key NUNCA va hardcodeada en el código.
        // Se guarda en wp-config.php como constante,
        // que a su vez lee de una variable de entorno del servidor.
        // Así nunca llega al repositorio de GitHub.
        $this->api_key = defined( 'OPENAI_API_KEY' )
            ? OPENAI_API_KEY
            : '';
    }

    /**
     * Envía un prompt a OpenAI y devuelve la respuesta.
     *
     * @param string $prompt El prompt construido por PromptBuilder.
     *
     * @return string|\WP_Error La respuesta de la IA o un error.
     */
    public function complete( string $prompt ): string|\WP_Error {

        // Validación: si no hay API key, fallamos rápido
        if ( empty( $this->api_key ) ) {
            return new \WP_Error(
                'missing_api_key',
                '[BIG School AI] No se ha configurado OPENAI_API_KEY.'
            );
        }

        // Construimos el body de la petición
        $body = wp_json_encode([
            'model'       => self::MODEL,
            'messages'    => [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens'  => 300,
            'temperature' => 0.7, // Creatividad moderada
        ]);

        // Usamos wp_remote_post — la forma correcta en WordPress.
        // Nunca usamos cURL directamente si WordPress nos da esta API.
        // Maneja redirects, timeouts y SSL de forma segura.
        $response = wp_remote_post(
            self::API_URL,
            [
                'timeout' => self::TIMEOUT,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => $body,
            ]
        );

        // 1. Comprobamos si WordPress tuvo un error de conexión
        if ( is_wp_error( $response ) ) {
            return new \WP_Error(
                'connection_error',
                '[BIG School AI] Error de conexión con OpenAI: '
                . $response->get_error_message()
            );
        }

        // 2. Comprobamos el código HTTP de la respuesta
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code !== 200 ) {
            return new \WP_Error(
                'api_error',
                sprintf(
                    '[BIG School AI] OpenAI devolvió HTTP %d.',
                    $status_code
                )
            );
        }

        // 3. Decodificamos el JSON de la respuesta
        $body_response = wp_remote_retrieve_body( $response );
        $data          = json_decode( $body_response, true );

        // 4. Validamos que la estructura de la respuesta es correcta
        if ( empty( $data['choices'][0]['message']['content'] ) ) {
            return new \WP_Error(
                'invalid_response',
                '[BIG School AI] Respuesta de OpenAI inesperada o vacía.'
            );
        }

        // 5. Devolvemos el texto limpio
        return trim( $data['choices'][0]['message']['content'] );
    }
}