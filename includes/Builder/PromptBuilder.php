<?php

declare(strict_types=1);

namespace BigSchool\Builder;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PromptBuilder
 *
 * Responsabilidad única: construir el prompt que se envía a OpenAI.
 * Si mañana queremos cambiar el prompt, solo tocamos esta clase.
 * El resto del plugin no se entera.
 */
class PromptBuilder {

    /**
     * Construye el prompt personalizado para el alumno.
     *
     * @param string $user_name    Nombre del alumno.
     * @param string $lesson_title Título de la lección completada.
     * @param string $course_title Título del curso al que pertenece.
     *
     * @return string El prompt listo para enviar a OpenAI.
     */
    public function build(
        string $user_name,
        string $lesson_title,
        string $course_title
    ): string {

        return <<<PROMPT
        Eres un tutor educativo experto del campus online BIG School,
        especializado en tecnología, SEO e Inteligencia Artificial.

        El alumno "{$user_name}" acaba de completar la lección
        "{$lesson_title}" perteneciente al curso "{$course_title}".

        Tu tarea es generar una recomendación de estudio personalizada
        que incluya:

        1. Un resumen breve (2-3 frases) de los conceptos clave
           que el alumno acaba de trabajar.
        2. Una recomendación concreta de qué estudiar a continuación
           para consolidar el aprendizaje.
        3. Un consejo práctico para aplicar lo aprendido en un
           proyecto real.

        Responde en español, de forma cercana y motivadora.
        Máximo 150 palabras.
        PROMPT;
    }
}