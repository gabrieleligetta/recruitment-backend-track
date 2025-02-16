<?php

use App\Http\Middleware\AccessDirective;

return [
    App\Providers\Handler::class,
    'access' => AccessDirective::class,
];
