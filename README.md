# BIG School AI Companion

Plugin de WordPress desarrollado como solución técnica para el campus 
educativo **BIG School**, que integra el LMS LearnDash con la API de 
OpenAI para generar recomendaciones de estudio personalizadas y 
notificar al CRM externo (ActiveCampaign / Zoho) via webhook.

---

## Caso de uso

Cuando un alumno completa una lección en el campus de BIG School:

1. LearnDash dispara el evento `learndash_lesson_completed`
2. El plugin captura el evento y recoge los datos del alumno
3. Construye un prompt personalizado y llama a la API de OpenAI
4. Almacena la recomendación de IA en los metadatos del usuario
5. Notifica al CRM externo (ActiveCampaign / n8n / Make) via webhook

---

## Arquitectura
bigschool-ai-companion/

├── bigschool-ai-companion.php       # Bootstrap del plugin

└── includes/

├── Handler/

│   └── LessonCompletionHandler.php  # Captura el hook de LearnDash

├── Builder/

│   └── PromptBuilder.php            # Construye el prompt para OpenAI

└── Service/

├── OpenAIService.php            # Integración con API de OpenAI

└── CRMNotifierService.php       # Webhook al CRM externo


Cada clase tiene una única responsabilidad (principio S de SOLID).
El `Handler` coordina, los `Service` ejecutan, el `Builder` construye.

---

## Decisiones técnicas

### WordPress nativo sobre librerías externas
Se usa `wp_remote_post` en lugar de cURL directo porque WordPress 
ya tiene una capa de abstracción HTTP que maneja SSL, redirects y 
timeouts de forma consistente en cualquier servidor.

### Seguridad de credenciales
Las claves de API nunca se almacenan en el código ni en el 
repositorio. Se definen como constantes en `wp-config.php`, 
que a su vez las lee de variables de entorno del servidor.

```php
// wp-config.php
define( 'OPENAI_API_KEY',            getenv('OPENAI_API_KEY') );
define( 'BIGSCHOOL_CRM_WEBHOOK_URL', getenv('BIGSCHOOL_CRM_WEBHOOK_URL') );
define( 'BIGSCHOOL_WEBHOOK_TOKEN',   getenv('BIGSCHOOL_WEBHOOK_TOKEN') );
```

### Compatibilidad con n8n y Make
El payload del webhook está estructurado para ser compatible con 
n8n y Make sin transformación adicional. El campo `event` permite 
filtrar por tipo de evento en el nodo receptor.

### Manejo de errores con WP_Error
Se usa el estándar nativo de WordPress (`WP_Error`) en lugar de 
excepciones PHP, manteniendo consistencia con el ecosistema WordPress 
y facilitando el debugging en producción via `error_log`.

---

## Configuración

Añadir en `wp-config.php` antes de `/* That's all, stop editing! */`:

```php
define( 'OPENAI_API_KEY',            'sk-tuclaveaqui' );
define( 'BIGSCHOOL_CRM_WEBHOOK_URL', 'https://tu-webhook-crm.com' );
define( 'BIGSCHOOL_WEBHOOK_TOKEN',   'tu-token-secreto' );
```

---

## Requisitos

- WordPress 6.0+
- PHP 8.2+
- LearnDash 4.0+
- Cuenta en OpenAI con acceso a la API

---

## Flujo del webhook al CRM

El payload que se envía al CRM / n8n tiene esta estructura:

```json
{
  "event": "lesson_completed",
  "timestamp": "2024-01-15T10:30:00+01:00",
  "source": "bigschool_lms",
  "student": {
    "id": 42,
    "email": "alumno@ejemplo.com",
    "name": "Ana García"
  },
  "activity": {
    "lesson": "Introducción a los modelos de lenguaje",
    "course": "Máster en Inteligencia Artificial"
  },
  "ai_recommendation": "Texto generado por OpenAI..."
}
```

---

## Estándares aplicados

- **PSR-4** — Autoloading de clases con namespaces
- **SOLID** — Principios de diseño orientado a objetos
- **PHP 8.2** — Tipos estrictos con `declare(strict_types=1)`
- **WordPress Coding Standards** — Hooks, sanitización y WP_Error