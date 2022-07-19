<?php

namespace App\Http\Controllers\v1\delegate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{BlockedIp, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

class BlockedIpController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;
    public function getIps(Request $request, $id) {
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
            'module' => 'Block IP Addrress',
            'requestedFor' => serialize(['name' => 'Block IP Address Listing']),
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
            $records = BlockedIp::where('user_id', $request->userid)->get();
            $ratingArray = [];
            if($records->isNotEmpty()){ 
                $permissionData = [];
                foreach($records as $row){
                    array_push($permissionData, ['id'=> jsencode_userdata($row->id), 'ip_address' => $row->ip_address]);
                }
                
                $ratingArray = $permissionData;
            }
            return $this->apiResponse('success', '200', 'Blocked IP Addresses listing', $ratingArray);
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
    
    public function blockIpAddress(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'ip_address' => 'required'
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
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Block IP Addrress',
            'requestedFor' => serialize(['name' => 'Block IP Address', 'ip_address' => $request->ip_address]),
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
            $accCreated = $this->blockIp($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->ip_address);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated["result"]['errors'];
                if(is_array($accCreated['result']['errors'])){
                    $error = $accCreated['result']['errors'][0];
                }
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Block IP Address error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }
            BlockedIp::updateOrCreate(['user_id' => $request->userid, 'ip_address' => $request->ip_address]);
            $records = BlockedIp::where('user_id', $request->userid)->get();
            $ratingArray = [];
            if($records->isNotEmpty()){ 
                $permissionData = [];
                foreach($records as $row){
                    array_push($permissionData, ['id'=> jsencode_userdata($row->id), 'ip_address' => $row->ip_address]);
                }
                
                $ratingArray = $permissionData;
            }          
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [],
                'message' => 'Ip address has been successfully blocked'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $ratingArray, 'message' => 'Ip address has been successfully blocked']);
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
    
    public function deleteIpAddress(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'ip_address' => 'required'
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
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Block IP Addrress',
            'requestedFor' => serialize(['name' => 'Unblock IP Address', 'ip_address' => $request->ip_address]),
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

            $accCreated = $this->unblockIp($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->ip_address);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                if(is_array($accCreated['result']['errors'])){
                    $error = $accCreated['result']['errors'][0];
                }
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Unblock IP Address error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }
            BlockedIp::where(['user_id' => $request->userid, 'ip_address' => $request->ip_address])->delete();
            $records = BlockedIp::where('user_id', $request->userid)->get();
            $ratingArray = [];
            if($records->isNotEmpty()){ 
                $permissionData = [];
                foreach($records as $row){
                    array_push($permissionData, ['id'=> jsencode_userdata($row->id), 'ip_address' => $row->ip_address]);
                }
                
                $ratingArray = $permissionData;
            }   
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [],
                'message' => 'Ip address has been successfully unblocked'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);   
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $ratingArray, 'message' => 'Ip address has been successfully unblocked']);
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
