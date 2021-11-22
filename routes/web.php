<?php

/** @var \Laravel\Lumen\Routing\Router $router */

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

// API route group
$router->group(['prefix' => 'api/v1', 'namespace' => 'v1'], function () use ($router) {
    
    //make api/v1/login
    $router->post('login', 'AuthController@login');

    // make api/v1/register
    $router->post('register', 'AuthController@register');

    // make api/v1/reset
    $router->post('forgot/password', 'AuthController@password_reset_link');
    $router->get('tokencheck/{token}', 'AuthController@password_reset_token_check');
    $router->post('reset/password', 'AuthController@update_new_password');
});

$router->group(['prefix' => 'api/v1', 'namespace' => 'v1','middleware'=> ['checktoken', 'auth']], function () use ($router) {
    
	// $router->group(['prefix' => 'user'], function () use ($router) {
        $router->post('update/password', 'UserController@updatePassword');
        $router->get('detail', 'UserController@getDetails');
        $router->get('countries[/{id}]', 'UserController@getCountries');
        $router->get('states/{countryId}[/{id}]', 'UserController@getStates');
        $router->post('update/detail', 'UserController@updateDetails');
        $router->post('update/address', 'UserController@updateAddress');
        $router->get('rating', 'ReviewController@getRatings');
        $router->get('orders-history', 'OrderController@ordersHistory');
        $router->get('orders-transaction', 'OrderController@ordersTransactions');
        $router->get('invoices[/{id}]', 'OrderController@invoiceList');
        $router->get('user/servers', 'CpanelController@orderedServers');
        $router->post('add/domain', 'CpanelController@addDomain');
        $router->get('email-accounts/{id}', 'CpanelController@getEmailAccount');
        $router->get('domain-info/{id}', 'CpanelController@getUserInfo');
        $router->post('update/review', 'ReviewController@companyReview');
        $router->get('review/{id}', 'ReviewController@getRating');
        $router->get('criteria', 'ReviewController@getReviewCriteria');
        $router->get('logout', 'UserController@logout');
        $router->get('logout-all', 'UserController@logoutAll');
    // });
});

