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
    return view('welcome');
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
        $router->get('login-cpanel/{id}', 'CpanelController@loginAccount');
        //Email Account Routes
        $router->get('email-accounts/{id}', 'EmailAccountController@getEmailAccount');
        $router->post('create-email-accounts', 'EmailAccountController@addEmailAccount');
        $router->post('login-email-account', 'EmailAccountController@loginEmailAccount');
        $router->post('update-email-accounts-password', 'EmailAccountController@updateEmailPasswrod');
        $router->post('delete-email-accounts', 'EmailAccountController@deleteEmailAccount');
        $router->post('suspend-email-account-login', 'EmailAccountController@suspendLogin');
        $router->post('unsuspend-email-account-login', 'EmailAccountController@unsuspendLogin');
        $router->post('unsuspend-email-account-incoming', 'EmailAccountController@unsuspendIncoming');
        $router->post('suspend-email-account-incoming', 'EmailAccountController@suspendIncoming');
        //PHP ini Routes
        $router->post('php-ini-content', 'PhpIniController@getPhpIni');
        $router->post('update-php-ini-content', 'PhpIniController@updatePhpIni');
        $router->get('get-php-versions/{id}', 'PhpIniController@getVersion');
        $router->post('update-php-version', 'PhpIniController@updateVersion');
        $router->post('get-php-directives', 'PhpIniController@getDirectives');
        $router->post('update-php-directive', 'PhpIniController@updateDirectives');
        //FTP Account Routes
        $router->get('ftp-accounts/{id}', 'FtpAccountController@getFtpAccount');
        $router->post('ftp-server', 'FtpAccountController@getFtpServerInfo');
        $router->post('create-ftp-accounts', 'FtpAccountController@addFtpAccount');
        $router->post('update-ftp-accounts', 'FtpAccountController@updateFtpAccount');
        $router->post('delete-ftp-accounts', 'FtpAccountController@deleteFtpAccount');
        //MySql User RoutesRestrictions
        $router->get('login-phpmyadmin/{id}', 'MySqlController@loginPHPMYADMIN');
        $router->get('mysql-listing/{id}', 'MySqlController@getListing');
        $router->get('mysql-users/{id}', 'MySqlController@getUsers');
        $router->get('mysql-name-restrictions/{id}', 'MySqlController@getUsersRestrictions');
        $router->get('mysql-privileges', 'MySqlController@getPrivileges');
        $router->post('create-mysql-user', 'MySqlController@addUser');
        $router->post('update-mysql-user', 'MySqlController@updateUser');
        $router->post('update-mysql-user-password', 'MySqlController@updateUserPassword');
        $router->post('delete-mysql-user', 'MySqlController@deleteUser');
        //MySql Databases Routes
        $router->get('mysql-databases/{id}', 'MySqlDbController@getDatabases');
        $router->post('create-mysql-database', 'MySqlDbController@addDatabase');
        $router->post('update-mysql-database', 'MySqlDbController@updateDatabase');
        $router->post('delete-mysql-database', 'MySqlDbController@deleteDatabase');
        $router->post('remove-mysql-privileges', 'MySqlDbController@removePrivileges');
        $router->post('get-mysql-privileges', 'MySqlDbController@getPrivileges');
        $router->post('update-mysql-privileges', 'MySqlDbController@updatePrivileges');
        //Get Domain Info Route
        $router->get('domain-info/{id}', 'CpanelController@getUserInfo');
        $router->post('update/review', 'ReviewController@companyReview');
        $router->get('review/{id}', 'ReviewController@getRating');
        $router->get('criteria', 'ReviewController@getReviewCriteria');
        $router->get('logout', 'UserController@logout');
        $router->get('logout-all', 'UserController@logoutAll');
        //Get Delegate Account Permissions
        $router->get('delegate-permissions', 'DelegateAccountController@permissionList');
        $router->get('delegate-accounts[/{id}]', 'DelegateAccountController@accountList');
        $router->get('domain-list', 'DelegateAccountController@domainList');
        $router->get('delete-delegate-account/{id}', 'DelegateAccountController@deleteAccount');
        $router->post('create-delegate-account', 'DelegateAccountController@createAccount');
        $router->post('search-user', 'DelegateAccountController@searchUser');
        //Blocked Ip Routes
        $router->get('blocked-ips/{id}', 'BlockedIpController@getIps');
        $router->post('block-ip', 'BlockedIpController@blockIpAddress');
        $router->post('unblock-ip', 'BlockedIpController@deleteIpAddress');
    // });
    
	$router->group(['prefix' => 'delegate/account'], function () use ($router) {
        
    });
});

