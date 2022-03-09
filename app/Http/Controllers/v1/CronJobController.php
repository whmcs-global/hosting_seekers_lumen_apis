<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait};
use App\Models\{UserServer};

class CronJobController extends Controller
{
    use CpanelTrait;

    public function getListing(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$server)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            
            $action = 'cpanel';
            $params = [
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'Cron',
                'cpanel_jsonapi_func' => 'fetchcron',
                'cpanel_jsonapi_user' => strtolower($server->name)
            ];
            $accCreated = $this->runQuery($server->company_server_package->company_server_id, $action, $params);
            
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job fetching error', 'message' => $error]);
            }   
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']['data'], 'message' => 'Cron jobs has been successfully fetched']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
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
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$server)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            
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
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job adding error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job fetching error', 'message' => $error]);
            }   
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("status", $accCreated['cpanelresult']['data'][0]) && 0 == $accCreated['cpanelresult']["data"][0]['status']) {
                $error = $accCreated['cpanelresult']['data'][0]['statusmsg'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job adding error', 'message' => $error]);
            }   
            
            $action = 'cpanel';
            $params = [
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'Cron',
                'cpanel_jsonapi_func' => 'fetchcron',
                'cpanel_jsonapi_user' => strtolower($server->name)
            ];
            $accCreated = $this->runQuery($server->company_server_package->company_server_id, $action, $params);
            
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job adding error', 'message' => $error]);
            }   
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']['data'], 'message' => 'Cron job has been successfully added']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job adding error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
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
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$server)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            
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
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job upating error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job upating error', 'message' => $error]);
            }   
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("status", $accCreated['cpanelresult']['data'][0]) && 0 == $accCreated['cpanelresult']["data"][0]['status']) {
                $error = $accCreated['cpanelresult']['data'][0]['statusmsg'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job upating error', 'message' => $error]);
            }   
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']['data'], 'message' => 'Cron job has been successfully updated']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job upating error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
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
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$server)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            
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
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job deleting error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job deleting error', 'message' => $error]);
            }   
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("status", $accCreated['cpanelresult']['data'][0]) && 0 == $accCreated['cpanelresult']["data"][0]['status']) {
                $error = $accCreated['cpanelresult']['data'][0]['statusmsg'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job deleting error', 'message' => $error]);
            }   
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Cron job has been successfully deleted']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Cron job deleting error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
}
