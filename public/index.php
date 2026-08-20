<?php

use App\Kernel;

// Symfony 5.4 (LTS) predates PHP 8.4/8.5 and its own bootstrap still calls
// ReflectionProperty::setAccessible(), which the engine now deprecates. Swallow
// deprecations raised while the framework boots so they do not end up in the
// response; Symfony installs its own error handler moments later and takes over,
// so application deprecations are still logged as usual.
if (\PHP_VERSION_ID >= 80400) {
    set_error_handler(static fn (int $type): bool => \E_DEPRECATED === $type);
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
