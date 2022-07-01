<?php

namespace App\Http\Controllers\v1\delegate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait};
use App\Models\{UserServer};

class CronJobController extends Controller
{
    use CpanelTrait;

    public function getListing(Request $request, $id) {
        
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Cpanel CronJobs Listing',
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Cpanel CronJobs',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($id);
            $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$server){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'CronJobs Listing for'.$server->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            
            $action = 'cpanel';
            $params = [
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'Cron',
                'cpanel_jsonapi_func' => 'fetchcron',
                'cpanel_jsonapi_user' => strtolower($server->name)
            ];
            $accCreated = $this->runQuery($server->company_server_package->company_server_id, $action, $params);
            
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
                    'data' => 'Cron job fetching error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }   
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']['data'], 'message' => 'Cron jobs has been successfully fetched']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
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
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server errorr',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
    }
    
    public function createRecord(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'day' => 'required',
            'hour' => 'required',
            'minute' => 'required',
            'month' => 'required',
            'weekday' => 'required',
            'command' => 'required'
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
            'name' => 'Create Cpanel CronJobs',
            'day' => $request->day,
            'hour' => $request->hour,
            'minute' => $request->minute,
            'month' => $request->month,
            'weekday' => $request->weekday,
            'command' => $request->command
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Cpanel CronJobs',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$server){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'CronJobs creating for'.$server->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            
            $action = 'cpanel';
            $params = [
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'Cron',
                'cpanel_jsonapi_func' => 'add_line',
                'cpanel_jsonapi_user' => strtolower($server->name),
                'day' => $request->day,
                'hour' => $request->hour,
                'minute' => $request->minute,
                'month' => $request->month,
                'weekday' => $request->weekday,
                'command' => $request->command
            ];
            $accCreated = $this->runQuery($server->company_server_package->company_server_id, $action, $params);
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Cron job fetching error',
                    'message' => config('constants.ERROR.FORBIDDEN_ERROR')
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Cron job fetching error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }   
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("status", $accCreated['cpanelresult']['data'][0]) && 0 == $accCreated['cpanelresult']["data"][0]['status']) {
                $error = $accCreated['cpanelresult']['data'][0]['statusmsg'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Cron job adding error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }   
            
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [],
                'message' => 'Cron job has been successfully added'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            $action = 'cpanel';
            $params = [
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'Cron',
                'cpanel_jsonapi_func' => 'fetchcron',
                'cpanel_jsonapi_user' => strtolower($server->name)
            ];
            $accCreated = $this->runQuery($server->company_server_package->company_server_id, $action, $params);
            
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Connection error',
                    'message' => config('constants.ERROR.FORBIDDEN_ERROR')
                ];
                $postData['response'] = serialize($errorArray);
                $postData['api_response'] = 'error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Cron job adding error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['api_response'] = 'error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            }   
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']['data'], 'message' => 'Cron job has been successfully added']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
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
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server errorr',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
    }
    
    public function updateRecord(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'day' => 'required',
            'hour' => 'required',
            'minute' => 'required',
            'month' => 'required',
            'weekday' => 'required',
            'command' => 'required',
            'linekey' => 'required'
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
            'name' => 'Update Cpanel CronJobs',
            'day' => $request->day,
            'hour' => $request->hour,
            'minute' => $request->minute,
            'month' => $request->month,
            'weekday' => $request->weekday,
            'command' => $request->command
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Cpanel CronJobs',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$server){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'CronJobs upating for'.$server->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            
            $action = 'cpanel';
            $params = [
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'Cron',
                'cpanel_jsonapi_func' => 'edit_line',
                'cpanel_jsonapi_user' => strtolower($server->name),
                'linekey' => $request->linekey,
                'day' => $request->day,
                'hour' => $request->hour,
                'minute' => $request->minute,
                'month' => $request->month,
                'weekday' => $request->weekday,
                'command' => $request->command
            ];
            $accCreated = $this->runQuery($server->company_server_package->company_server_id, $action, $params);
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
                    'data' => 'Cron job upating error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }   
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("status", $accCreated['cpanelresult']['data'][0]) && 0 == $accCreated['cpanelresult']["data"][0]['status']) {
                $error = $accCreated['cpanelresult']['data'][0]['statusmsg'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Cron job upating error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }   
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [],
                'message' => 'Zone Record has been created successfully'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']['data'], 'message' => 'Cron job has been successfully updated']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
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
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server errorr',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
    }
    
    public function deleteRecord(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'line' => 'required'
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
            'name' => 'Delete CronJobs',
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Cpanel CronJobs',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$server){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'CronJobs deleting for'.$server->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            
            $action = 'cpanel';
            $params = [
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'Cron',
                'cpanel_jsonapi_func' => 'remove_line',
                'cpanel_jsonapi_user' => strtolower($server->name),
                'line' => $request->line
            ];
            $accCreated = $this->runQuery($server->company_server_package->company_server_id, $action, $params);
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
                    'data' => 'Cron job deleting error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }   
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("status", $accCreated['cpanelresult']['data'][0]) && 0 == $accCreated['cpanelresult']["data"][0]['status']) {
                $error = $accCreated['cpanelresult']['data'][0]['statusmsg'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Cron job deleting error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }   
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [],
                'message' => 'Cron job has been successfully deleted'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Cron job has been successfully deleted']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'CronJob deleting error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
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
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server errorr',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
    }
}
