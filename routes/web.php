<?php



/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});
Route::options(
   '/{any:.*}', 
   [
       'middleware' => 'cors', 
       function (){ 
           return response(['status' => 'success']); 
       }
   ]
);
// The above code is needed to allow any type of OPTIONS - POST, GET etc.
// $router->get('login', UserController@authenticate);

$router->group(['prefix' => 'api/', 'middleware' => 'cors'], function () use ($router) {
    $router->get('login', 'LoginController@login');
    $router->get('logout', 'LoginController@logout');
    $router->post('signup', 'RegisterController@signup');
    $router->get('forgot_password', 'ForgotPasswordController@forgotPassword');
    $router->post('forgot_password_request', 'ForgotPasswordController@forgotPasswordRequest');

    $router->group(['prefix' => 'users/'], function () use ($router) {
        $router->get('list', 'UserController@list');
        $router->get('role_change', 'UserController@roleChange');
        $router->get('delete', ['uses'=>'UserController@delete']);
        $router->get('create', ['uses'=>'UserController@create']);
    });

    $router->group(['prefix' => 'tasks/'], function () use ($router) {
        $router->get('create', 'TaskController@create');
        $router->get('update', 'TaskController@update');
        $router->get('update_status', 'TaskController@update_status');
        $router->get('delete', 'TaskController@delete');
        $router->get('dashboard', 'TaskController@dashboard');
        $router->get('dashboard_tasks', 'TaskController@dashboardTasks');
        $router->get('filter', 'TaskController@filter');
        $router->get('task', 'TaskController@task');
        $router->get('get_assignable_users','TaskController@getAssignableUsers');
    });
});
// 'middleware'=>'role:admin',


$router->get('/test', 'LoginController@test');
