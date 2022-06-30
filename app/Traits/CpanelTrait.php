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
    public function runQuery($id, $action, $arguments = [], $throw = false, $csf = null, $moduleName = null)
    {
        $userId = request()->userid;
        $records = CompanyServer::where('id', $id)->first();
        if(is_null($records))
            return config('constants.ERROR.FORBIDDEN_ERROR');
        $linkserver = $records->link_server ? unserialize($records->link_server) : 'N/A';
        if('N/A' != $linkserver){
            
            $hostUrl = 'https://'.$records->ip_address.':'.$linkserver['port'];
            $headers = ['Authorization' => 'WHM ' . $linkserver['username'] . ':' . preg_replace("'(\r|\n|\s|\t)'", '', $linkserver['apiToken'])];
            $client = new Client(['base_uri' => $hostUrl]);
            try{
                if($csf){
                    $response = $client->post('/' . $action, [
                        'headers' => $headers,
                        'verify' => false,
                        'query' => $arguments,
                        'timeout' => 60,
                        'connect_timeout' => 2
                    ]);
                }
                else
                $response = $client->post('/json-api/' . $action, [
                    'headers' => $headers,
                    'verify' => false,
                    'query' => $arguments,
                    'timeout' => 60,
                    'connect_timeout' => 2
                ]);
                if (($decodedBody = json_decode($response->getBody(), true)) === false) {
                    
                    $dataArray = ['userId' => jsencode_userdata($userId), 'logType' => 'cPanel', 'requestURL' => $hostUrl, 'module' => $moduleName, 'response' => json_last_error_msg()];
                    $response1 = hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $dataArray);
                    throw new \Exception(json_last_error_msg(), json_last_error());
                }
                $dataArray = ['userId' => jsencode_userdata($userId), 'logType' => 'cPanel', 'requestURL' => $hostUrl, 'module' => $moduleName, 'response' => $decodedBody];
                $response1 = hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $dataArray);
                return $decodedBody;
            }
            catch(\GuzzleHttp\Exception\ClientException $e)
            {
                if ($throw) {
                    throw $e; 
                }
                $dataArray = ['userId' => jsencode_userdata($userId), 'logType' => 'cPanel', 'requestURL' => $hostUrl, 'module' => $moduleName, 'response' => $e->getMessage()];
                $response1 = hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $dataArray);
                return $e->getMessage();
            }
        }
        
        $dataArray = ['userId' => jsencode_userdata($userId), 'logType' => 'cPanel', 'requestURL' => $records->host.'('.$records->ip_address.')', 'module' => $moduleName, 'response' => 'Linked server connection test failed'];
        $response1 = hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $dataArray);
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
            $testConnection = $this->runQuery($id, 'version', [], false,  null, 'Test Connection');
            if(is_array($testConnection) && array_key_exists("version", $testConnection)){
                return response()->json(['api_response' => 'success', 'status_code' => 200, 'message' => 'Connection success'], 200);
            }
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed'], 400);
        } 
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()], 400);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()], 400);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()], 400);
        }
    }
    /* End Method addDevice */ 
    
    /*
    API Method Name:    analogStats
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of subdomains
    */
    public function analogStats($id, $username, $domain)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Stats',
            'cpanel_jsonapi_func' => 'list_stats_by_domain',
            'cpanel_jsonapi_user' => $username,
            'domain' => $domain,
            'engine' => 'analog'
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'List Stats By Domain');
    }
    /* End Method analogStats */ 
    /*
    API Method Name:    installCertificate
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of subdomains
    */
    public function installCertificate($id, $username, $domain, $cert, $key, $cabundle = null)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'SSL',
            'cpanel_jsonapi_func' => 'install_ssl',
            'cpanel_jsonapi_user' => $username,
            'domain' => $domain,
            'cert' => $cert,
            'key' => $key
        ];
        if($cabundle)
        $params['cabundle'] = $cabundle;
        return $this->runQuery($id, $action, $params, false,  null, 'Install Certificate');
    }
    /* End Method installCertificate */ 
    
    /*
    API Method Name:    getSubDomains
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of subdomains
    */
    public function getSubDomains($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'SubDomain',
            'cpanel_jsonapi_func' => 'listsubdomains',
            'cpanel_jsonapi_user' => $username,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'SubDomain List');
    }
    /* End Method getSubDomains */ 

    /*
    API Method Name:    terminateCpanelAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To terminate a cpanel account
    */
    public function terminateCpanelAccount($id, $username)
    {
        return $this->runQuery($id, 'removeacct', ['api.version' => '1', 'username' => $username], false,  null, 'Terminate Cpanel Account');
    }
    /* End Method terminateCpanelAccount */ 

    /*
    API Method Name:    createSubDomain
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new subdomain
    */
    public function createSubDomain($id, $domain, $homedir)
    {
        return $this->runQuery($id, 'create_subdomain', ['api.version' => '1', 'domain' => $domain, 'document_root' => $homedir], false,  null, 'Create SubDomain');
    }
    /* End Method createSubDomain */ 
    
    /*
    API Method Name:    changeRootDir
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To update subdomain root directory
    */
    public function changeRootDir($id, $username, $domain, $subdomain, $homedir)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'SubDomain',
            'cpanel_jsonapi_func' => 'changedocroot',
            'cpanel_jsonapi_user' => $username,
            'rootdomain' => $domain,
            'subdomain' => $subdomain,
            'dir' => $homedir
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Change SubDomain Root Dir');
    }
    /* End Method changeRootDir */ 
    
    /*
    API Method Name:    delSubDomain
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete a subdomain
    */
    public function delSubDomain($id, $username, $domain)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'SubDomain',
            'cpanel_jsonapi_func' => 'delsubdomain',
            'cpanel_jsonapi_user' => $username,
            'domain' => $domain,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Delete SubDomain');
    }
    /* End Method delSubDomain */ 
    
    /*
    API Method Name:    getAddonsDomains
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of AddonsDomains
    */
    public function getAddonsDomains($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'AddonDomain',
            'cpanel_jsonapi_func' => 'listaddondomains',
            'cpanel_jsonapi_user' => $username,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Addons Domain List');
    }
    /* End Method getAddonsDomains */ 

    /*
    API Method Name:    createAddonsDomain
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new AddonsDomain
    */
    public function createAddonsDomain($id, $username, $domain, $subdomain, $homedir)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'AddonDomain',
            'cpanel_jsonapi_func' => 'addaddondomain',
            'cpanel_jsonapi_user' => $username,
            'newdomain' => $domain,
            'subdomain' => $subdomain,
            'dir' => $homedir
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Create Addons Domain');
    }
    /* End Method createAddonsDomain */ 
    
    /*
    API Method Name:    delAddonsDomain
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete a AddonsDomain
    */
    public function delAddonsDomain($id, $username, $domain, $subdomain)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'AddonDomain',
            'cpanel_jsonapi_func' => 'deladdondomain',
            'cpanel_jsonapi_user' => $username,
            'domain' => $domain,
            'subdomain' => $subdomain,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Delete Addons Domain');
    }
    /* End Method delAddonsDomain */ 
    
    
    /*
    API Method Name:    backupFiles
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            This function creates a full backup to the user's home directory
    */
    public function backupFiles($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Backup',
            'cpanel_jsonapi_func' => 'fullbackup_to_homedir',
            'cpanel_jsonapi_user' => $username,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Create Backup file');

    }
    /* End Method backupFiles */ 
    
    /*
    API Method Name:    listBackups
    Developer:          Shine Dezign
    Created Date:       2022-06-15 (yyyy-mm-dd)
    Purpose:            This function lists the account's backup files.
    */
    public function listBackups($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'Backups',
            'cpanel_jsonapi_func' => 'listfullbackups',
            'cpanel_jsonapi_user' => $username,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Backup List');
    }
    /* End Method listBackups */ 
    
    /*
    API Method Name:    loginPhpMyAdminAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To login phpmyadmin account
    */
    public function loginPhpMyAdminAccount($id, $username)
    {
        
        return $this->runQuery($id, 'create_user_session', ['api.version' => '1', 'user' => $username, 'service' => 'cpaneld', 'app' => 'Database_phpMyAdmin'], false,  null, 'Login PHPMYADMIN Account');
    }

    /* End Method loginPhpMyAdminAccount */ 
    /*
    API Method Name:    loginWebmail
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To login email account with webmail
    */
    public function loginWebmail($id, $username)
    {
        
        return $this->runQuery($id, 'create_user_session', ['api.version' => '1', 'user' => $username, 'service' => 'cpaneld', 'app' => 'Email_Accounts'], false,  null, 'Login Webmail Account');
    }
    /* End Method loginWebmail */ 

    /*
    API Method Name:    loginCpanelAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To login into cpanel account
    */
    public function loginCpanelAccount($id, $username)
    {
        
        return $this->runQuery($id, 'create_user_session', ['api.version' => '1', 'user' => $username, 'service' => 'cpaneld'], false,  null, 'Login Cpane Account');
    }
    /* End Method loginCpanelAccount */  
    
    /*
    API Method Name:    getBlockIp
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of black listed ip addresses
    */
    public function getBlockIp($id, $username)
    {
        return $this->runQuery($id, 'read_cphulk_records', ['api.version' => '1', 'user' => $username, 'list_name' => 'black'], false,  null, 'Block IP List');
    }
    /* End Method getBlockIp */ 
    
    /*
    API Method Name:    blockIp
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To black list any ip address
    */
    public function blockIp($id, $username, $ip) 
    {
        return $this->runQuery($id, 'create_cphulk_record', ['api.version' => '1', 'user' => $username, 'list_name' => 'black', 'ip' => $ip], false,  null, 'Block IP');
    }
    /* End Method blockIp */  
    
    /*
    API Method Name:    unblockIp
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To whitelist any ip address
    */
    public function unblockIp($id, $username, $ip)
    {
        return $this->runQuery($id, 'cgi/addon_csf.cgi', ['action' => 'kill', 'ip' => $ip], false, true, 'Unblock IP');
    }
    /* End Method unblockIp */
    
    /*
    API Method Name:    extractFile
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To extract file on cpanel
    */
    public function extractFile($id, $username, $dir)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_module' => 'Fileman',
            'cpanel_jsonapi_func' => 'fileop',
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_user' => $username,
            'doubledecode' => 0,
            'op' => 'extract',
            'sourcefiles' => '/public_html/wordpress.zip',
            'destfiles' => $dir
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Extract File');
    }
    /* End Method extractFile */
    
    /*
    API Method Name:    deleteCpanelFile
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete file from cpanel
    */
    public function deleteCpanelFile($id, $username, $file = '/public_html/wordpress.zip')
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_module' => 'Fileman',
            'cpanel_jsonapi_func' => 'fileop',
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_user' => $username,
            'op' => 'unlink',
            'sourcefiles' => $file,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Delete File');
    }
    /* End Method deleteCpanelFile */
    
    /*
    API Method Name:    uploadFile
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To upload new file on cpanel
    */
    public function uploadFile($id, $username, $dir, $contentText, $fileName)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Fileman',
            'cpanel_jsonapi_func' => 'save_file_content',
            'cpanel_jsonapi_user' => $username,
            'dir' => $dir,
            'file' => $fileName,
            "content" => $contentText
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Create File');
    }
    /* End Method uploadFile */
    
    /*
    API Method Name:    removeFile
    Developer:          Shine Dezign
    Created Date:       2022-06-15 (yyyy-mm-dd)
    Purpose:            To copy a file on cpanel
    */
    public function removeFile($id, $username, $fileName)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'Fileman',
            'cpanel_jsonapi_func' => 'fileop',
            'cpanel_jsonapi_user' => $username,
            'op' => 'trash',
            'sourcefiles' => $fileName,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Delete File');
    }
    /* End Method removeFile */
    
    /*
    API Method Name:    copyFile
    Developer:          Shine Dezign
    Created Date:       2022-06-15 (yyyy-mm-dd)
    Purpose:            To copy a file on cpanel
    */
    public function copyFile($id, $username, $fileName = 'backup-6.9.2022_06-55-39_pkkchemicals.tar.gz')
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'Fileman',
            'cpanel_jsonapi_func' => 'fileop',
            'cpanel_jsonapi_user' => $username,
            'op' => 'copy',
            'sourcefiles' => '/'.$fileName,
            'destfiles' => 'public_html/',
            'metadata' => '0755'
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Copy File');
    }
    /* End Method copyFile */
    /*
    API Method Name:    filePermission
    Developer:          Shine Dezign
    Created Date:       2022-06-15 (yyyy-mm-dd)
    Purpose:            To copy a file on cpanel
    */
    public function filePermission($id, $username, $fileName = 'backup-6.9.2022_06-55-39_pkkchemicals.tar.gz')
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'Fileman',
            'cpanel_jsonapi_func' => 'fileop',
            'cpanel_jsonapi_user' => $username,
            'op' => 'chmod',
            'sourcefiles' => 'public_html/'.$fileName,
            'metadata' => '0755'
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'File Permission');
    }
    /* End Method filePermission */
    
    /*
    API Method Name:    createAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new cpanel account
    */
    public function createAccount($id, $domain_name, $username, $password, $plan)
    {
        return $this->runQuery($id, 'createacct', [
            'api.version' => '1', 
            'username' => $username,
            'domain' => $domain_name,
            'password' => $password,
            'plan' => $plan,
        ], false,  null, 'Create Cpanel Account');
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
        return $this->runQuery($id, 'domainuserdata', ['api.version' => '1', 'domain' => $domain_name], false,  null, 'Domain Info');
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
        return $this->runQuery($id, 'php_get_installed_versions', ['api.version' => '1', 'domain' => $domain_name], false,  null, 'PHP Version');
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
        return $this->runQuery($id, 'php_get_system_default_version', ['api.version' => '1', 'domain' => $domain_name], false,  null, 'PHP Current Version');
    }
    /* End Method phpCurrentVersion */ 
    
    /*
    API Method Name:    phpIniGetDirectives
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get current version
    */
    public function phpIniGetDirectives($id, $domain_name, $version)
    {
        return $this->runQuery($id, 'php_ini_get_directives', ['api.version' => '1', 'domain' => $domain_name, 'version' => $version], false,  null, 'PHP INI Get Directives');
    }
    /* End Method phpIniGetDirectives */ 
    
    /*
    API Method Name:    phpIniUpdateDirectives
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get current version
    */
    public function phpIniUpdateDirectives($id, $domain_name, $version, $directive)
    {
        return $this->runQuery($id, 'php_ini_set_directives', ['api.version' => '1', 'domain' => $domain_name, 'version' => $version, 'directive' => $directive], false,  null, 'PHP INI Update Directives');
    }
    /* End Method phpIniUpdateDirectives */ 
    
    
    /*
    API Method Name:    getPhpIniFile
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get current version
    */
    public function getPhpIniFile($id, $domain_name, $version)
    {
        return $this->runQuery($id, 'php_ini_get_content', ['api.version' => '1', 'domain' => $domain_name, 'version' => $version], false,  null, 'Get PHP INI File');
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
        return $this->runQuery($id, 'php_ini_set_content', ['api.version' => '1', 'domain' => $domain_name, 'version' => $version, 'content' => $content], false,  null, 'Update PHP INI File');
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
        return $this->runQuery($id, 'php_set_system_default_version', ['api.version' => '1', 'domain' => $domain_name, 'version' => $version], false,  null, 'Update PHP Current Version');
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
        return $this->runQuery($id, 'get_nameserver_config', ['api.version' => '1', 'domain' => $domain_name], false,  null, 'Nameserver Info');
    }
    /* End Method domainNameServersInfo */ 
    
    /*
    API Method Name:    domainList
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get nameserver info for any domain
    */
    public function domainList($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'DomainInfo',
            'cpanel_jsonapi_func' => 'list_domains',
            'cpanel_jsonapi_user' => $username,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Domain List');
    }
    /* End Method domainList */ 
    
    /*
    API Method Name:    getCpanelStats
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of all mysql users
    */ 
    public function getCpanelStats($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'StatsBar',
            'cpanel_jsonapi_func' => 'get_stats',
            'cpanel_jsonapi_user' => $username,
            'display' => 'addondomains|bandwidthusage|cpanelversion|dedicatedip|diskusage|emailaccounts|ftpaccounts|hostname|mysqldatabases|mysqldiskusage|mysqlversion|subdomains|apacheversion|phpversion'
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Get Cpanel Stats');
    }
    /* End Method getCpanelStats */ 


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
        return $this->runQuery($id, $action, $params, false,  null, 'Get Server Info');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Get MySql Users');
    }
    /* End Method getMySqlUsers */ 
    
    /*
    API Method Name:    backUpMySqlDB
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of all mysql db backups
    */
    public function backUpMySqlDB($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'MysqlFE',
            'cpanel_jsonapi_func' => 'listdbsbackup',
            'cpanel_jsonapi_user' => $username, 
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'BackUp MySql DB');
    }
    /* End Method backUpMySqlDB */ 
    
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
        return $this->runQuery($id, $action, $params, false,  null, 'Get MySql User Restrictions');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Create MySql USer');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Set MySql User Password');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Rename MySql User');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Delete MySql DB');
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
        return $this->runQuery($id, $action, $params, false,  null, 'MySql DB list');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Create MySql DB');
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
        return $this->runQuery($id, $action, $params, false,  null, 'TRename MySql Database');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Get MySql Privileges');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Set MySql Privileges');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Remove MySql Privileges');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Delete MySql DB');
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
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'list_pops',
            'cpanel_jsonapi_user' => $username,
            "skip_main" => 1
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Email Account List');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Login Web Mail Account');
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
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'add_pop',
            'cpanel_jsonapi_user' => $username,
            'domain' => $domain,
            'email' => $account,
            'password' => $password,
            'quota' => $quota,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Create Email Account');
    }
    /* End Method createEmailAccount */ 
    
    /*
    API Method Name:    getClientSetting
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new email account
    */
    public function getClientSetting($id, $username, $email)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'get_client_settings',
            'cpanel_jsonapi_user' => $username,
            'account' => $email
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Get Client Settings');
    }
    /* End Method getClientSetting */ 
    
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
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'passwd_pop',
            'cpanel_jsonapi_user' => $username,
            'domain' => $domain,
            'email' => $account,
            'password' => $password,
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Change Email Password');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Suspend Email Login');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Unsuspend Email Login');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Unsuspend Email Incoming');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Suspend Email Incoming');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Delete Email Account');
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
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'list_ftp',
            'cpanel_jsonapi_user' => $username,
            'include_acct_types' => 'sub'
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'FTP Account List');
    }
    /* End Method listFtpAccounts */ 
    
    /*
    API Method Name:    checkFtpAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To check ftp account exist
    */
    public function checkFtpAccount($id, $username, $user)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'ftp_exists',
            'cpanel_jsonapi_user' => $username,
            "user" => $user
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Check FTP Account ');
    }
    /* End Method checkFtpAccount */ 
    
    /*
    API Method Name:    getFtpConfiguration
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new ftp account
    */
    public function getFtpConfiguration($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'get_ftp_daemon_info',
            'cpanel_jsonapi_user' => $username
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Get FTP Config');
    }
    /* End Method getFtpConfiguration */ 
    
    /*
    API Method Name:    getFtpPort
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new ftp account
    */
    public function getFtpPort($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'get_port',
            'cpanel_jsonapi_user' => $username
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'FTP Account List');
    }
    /* End Method getFtpPort */ 
    
    /*
    API Method Name:    createFtpAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To create new ftp account
    */
    public function createFtpAccount($id, $username, $user, $password, $quota, $homedir = null)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'add_ftp',
            'cpanel_jsonapi_user' => $username,
            "user" => $user,
            "pass" => $password,
            "quota" => $quota,
            "homedir" => $homedir
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Create FTP Account');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Delete FTP Account');
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
        return $this->runQuery($id, $action, $params, false,  null, 'Change FTP Quota');
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
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'passwd',
            'cpanel_jsonapi_user' => $username,
            "user" => $user,
            "pass" => $password,
            "homedir" => "public_html"
        ];
        return $this->runQuery($id, $action, $params, false,  null, 'Change FTP Password');
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