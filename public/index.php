<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    return new Kernel(
        $context['APP_ENV'] ?? 'dev',
        (bool) ($context['APP_DEBUG'] ?? ('prod' !== ($context['APP_ENV'] ?? 'dev')))
    );
};
