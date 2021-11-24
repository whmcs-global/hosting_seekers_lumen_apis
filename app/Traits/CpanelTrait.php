<?php
 
namespace App\Traits;
use GuzzleHttp\Client;


use App\Models\{Order, OrderTransaction, CompanyServerPackage, UserServer, CompanyServer};
 
trait CpanelTrait {
    

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
                    'timeout' => 50,
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
    public function createAccount($id, $domain_name, $username, $password, $plan)
    {
        return $this->runQuery($id, 'createacct', [
            'username' => $username,
            'domain' => $domain_name,
            'password' => $password,
            'plan' => $plan,
        ]);
    }

    public function domainInfo($id, $domain_name)
    {
        return $this->runQuery($id, 'domainuserdata', ['api.version' => '1', 'domain' => $domain_name]);
    }

    public function listEmailAccounts($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 2,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'listpops',
            'cpanel_jsonapi_user' => $username,
        ];
        return $this->runQuery($id, $action, $params);
    }
    
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
    
    public function deleteEmailsAccount($id, $username, $user)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'deletepop',
            'cpanel_jsonapi_user' => $username,
            "user" => $user
        ];
        return $this->runQuery($id, $action, $params);
    }
    
    public function changeEmailQuota($id, $username, $user, $quota)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 3,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'editquotapop',
            'cpanel_jsonapi_user' => $username,
            "user" => $user,
            "quota" => $quota,
        ];
        return $this->runQuery($id, $action, $params);
    }

    public function listFtpAccounts($id, $username)
    {
        $action = 'cpanel';
        $params = [
            'cpanel_jsonapi_version' => 3,
            'cpanel_jsonapi_module' => 'Ftp',
            'cpanel_jsonapi_func' => 'listftp',
            'cpanel_jsonapi_user' => $username,
            'include_acct_types' => 'main|sub'
        ];
        return $this->runQuery($id, $action, $params);
    }
    
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
    
    public function getDomain($url)
    {
      $pieces = parse_url($url);
      $domain = isset($pieces['host']) ? $pieces['host'] : $pieces['path'];
      if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
        return $regs['domain'];
      }
      return false;
    }
    
    private function splitEmail($email)
    {
        $email_parts = explode('@', $email);
        if (count($email_parts) !== 2) {
            throw new \Exception("Email account is not valid.");
        }

        return $email_parts;
    }
 
}