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

$router->group(['prefix' => 'api/v1', 'namespace' => 'v1', 'middleware'=> ['checktoken', 'auth']], function () use ($router) {
    
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
        //Sub Domains Routes
        $router->group(['prefix' => 'subdomain'], function () use ($router) {
            $router->get('/{id}', 'SubDomainController@getSubDomain');
            $router->post('add', 'SubDomainController@addSubDomain');
            $router->post('update', 'SubDomainController@updateSubDomain');
            $router->post('delete', 'SubDomainController@deleteSubDomain');
        });
        //Addon Domains Routes
        $router->group(['prefix' => 'addon-domain'], function () use ($router) {
            $router->get('/{id}', 'AddonDomainController@getAddonsDomain');
            $router->post('add', 'AddonDomainController@addAddonsDomain');
            $router->post('delete', 'AddonDomainController@deleteAddonsDomain');
        });
        //Email Account Routes
        $router->get('email-accounts/{id}', 'EmailAccountController@getEmailAccount');
        $router->post('create-email-accounts', 'EmailAccountController@addEmailAccount');
        $router->post('email-client-settings', 'EmailAccountController@emailSetting');
        $router->post('login-email-account', 'EmailAccountController@loginEmailAccount');
        $router->get('login-webmail/{id}', 'EmailAccountController@loginWebmailAccount');
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
        $router->get('domain-list/{id}', 'CpanelController@getDomains');
        $router->get('domain-info/{id}', 'CpanelController@getUserInfo');
        $router->get('domain-detail/{id}', 'CpanelController@dnsInfo');
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
        $router->get('delete-delegate-domain/{id}', 'DelegateAccountController@deleteDomain');
        $router->post('create-delegate-account', 'DelegateAccountController@createAccount');
        $router->post('update-delegate-account', 'DelegateAccountController@updateAccount');
        $router->post('search-user', 'DelegateAccountController@searchUser');
        //Blocked Ip Routes
        $router->get('blocked-ips/{id}', 'BlockedIpController@getIps');
        $router->post('block-ip', 'BlockedIpController@blockIpAddress');
        $router->post('unblock-ip', 'BlockedIpController@deleteIpAddress');
        //Backup Routes
        $router->get('return-backup-files/{id}', 'BackUpController@getBackupFiles');
        $router->post('create-image', 'CpanelController@createImage');
        //Terminate cPanel Account
        $router->get('delete-account/{id}', 'CpanelController@deleteAccount');
    // });
    
	$router->group(['prefix' => 'delegate-access', 'namespace' => 'delegate', 'middleware'=> ['delegateAccess', 'cors']], function () use ($router) {
        //Email Account Routes emailAccess
        $router->group(['middleware'=> ['cpanelAccess']], function () use ($router) {
            $router->get('login-cpanel/{id}', 'CpanelController@loginAccount'); 
            $router->post('login-email-account', 'EmailAccountController@loginEmailAccount');
            $router->get('login-phpmyadmin/{id}', 'MySqlController@loginPHPMYADMIN');
        });
        //Sub Domains Routes
        $router->group(['prefix' => 'subdomain', 'middleware'=> ['subdomainAccess']], function () use ($router) {
            $router->get('/{id}', 'SubDomainController@getSubDomain');
            $router->post('add', 'SubDomainController@addSubDomain');
            $router->post('update', 'SubDomainController@updateSubDomain');
            $router->post('delete', 'SubDomainController@deleteSubDomain');
        });
        //Addon Domains Routes
        $router->group(['prefix' => 'addon-domain', 'middleware'=> ['addonDomainAccess']], function () use ($router) {
            $router->get('/{id}', 'AddonDomainController@getAddonsDomain');
            $router->post('add', 'AddonDomainController@addAddonsDomain');
            $router->post('delete', 'AddonDomainController@deleteAddonsDomain');
        });
        $router->group(['middleware'=> ['emailAccess']], function () use ($router) {
            $router->get('email-accounts/{id}', 'EmailAccountController@getEmailAccount');
            $router->post('email-client-settings', 'EmailAccountController@emailSetting');
            $router->post('create-email-accounts', 'EmailAccountController@addEmailAccount');
            $router->post('update-email-accounts-password', 'EmailAccountController@updateEmailPasswrod');
            $router->post('delete-email-accounts', 'EmailAccountController@deleteEmailAccount');
            $router->post('suspend-email-account-login', 'EmailAccountController@suspendLogin');
            $router->post('unsuspend-email-account-login', 'EmailAccountController@unsuspendLogin');
            $router->post('unsuspend-email-account-incoming', 'EmailAccountController@unsuspendIncoming');
            $router->post('suspend-email-account-incoming', 'EmailAccountController@suspendIncoming');
        });
        //PHP ini Routes
        $router->group(['middleware'=> ['phpversionsAccess']], function () use ($router) {
            $router->post('php-ini-content', 'PhpIniController@getPhpIni');
            $router->post('update-php-ini-content', 'PhpIniController@updatePhpIni');
            $router->get('get-php-versions/{id}', 'PhpIniController@getVersion');
            $router->post('update-php-version', 'PhpIniController@updateVersion');
            $router->post('get-php-directives', 'PhpIniController@getDirectives');
            $router->post('update-php-directive', 'PhpIniController@updateDirectives');
        });
        //FTP Account Routes
        $router->group(['middleware'=> ['ftpAccess']], function () use ($router) {
            $router->get('ftp-accounts/{id}', 'FtpAccountController@getFtpAccount');
            $router->post('ftp-server', 'FtpAccountController@getFtpServerInfo');
            $router->post('create-ftp-accounts', 'FtpAccountController@addFtpAccount');
            $router->post('update-ftp-accounts', 'FtpAccountController@updateFtpAccount');
            $router->post('delete-ftp-accounts', 'FtpAccountController@deleteFtpAccount');
        });
        //MySql User RoutesRestrictions
        $router->group(['middleware'=> ['mysqlAccess']], function () use ($router) {
            $router->get('mysql-listing/{id}', 'MySqlController@getListing');
            $router->get('mysql-users/{id}', 'MySqlController@getUsers');
            $router->get('mysql-name-restrictions/{id}', 'MySqlController@getUsersRestrictions');
            $router->post('create-mysql-user', 'MySqlController@addUser');
            $router->post('update-mysql-user', 'MySqlController@updateUser');
            $router->post('update-mysql-user-password', 'MySqlController@updateUserPassword');
            $router->post('delete-mysql-user', 'MySqlController@deleteUser');
        });
        //MySql Databases Routes
        $router->group(['middleware'=> ['databaseAccess']], function () use ($router) {
            $router->get('mysql-databases/{id}', 'MySqlDbController@getDatabases');
            $router->post('create-mysql-database', 'MySqlDbController@addDatabase');
            $router->post('update-mysql-database', 'MySqlDbController@updateDatabase');
            $router->post('delete-mysql-database', 'MySqlDbController@deleteDatabase');
            $router->post('remove-mysql-privileges', 'MySqlDbController@removePrivileges');
            $router->post('get-mysql-privileges', 'MySqlDbController@getPrivileges');
            $router->post('update-mysql-privileges', 'MySqlDbController@updatePrivileges');
        });
        //Get Domain Info Route
        $router->group(['middleware'=> ['infoAccess']], function () use ($router) {
            $router->get('domain-info/{id}', 'CpanelController@getUserInfo');  
        }); 
        $router->group(['middleware'=> ['DnsAccess']], function () use ($router) {
            $router->get('domain-detail/{id}', 'CpanelController@dnsInfo');
        }); 
        $router->get('domain-list/{id}', 'CpanelController@getDomains');
        //Blocked Ip Routes
        $router->group(['middleware'=> ['ipAccess']], function () use ($router) {
            $router->get('blocked-ips/{id}', 'BlockedIpController@getIps');
            $router->post('block-ip', 'BlockedIpController@blockIpAddress');
            $router->post('unblock-ip', 'BlockedIpController@deleteIpAddress'); 
        });
        $router->get('mysql-privileges', 'MySqlController@getPrivileges');
        $router->get('user/servers[/{id}]', 'CpanelController@orderedServers'); 
        $router->group(['prefix' => 'plesk', 'namespace' => 'plesk' , 'middleware'=> ['pleskvalidation'] ] ,  function () use ($router) {
            $router->post('get-plans', 'DomainController@getPlans');
            //Manage domain(Webspaces)
            $router->post('domain-info', 'DomainController@getDomain');
            $router->group(['prefix'=>'domain'],function() use ($router){
                $router->post('get-all', 'DomainController@getAll');
                $router->post('get-detail', 'DomainController@getDomain');
                $router->post('delete', 'DomainController@delete');
                $router->post('create', 'DomainController@create');
                $router->group(['middleware'=> ['subdomainAccess']],function() use ($router){
            
                    $router->post('create-subdomain','DomainController@createSubdomain');
                    $router->post('get-subdomain','DomainController@subDomainDetail');
                    $router->post('all-subdomains','DomainController@subDomains');
                    $router->post('delete-subdomain','DomainController@deleteSubDomain');
                });
        
                $router->group(['middleware'=> ['cpanelAccess']], function () use ($router) {
                    $router->post('login-session','DomainController@loginSession');
                });
            });
            //Manage databases
            $router->group(['prefix'=>'database', 'middleware'=> ['databaseAccess']],function() use ($router){
                $router->post('create','DatabaseController@create');
                $router->post('detail[/{database_id}]','DatabaseController@detail');
                $router->post('delete/{database_id}','DatabaseController@delete');
                $router->post('create-user','DatabaseController@createUser');
                $router->post('user-detail[/{user_id}]','DatabaseController@userDetail');
                $router->post('delete-user','DatabaseController@deleteUser');
                $router->post('change-user-settings','DatabaseController@updateUser');
            });
            $router->post('mysql-privileges', 'DatabaseController@getPrivileges');
            //Manage FTPs
            $router->group(['prefix'=>'ftp-account', 'middleware'=> ['ftpAccess']],function() use ($router){
                $router->post('create','FtpAccountController@create');
                $router->post('detail[/{ftp_id}]','FtpAccountController@detail');
                $router->post('update','FtpAccountController@update');
                $router->post('delete','FtpAccountController@delete');
            });
            //Manage Emails
            $router->group(['prefix'=>'email-account', 'middleware'=> ['emailAccess']],function() use ($router){
                $router->post('create','EmailAccountController@create');
                $router->post('detail','EmailAccountController@detail');
                $router->post('update','EmailAccountController@update');
                $router->post('delete','EmailAccountController@delete');
            });
           
        });
    });
    $router->group(['prefix' => 'plesk', 'namespace' => 'plesk' , 'middleware'=> ['pleskvalidation'] ] ,  function () use ($router) {
        $router->post('get-plans', 'DomainController@getPlans');
        //Manage domain(Webspaces)
        $router->post('domain-info', 'DomainController@getDomain');
        $router->group(['prefix'=>'domain'],function() use ($router){
            $router->post('get-all', 'DomainController@getAll');
            $router->post('get-detail', 'DomainController@getDomain');
            $router->post('delete', 'DomainController@delete');
            $router->post('create', 'DomainController@create');
    
            $router->post('create-subdomain','DomainController@createSubdomain');
            $router->post('update-subdomain','DomainController@updateSubdomain');
            $router->post('all-subdomains','DomainController@subDomains');
            $router->post('delete-subdomain','DomainController@deleteSubDomain');
    
            $router->post('login-session','DomainController@loginSession');
        });
        //Manage databases
        $router->group(['prefix'=>'database'],function() use ($router){
            $router->post('create','DatabaseController@create');
            $router->post('detail','DatabaseController@detail');
            $router->post('delete','DatabaseController@delete');
            $router->post('create-user','DatabaseController@createUser');
            $router->post('user-detail[/{user_id}]','DatabaseController@userDetail');
            $router->post('delete-user','DatabaseController@deleteUser');
            $router->post('change-user-settings','DatabaseController@updateUser');
        });
        $router->post('mysql-privileges', 'DatabaseController@getPrivileges');
        //Manage FTPs
        $router->group(['prefix'=>'ftp-account'],function() use ($router){
            $router->post('create','FtpAccountController@create');
            $router->post('detail[/{ftp_id}]','FtpAccountController@detail');
            $router->post('update','FtpAccountController@update');
            $router->post('delete','FtpAccountController@delete');
        });
        //Manage Emails
        $router->group(['prefix'=>'email-account'],function() use ($router){
            $router->post('create','EmailAccountController@create');
            $router->post('detail','EmailAccountController@detail');
            $router->post('update','EmailAccountController@update');
            $router->post('delete','EmailAccountController@delete');
        });
       
    });
});