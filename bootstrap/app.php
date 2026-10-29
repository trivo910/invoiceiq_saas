<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
        $middleware->alias([
            'superadmin' => \App\Http\Middleware\RequireSuperAdmin::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'onboarded' => \App\Http\Middleware\EnsureOnboardingComplete::class,
            'subscription' => \App\Http\Middleware\EnsureSubscriptionWritable::class,
            'plan.limit' => \App\Http\Middleware\CheckPlanLimit::class,
            'plan.feature' => \App\Http\Middleware\CheckPlanFeature::class,
        ]);
        $middleware->validateCsrfTokens(except: ['webhooks/razorpay']);
        $middleware->web(append: [\App\Http\Middleware\EnforceImpersonation::class, \App\Http\Middleware\SecurityHeaders::class]);
        $middleware->appendToPriorityList(\Illuminate\Session\Middleware\StartSession::class, \App\Http\Middleware\EnforceImpersonation::class);
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\RequireSuperAdmin::class);
        $middleware->appendToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\ResolveTenant::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            return app(\App\Http\Middleware\SecurityHeaders::class)->apply(request(), $response);
        });
    })->create();
