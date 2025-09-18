<?php

return [

    // Sentry DSN (Data Source Name)
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Capture release version
    'release' => trim(exec('git describe --tags --abbrev=0')) ?: null,

    // Environment name
    'environment' => env('APP_ENV', 'production'),

    // Breadcrumbs configuration
    'breadcrumbs' => [
        'logs' => true,
        'cache' => true,
        'livewire' => true,
        'sql_queries' => true,
        'sql_bindings' => true,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
    ],

    // Performance monitoring
    'tracing' => [
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs' => true,
        'sql_queries' => true,
        'sql_origin' => true,
        'views' => true,
        'livewire' => true,
        'http_client_requests' => true,
        'default_integrations' => true,

        // Sampling rate (0.0 to 1.0)
        'sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', env('APP_ENV') === 'production' ? 0.1 : 1.0),
    ],

    // Send default PII (Personally Identifiable Information)
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // Error types to ignore
    'ignore_exceptions' => [
        Illuminate\Auth\AuthenticationException::class,
        Illuminate\Auth\Access\AuthorizationException::class,
        Symfony\Component\HttpKernel\Exception\HttpException::class,
        Illuminate\Database\Eloquent\ModelNotFoundException::class,
        Illuminate\Session\TokenMismatchException::class,
        Illuminate\Validation\ValidationException::class,
    ],

    // Integrations
    'integrations' => [
        // Captures SQL queries
        Sentry\Laravel\Integration::class,
    ],

    // Before send callback
    'before_send' => function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
        // Filter sensitive data
        if ($event->getRequest()) {
            $request = $event->getRequest();

            // Remove sensitive headers
            $headers = $request->getHeaders();
            unset($headers['authorization']);
            unset($headers['cookie']);
            $request->setHeaders($headers);

            // Remove sensitive data from request body
            $data = $request->getData();
            if (isset($data['password'])) {
                $data['password'] = '[FILTERED]';
            }
            if (isset($data['credit_card'])) {
                $data['credit_card'] = '[FILTERED]';
            }
            $request->setData($data);
        }

        return $event;
    },

    // Tags to be sent with every event
    'tags' => [
        'app.version' => config('app.version', '1.0.0'),
        'server.name' => gethostname(),
        'php.version' => PHP_VERSION,
        'laravel.version' => app()->version(),
    ],

    // Profiles sample rate
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.5),

];