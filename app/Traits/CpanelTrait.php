<?php
 
namespace App\Traits;

use App\Models\{CompanyServer};
 
trait CpanelTrait {
    

    public function testServerConnection($id) {
        try
        {
            $records = CompanyServer::where('id', $id)->first();
            if(is_null($records))
                return response()->json(['api_response' => 'error', 'status' => 400, 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR'), 'data' => 'Connection error'], 400);
            
            $linkserver = $records->link_server ? unserialize($records->link_server) : 'N/A';
            if('N/A' != $linkserver){
                $cpanel = new \Gufy\CpanelPhp\Cpanel([
                    'host'        =>  'https://'.$records->ip_address.':'.$linkserver['port'],
                    'username'    =>  $linkserver['username'],
                    'auth_type'   =>  'hash',
                    'password'    =>  $linkserver['apiToken'],
                ]);
                $testConnection = $cpanel->version();
                if(is_array($testConnection) && array_key_exists("version", $testConnection)){
                    return response()->json(['api_response' => 'success', 'status_code' => 200, 'message' => 'Connection success', 'cpanel' => $cpanel], 200);
                }
                else
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed'], 400);
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
    
    public function getDomain($url)
    {
      $pieces = parse_url($url);
      $domain = isset($pieces['host']) ? $pieces['host'] : $pieces['path'];
      if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
        return $regs['domain'];
      }
      return false;
    }
 
}