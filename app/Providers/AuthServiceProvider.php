<?php

namespace App\Providers;

use App\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        $this->app['auth']->viaRequest('api', function ($request) {
            // $token = $request->get('token');
            $token = $request->cookie('token');
            if (!$token) {
                // Unauthorized response if token not there
                // return response()->json([
                //     'error' => 'Token not provided.'
                // ], 401);
                return null;
            }
            // we can use try and catch for expired token, domain exception, .
            try {
                $credentials = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
            } catch (ExpiredException $e) {
                return null;
            }
            if ($credentials->typ!=='login') {
                return null;
            }
            $now = time();
            if ($credentials->exp < $now) {
                return null;
            }
            $user =  User::find($credentials->usr);
            if (!$user || $user->deleted_at) {
                return null;
            }
            return $user;
        });
    }
}
