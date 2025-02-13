<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e) {
            if ($e instanceof TokenExpiredException) {
                return response()->json([
                    'message' => 'Il token è scaduto.'
                ], 401);
            }
            if ($e instanceof TokenInvalidException) {
                return response()->json([
                    'message' => 'Il token non è valido.'
                ], 401);
            }
            if ($e instanceof JWTException) {
                return response()->json([
                    'message' => 'Token mancante o errore generico nel token.'
                ], 401);
            }
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Il token non è valido o è scaduto, per favore effettua di nuovo la login.'
                ], 401);
            }
            if ($e instanceof AccessDeniedHttpException) {
                return response()->json([
                    'message' => 'Accesso negato.'
                ], 403);
            }
            return $e;
        });
    })->create();
