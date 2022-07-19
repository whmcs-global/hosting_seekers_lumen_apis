<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{BlockedIp, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

class AddonDomainController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;
    public function getAddonsDomain(Request $request, $id) {
              
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Get Addons Domain List',
            'requestedFor' => serialize(['name' => 'Addons Domain List']),
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
            $postData['requestedFor'] = serialize(['name' => $serverPackage->domain.' Addons Domain List']);
            $accCreated = $this->getAddonsDomains($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Account login error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');           
                return response()->json($errorArray);
            }   
            return response()->json(
                [
                    'api_response' => 'success',
                    'status_code' => 200,
                    'data' => $accCreated['cpanelresult']['data'],
                    'message' => 'Addons Domains has been successfully fetched'
            ]);
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
    
    public function addAddonsDomain(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'domain' => 'required',
            'subdomain' => 'required',
            'homedir' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        } 
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Create Addons Domain',
            'domain' => $request->domain,
            'subdomain' => $request->subdomain.'.'.$request->domain,
            'dir' => $request->homedir
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Create Addons Domain',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'Create Addons Domain for '.$serverPackage->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            if($serverPackage->domain == $request->domain){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Addons domain adding error',
                    'message' => "Can't create addon domain for ".$request->domain
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $accCreated = $this->createAddonsDomain($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->domain, $request->subdomain.'.'.$serverPackage->domain, $request->homedir);
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data'][0]) && 0 == $accCreated['cpanelresult']["data"][0]['result']) {
                $error = $accCreated['cpanelresult']['data'][0]["reason"];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Addons domain adding error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }

            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [],
                'message' => 'Addons Domain has been successfully created'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
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
    public function deleteAddonsDomain(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'domain' => 'required',
            'subdomain' => 'required',
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Delete Addons Domain',
            'domain' => $request->domain,
            'subdomain' => $request->subdomain
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Delete Addons Domain',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }

            $requestedFor['name'] = 'Delete Addons Domain from '.$serverPackage->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            $accCreated = $this->delAddonsDomain($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->domain, $request->subdomain);
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                return response()->json($errorArray);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data'][0]) && 0 == $accCreated['cpanelresult']["data"][0]['result']) {
                $error = $accCreated['cpanelresult']['data'][0]["reason"];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Addons domain deleting error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [],
                'message' => 'Addons Domain has been successfully deleted'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
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
