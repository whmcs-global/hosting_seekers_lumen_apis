<?php
 
namespace App\Traits;
use Tymon\JWTAuth\Facades\JWTAuth;
Use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;
use hisorange\BrowserDetect\Parser;

use Illuminate\Support\Facades\Crypt;
trait CommonTrait {
    public function updateLastLogin($id = NULL) {
        $browser = new Parser(null, null, [
            'cache' => [
                'interval' => 86400 // This will overide the default configuration.
            ]
        ]);
        if($id){
            $lastLoginData = serialize([
                'ip_address' => $this->get_ClientIp(),
                'Browser Name' => $browser->browserName(),
                'Operating System' => $browser->platformName(),
                'Agent' => $browser->userAgent(),
            ]);
            $services = User::where('id', $id)
            ->update(['last_login' =>  date('Y-m-d H:i:s'), 'last_login_data' => $lastLoginData]);
        }
    }
    public function billingCycleName($id) {
        $billingCycle =  ['Monthly', 'Quarterly', 'SemiAnnually', 'Annually', 'Onetime', 'Trial'];
 		return $billingCycle[$id];
    }
    public function get_ClientIp() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
    
    public function domainDetail($domain = NULL, $userId = null){
        if($domain){
            try{
                $response = hitCurl('https://api.promptapi.com/whois/query?apikey=H7jEwg0T1VTqd9SqURuyLq0eYeyThSbz&domain='.$domain, 'GET', '', array('Content-Type: text/plain'));
                
                $dataArray = ['userId' => jsencode_userdata($userId), 'logType' => 'cPanel', 'requestURL' => 'https://api.promptapi.com/whois/query?apikey=H7jEwg0T1VTqd9SqURuyLq0eYeyThSbz&domain='.$domain, 'module' => 'Domain Check', 'response' => $response];
                $response1 = hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $dataArray);
                $domainInfo = (array)json_decode(json_decode(json_encode($response, true)));
                if($domainInfo['result'] == 'error')
                {
                    if($domainInfo['message'] == 'TLD not supported'){
                        return $domainInfo['message'];
                    }
                    return FALSE;
                }
                $domainArray = (array) $domainInfo['result'];

                if(!array_key_exists('creation_date', $domainArray ))
                return FALSE;
            } catch ( \Exception $e ) {
                return FALSE;
            }
            return $domainArray;
        }
        else{
            return FALSE;
        }
    }
}