<?php

namespace App\Http\Middleware;

use Closure;

class AddHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $response->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Content-Range, Content-Disposition, Content-Description, X-Auth-Token,Authorization');
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Control-Allow-Origin', 'http://localhost:3000');
        $response->header('Access-Control-Allow-Methods', 'GET', 'POST');
        //add more headers here
        return $response;
    }
}
