<?php

declare(strict_types=1);

function configure_error_handling(): void
{
    error_reporting(E_ALL);
    ini_set('display_errors', APP_DEBUG ? '1' : '0');
    ini_set('log_errors', '1');

    set_exception_handler(static function (Throwable $exception): void {
        error_log((string) $exception);
        http_response_code(500);

        if (APP_DEBUG) {
            echo '<pre>' . escape_html($exception->getMessage()) . '</pre>';
            return;
        }

        echo '<h1>Something went wrong</h1><p>Please try again shortly.</p>';
    });
}
