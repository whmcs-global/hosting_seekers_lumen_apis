<?php
 
namespace App\Traits;
use GuzzleHttp\Client;
use App\Models\{Order, OrderTransaction, CompanyServerPackage, UserServer, CompanyServer};
 
trait CpanelTrait {
    

    /*
    API Method Name:    runQuery
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To hit cpanel api with specific action
    */
    public function runQuery($id, $action, $arguments = [], $throw = false)
    {
        $records = CompanyServer::where('id', $id)->first();
        if(is_null($records))
            return config('constants.ERROR.FORBIDDEN_ERROR');
        
        $linkserver = $records->link_server ? unserialize($records->link_server) : 'N/A';
        if('N/A' != $linkserver){
            $hostUrl = 'https://'.$records->ip_address.':'.$linkserver['port'];
            $headers = ['Authorization' => 'WHM ' . $linkserver['username'] . ':' . preg_replace("'(\r|\n|\s|\t)'", '', $linkserver['apiToken'])];
            $client = new Client(['base_uri' => $hostUrl]);
            try{
                $response = $client->post('/json-api/' . $action, [
                    'headers' => $headers,
                    'verify' => false,
                    'query' => $arguments,
                    'timeout' => 60,
                    'connect_timeout' => 2
                ]);

                if (($decodedBody = json_decode($response->getBody(), true)) === false) {
                    throw new \Exception(json_last_error_msg(), json_last_error());
                }

                return $decodedBody;
            }
            catch(\GuzzleHttp\Exception\ClientException $e)
            {
            if ($throw) {
                throw $e; 
            }
            return $e->getMessage();
            }
        }
        return 'Linked server connection test failed';
    }
    /* End Method addDevice */ 
    
    /*
    API Method Name:    testServerConnection
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            Test server conction using host, port and credentials
    */
    public function testServerConnection($id) {
        try
        {
            $testConnection = $this->runQuery($id, 'version');
            if(is_array($testConnection) && array_key_exists("version", $testConnection)){
                return response()->json(['api_response' => 'success', 'status_code' => 200, 'message' => 'Connection success'], 200);
            }
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed'], 400);
        } 
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed'], 400);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout'], 400);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence'], 400);
        }
    }
    /* End Method addDevice */ 
    
    /*
    API Method Name:    createAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new cpanel account
    */
    public function createAccount($id, $domain_name, $username, $password, $plan)
    {
        return $this->runQuery($id, 'createacct', [
            'username' => $username,
            'domain' => $domain_name,
            'password' => $password,
            'plan' => $plan,
        ]);
    }
    /* End Method createAccount */ 
    
    /*
    API Method Name:    domainInfo
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get server information od any domain
    */
    public function domainInfo($id, $domain_name)
    {
        return $this->runQuery($id, 'domainuserdata', ['api.version' => '1', 'domain' => $domain_name]);
    }
    /* End Method domainInfo */ 
    
    /*
    API Method Name:    phpVersions
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get php versions
    */
    public function phpVersions($id, $domain_name)
    {
        return $this->runQuery($id, 'php_get_installed_versions', ['api.version' => '1', 'domain' => $domain_name]);
    }
    /* End Method phpVersions */ 
    
    /*
    API Method Name:    phpCurrentVersion
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get current version
    */
    public function phpCurrentVersion($id, $domain_name)
    {
        return $this->runQuery($id, 'php_get_system_default_version', ['api.version' => '1', 'domain' => $domain_name]);
    }
    /* End Method phpCurrentVersion */ 
    
    /*
    API Method Name:    getPhpIniFile
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get current version
    */
    public function getPhpIniFile($id, $domain_name, $version)
    {
        return $this->runQuery($id, 'php_ini_get_content', ['api.version' => '1', 'domain' => $domain_name, 'version' => $version]);
    }
    /* End Method getPhpIniFile */ 
    
    /*
    API Method Name:    updatePhpIniFile
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get current version
    */
    public function updatePhpIniFile($id, $domain_name, $version, $content)
    {
        return $this->runQuery($id, 'php_ini_set_content', ['api.version' => '1', 'domain' => $domain_name, 'version' => $version, 'content' => $content]);
    }
    /* End Method updatePhpIniFile */ 
    
    /*
    API Method Name:    updatePhpCurrentVersion
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get current version
    */
    public function updatePhpCurrentVersion($id, $domain_name, $version)
    {
        return $this->runQuery($id, 'php_set_system_default_version', ['api.version' => '1', 'domain' => $domain_name, 'version' => $version]);
    }
    /* End Method updatePhpCurrentVersion */ 
    
    /*
    API Method Name:    domainNameServersInfo
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get nameserver info for any domain
    */
    public function domainNameServersInfo($id, $domain_name)
    {
        return $this->runQuery($id, 'get_nameserver_config', ['api.version' => '1', 'domain' => $domain_name]);
    }
    /* End Method domainNameServersInfo */ 
    
    /*
    API Method Name:    getServerInfo
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of all mysql users
    */
    public function getServerInfo($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'ServerInformation',
            'cpanel_jsonapi_func' => 'get_information',
            'cpanel_jsonapi_user' => $username,
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method getServerInfo */ 
    
    /*
    API Method Name:    getMySqlUsers
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of all mysql users
    */
    public function getMySqlUsers($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'list_users',
            'cpanel_jsonapi_user' => $username,
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method getMySqlUsers */ 
    
    /*
    API Method Name:    getMySqlUserRestrictions
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get mysql name restrictions
    */
    public function getMySqlUserRestrictions($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'get_restrictions',
            'cpanel_jsonapi_user' => $username,
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method getMySqlUserRestrictions */ 
    
    /*
    API Method Name:    createMySqlUser
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new mysql user
    */
    public function createMySqlUser($id, $username, $userName, $password)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'create_user',
            'cpanel_jsonapi_user' => $username,
            'name' => $userName,
            'password' => $password
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method createMySqlUser */ 
    
    /*
    API Method Name:    updateMySqlUserPassword
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To update mysql user password
    */
    public function updateMySqlUserPassword($id, $username, $userName, $password)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'set_password',
            'cpanel_jsonapi_user' => $username,
            'user' => $userName,
            'password' => $password
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method updateMySqlUserPassword */ 
    
    /*
    API Method Name:    updateMySqlUser
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To rename mysql user name
    */
    public function updateMySqlUser($id, $username, $userName, $oldName)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'rename_user',
            'cpanel_jsonapi_user' => $username,
            'newname' => $userName,
            'oldname' => $oldName
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method updateMySqlUser */ 
    
    /*
    API Method Name:    deleteMySqlUser
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete mysql user
    */
    public function deleteMySqlUser($id, $username, $user)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'delete_user',
            'cpanel_jsonapi_user' => $username,
            "name" => $user
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method deleteMySqlUser */ 
    
    /*
    API Method Name:    getMySqlDb
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of all mysql database
    */
    public function getMySqlDb($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'list_databases',
            'cpanel_jsonapi_user' => $username,
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method getMySqlDb */ 
    
    /*
    API Method Name:    createMySqlDb
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new nysql database
    */
    public function createMySqlDb($id, $username, $dbName)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'create_database',
            'cpanel_jsonapi_user' => $username,
            'name' => $dbName
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method createMySqlDb */ 
    
    /*
    API Method Name:    updateMySqlDb
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To rename mysql database
    */
    public function updateMySqlDb($id, $username, $userName, $oldName)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'rename_database',
            'cpanel_jsonapi_user' => $username,
            'newname' => $userName,
            'oldname' => $oldName
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method updateMySqlDb */ 
    
    /*
    API Method Name:    getMySqlDbPrivileges
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get privileges of database and user
    */
    public function getMySqlDbPrivileges($id, $username, $dbName, $userName)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'get_privileges_on_database',
            'cpanel_jsonapi_user' => $username,
            'database' => $dbName,
            'user' => $userName
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method getMySqlDbPrivileges */ 
    
    /*
    API Method Name:    updateMySqlDbPrivileges
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To update privileges of database and user
    */
    public function updateMySqlDbPrivileges($id, $username, $dbName, $userName, $privileges)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'set_privileges_on_database',
            'cpanel_jsonapi_user' => $username,
            'database' => $dbName,
            'user' => $userName,
            'privileges' => $privileges
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method updateMySqlDbPrivileges */ 
    
    /*
    API Method Name:    removeMySqlDbPrivileges
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To revoke privileges from database
    */
    public function removeMySqlDbPrivileges($id, $username, $dbName, $userName)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'revoke_access_to_database',
            'cpanel_jsonapi_user' => $username,
            'database' => $dbName,
            'user' => $userName,
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method removeMySqlDbPrivileges */ 
    
    /*
    API Method Name:    deleteMySqlDb
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete mysql database
    */
    public function deleteMySqlDb($id, $username, $user)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'delete_database',
            'cpanel_jsonapi_user' => $username,
            "name" => $user
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method deleteMySqlDb */ 
    
    /*
    API Method Name:    listEmailAccounts
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of created email accounts
    */
    public function listEmailAccounts($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 2,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'listpops',
            'cpanel_jsonapi_user' => $username,
            "skip_main" => 1
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method listEmailAccounts */ 
    
    /*
    API Method Name:    loginEmailAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new email account
    */
    public function loginWebEmailAccount($id, $username, $email, $domainName)
    {
        list($account, $domain) = explode('@', $email);

        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Session',
            'cpanel_jsonapi_func' => 'create_webmail_session_for_mail_user',
            'cpanel_jsonapi_user' => $username,
            'domain' => $domainName,
            'login' => $account,
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method loginEmailAccount */ 
    
    /*
    API Method Name:    createEmailAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new email account
    */
    public function createEmailAccount($id, $username, $email, $password, $quota)
    {
        list($account, $domain) = explode('@', $email);

        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 2,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'addpop',
            'cpanel_jsonapi_user' => $username,
            'domain' => $domain,
            'email' => $account,
            'password' => $password,
            'quota' => $quota,
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method createEmailAccount */ 
    
    /*
    API Method Name:    changeEmailPassword
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To update email password
    */
    public function changeEmailPassword($id, $username, $email, $password)
    {
        list($account, $domain) = explode('@', $email);

        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 2,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'passwdpop',
            'cpanel_jsonapi_user' => $username,
            'domain' => $domain,
            'email' => $account,
            'password' => $password,
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method changeEmailPassword */ 
    
    /*
    API Method Name:    suspendEmailsLogin
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete email account
    */
    public function suspendEmailsLogin($id, $username, $user)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'suspend_login',
            'cpanel_jsonapi_user' => $username,
            "email" => $user
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method suspendEmailsLogin */
    
    /*
    API Method Name:    unsuspendEmailsLogin
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete email account
    */
    public function unsuspendEmailsLogin($id, $username, $user)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'unsuspend_login',
            'cpanel_jsonapi_user' => $username,
            "email" => $user
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method unsuspendEmailsLogin */
    
    /*
    API Method Name:    unsuspendEmailsIncoming
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete email account
    */
    public function unsuspendEmailsIncoming($id, $username, $user)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'unsuspend_incoming',
            'cpanel_jsonapi_user' => $username,
            "email" => $user
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method unsuspendEmailsIncoming */
    
    /*
    API Method Name:    suspendEmailsIncoming
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete email account
    */
    public function suspendEmailsIncoming($id, $username, $user)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'suspend_incoming',
            'cpanel_jsonapi_user' => $username,
            "email" => $user
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method suspendEmailsIncoming */
    
    /*
    API Method Name:    deleteEmailsAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete email account
    */
    public function deleteEmailsAccount($id, $username, $user)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'delete_pop',
            'cpanel_jsonapi_user' => $username,
            "email" => $user
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method deleteEmailsAccount */ 
    
    /*
    API Method Name:    listFtpAccounts
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of created ftp accounts
    */
    public function listFtpAccounts($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'listftp',
            'cpanel_jsonapi_user' => $username,
            'include_acct_types' => 'sub'
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method listFtpAccounts */ 
    
    /*
    API Method Name:    createFtpAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new ftp account
    */
    public function createFtpAccount($id, $username, $user, $password, $quota)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'addftp',
            'cpanel_jsonapi_user' => $username,
            "user" => $user,
            "pass" => $password,
            "quota" => $quota,
            "homedir" => "public_html"
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method createFtpAccount */ 
    
    /*
    API Method Name:    deleteFtpUser
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete ftp account
    */
    public function deleteFtpUser($id, $username, $user)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'delete_ftp',
            'cpanel_jsonapi_user' => $username,
            "user" => $user,
            "destroy" => 1
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method deleteFtpUser */ 
    
    /*
    API Method Name:    changeFtpQuota
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To update quota of the ftp account
    */
    public function changeFtpQuota($id, $username, $user, $quota)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'setquota',
            'cpanel_jsonapi_user' => $username,
            "user" => $user,
            "quota" => $quota,
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method changeFtpQuota */ 
    
    /*
    API Method Name:    changeFtpPassword
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To chnage password of the ftp account
    */
    public function changeFtpPassword($id, $username, $user, $password)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'passwd',
            'cpanel_jsonapi_user' => $username,
            "user" => $user,
            "pass" => $password,
            "homedir" => "public_html"
        ];
        return $this->runQuery($id, $action, $params);
    }
    /* End Method changeFtpPassword */ 
    
    /*
    API Method Name:    getDomain
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get domain from url
    */
    public function getDomain($url)
    {
      $pieces = parse_url($url);
      $domain = isset($pieces['host']) ? $pieces['host'] : $pieces['path'];
      if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
        return $regs['domain'];
      }
      return false;
    }
    /* End Method getDomain */ 
    
    /*
    API Method Name:    splitEmail
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            Split email to get domain name and starting name
    */
    private function splitEmail($email)
    {
        $email_parts = explode('@', $email);
        if (count($email_parts) !== 2) {
            throw new \Exception("Email account is not valid.");
        }

        return $email_parts;
    }
    /* End Method splitEmail */ 
 
}