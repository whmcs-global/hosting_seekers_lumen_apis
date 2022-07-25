<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{UserServer};
use Illuminate\Support\Facades\{Config, Validator};
use App\Traits\{CpanelTrait, AutoResponderTrait};
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class ReInstallController extends Controller
{
    use CpanelTrait, AutoResponderTrait;
    /*
    API Method Name:    reinstallCheck
    Developer:          Shine Dezign
    Created Date:       2022-07-12 (yyyy-mm-dd)
    Purpose:            Reinstall Wordpress on the server
    */
    public function reinstallCheck(Request $request, $id = null) {
        if(!$id){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Requesting error', 'message' => 'Server ID is required']);
        }
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Reinstall Wordpress'
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Install Wordpress',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            
            
            if(!$serverPackage->wordpress_detail){
                
                $publicHtml = $this->getFileCount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), 'public_html');
                if(is_array($publicHtml) && array_key_exists("cpanelresult", $publicHtml) && array_key_exists("data", $publicHtml['cpanelresult']) && is_numeric(array_search('wp-config.php', array_column($publicHtml['cpanelresult']['data'], 'file')))){
                    $errorArray = [
                        'api_response' => 'error',
                        'status_code' => 400,
                        'data' => 'Install Wordpress',
                        'message' => 'WordPress is already installed'
                    ];
                    return response()->json($errorArray);
                }
            }
            $dbDetail = unserialize($serverPackage->wordpress_detail);
            $installPath = $dbDetail['installPath'];
            if($installPath != '' && $installPath != '""'){                
                $publicHtml = $this->getFileCount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), 'public_html');
                if(is_array($publicHtml) && array_key_exists("cpanelresult", $publicHtml) && array_key_exists("data", $publicHtml['cpanelresult']) && is_numeric(array_search($installPath, array_column($publicHtml['cpanelresult']['data'], 'file')))){
                    $errorArray = [
                        'api_response' => 'error',
                        'status_code' => 400,
                        'data' => 'Install Wordpress',
                        'message' => 'WordPress is already installed'
                    ];
                    return response()->json($errorArray);
                }
            }
            $publicHtml = $this->getFileCount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), 'public_html');
            if(is_array($publicHtml) && array_key_exists("cpanelresult", $publicHtml) && array_key_exists("data", $publicHtml['cpanelresult']) && is_numeric(array_search('wp-config.php', array_column($publicHtml['cpanelresult']['data'], 'file')))){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Install Wordpress',
                    'message' => 'WordPress is already installed'
                ];
                return response()->json($errorArray);
            }
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => 'Install Wordpress',
                'message' => 'WordPress is not installed'
            ];
            return response()->json($errorArray);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
    }
    /*
    API Method Name:    reinstallConfirm
    Developer:          Shine Dezign
    Created Date:       2022-07-12 (yyyy-mm-dd)
    Purpose:            Reinstall Wordpress on the server
    */
    public function reinstallWordpress(Request $request, $id = null) {
        if(!$id){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Requesting error', 'message' => 'Server ID is required']);
        }
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Reinstall Wordpress'
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Install Wordpress',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $dbName = null;
            $publicHtml = NULL;
            if($serverPackage->wordpress_detail){
                $dbDetail = unserialize($serverPackage->wordpress_detail);
                $installPath = $dbDetail['installPath'];
                $dbName = $dbDetail['database'];
                if($installPath != '' && $installPath != '""'){      
                    $publicHtml = $installPath;
                }
                if($dbName){
                    $deleteDb = $this->deleteMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $dbName);
                    if(!is_array($deleteDb) || !array_key_exists("result", $deleteDb)){
                        //Hit node api to save logs
                        hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                        return response()->json($errorArray);
                    }
                    
                    if ($deleteDb["result"]['status'] == "0") {
                        $error = $deleteDb['result']["errors"];
                        if(is_array($deleteDb['result']['errors'])){
                            $error = $deleteDb['result']['errors'][0];
                        }
                        $errorArray = [
                            'api_response' => 'error',
                            'status_code' => 400,
                            'data' => 'Delete MySql Database error',
                            'message' => $error
                        ];
                        $postData['response'] = serialize($errorArray);
                        $postData['errorType'] = 'System Error';
                        //Hit node api to save logs
                        hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                        $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                        return response()->json($errorArray);
                    }
                    $deleteUser = $this->deleteMySqlUser($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $dbName);
                    if(!is_array($deleteUser) || !array_key_exists("result", $deleteUser)){
                        //Hit node api to save logs
                        hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                        return response()->json($errorArray);
                    }
                    
                    if ($deleteUser["result"]['status'] == "0") {
                        $error = $deleteUser['result']["errors"];
                        if(is_array($deleteUser['result']['errors'])){
                            $error = $deleteUser['result']['errors'][0];
                        }
                        $errorArray = [
                            'api_response' => 'error',
                            'status_code' => 400,
                            'data' => 'Delete MySql Database error',
                            'message' => $error
                        ];
                        $postData['response'] = serialize($errorArray);
                        $postData['errorType'] = 'System Error';
                        //Hit node api to save logs
                        hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                        $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                        return response()->json($errorArray);
                    }
                }
            }
            if(!$publicHtml){              
                $this->removeDir($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), 'public_html');
                $nakeDir = $this->makeDir($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), 'public_html');
            } else{
                $this->removeDir($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), 'public_html/'.$publicHtml);
            }
            
            UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->update(['wordpress_detail' => serialize(['database' => NULL, 'installPath' => $publicHtml]), 'install_wordpress' => 0]);
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => 'Reinstall Wordpress',
                'message' => 'Ready To Install WordPress'
            ];
            return response()->json($errorArray);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
    }
    
}
