<?php

namespace App\Http\Controllers\v1\delegate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, OrderTransaction, CompanyServerPackage, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

class MySqlDbController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;
    
    public function checkName($id, $name, $type) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['id' => $serverId])->first();
            if(!$serverPackage){       
                return true;
            }
            $userCreated = $this->getMySqlUserRestrictions($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(is_array($userCreated) && array_key_exists("result", $userCreated) && $userCreated['result']['status'] == 1) {
                if($userCreated['result']['data']['prefix']){
                    if('user' == $type && str_contains($name, $userCreated['result']['data']['prefix']) && $userCreated['result']['data']['max_username_length'] > strlen($name)){
                        return true;
                    }
                    if('db' == $type && str_contains($name, $userCreated['result']['data']['prefix']) && $userCreated['result']['data']['max_database_name_length'] > strlen($name)){
                        return true;
                    }
                } else{
                    if('user' == $type && $userCreated['result']['data']['max_username_length'] > strlen($name)){
                        return true;
                    }
                    if('db' == $type && $userCreated['result']['data']['max_database_name_length'] > strlen($name)){
                        return true;
                    }
                }
            }
            return false;
        }
        catch(Exception $ex){
            return false;
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return false;
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return false;
        }

    }

    public function getDatabases(Request $request, $id) {
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
            'module' => 'MySql Databases',
            'requestedFor' => serialize(['name' => 'MySql Database Listing', 'version' => $request->version]),
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
            $accCreated = $this->getMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
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
                    'data' => 'MySql Database listing error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["result"]["data"], 'message' => 'MySql Databases has been successfully fetched']);
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
    
    public function addDatabase(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'name' => 'required',
        ]);
        if($validator->fails() ){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' =>$validator->getMessageBag()->toArray()], 400);
        }
        if(!$this->checkName($request->cpanel_server, $request->name, 'db'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid name']], 400);
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
            'module' => 'MySql Databases',
            'requestedFor' => serialize(['name' => 'MySql Database Listing', 'name' => $request->name]),
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
            $accCreated = $this->createMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->name);
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
                    'data' => 'Create MySql Database error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }

            $emails = $this->getDatabases($request, $request->cpanel_server)->getOriginalContent();
            if(!is_array($emails)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }          
            if ($emails['api_response'] == 'error') {
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => 'Create MySql Database',
                'message' => 'MySql database has been successfully created'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $emails['data'], 'message' => 'MySql Database has been successfully created']);
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
    
    public function updateDatabase(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'newname' => 'required',
            'oldname' => 'required',
        ]);
        if($validator->fails() ){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' =>$validator->getMessageBag()->toArray()], 400);
        }
        if(!$this->checkName($request->cpanel_server, $request->newname, 'db'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid name']], 400);
        
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
            'module' => 'MySql Databases',
            'requestedFor' => serialize(['name' => 'Rename MySql Database Listing', 'newname' => $request->newname, 'oldname' => $request->oldname]),
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
            $accCreated = $this->updateMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->newname,  $request->oldname);
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
                    'data' => 'Rename MySql Database error',
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
                'data' => 'Rename MySql Database',
                'message' => 'MySql database has been successfully renamed'
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
    
    public function getPrivileges(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'name' => 'required',
            'username' => 'required'
        ]);
        if($validator->fails() ){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' =>$validator->getMessageBag()->toArray()], 400);
        }
        if(!$this->checkName($request->cpanel_server, $request->name, 'db'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid name']], 400);
        if(!$this->checkName($request->cpanel_server, $request->username, 'user'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid username']], 400);
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
            'module' => 'MySql Databases',
            'requestedFor' => serialize(['name' => 'Get MySql Database Privileges', 'username' => $request->username, 'name' => $request->name]),
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
            $accCreated = $this->getMySqlDbPrivileges($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->name,  $request->username);
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
                    'data' => 'Get MySql Database Privileges error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['result']['data'], 'message' => 'MySql Database privileges has been successfully fetched']);
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
    
    public function removePrivileges(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'name' => 'required',
            'username' => 'required',
        ]);
        if($validator->fails() ){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' =>$validator->getMessageBag()->toArray()], 400);
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
            'module' => 'MySql Databases',
            'requestedFor' => serialize(['name' => 'Remove MySql Database Privileges', 'username' => $request->username, 'name' => $request->name]),
            'response' => serialize($errorArray)
        ];
        if(!$this->checkName($request->cpanel_server, $request->name, 'db'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid name']], 400);
        if(!$this->checkName($request->cpanel_server, $request->username, 'user'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid username']], 400);
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){       
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            $accCreated = $this->removeMySqlDbPrivileges($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->name,  $request->username);
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
                    'data' => 'Remove MySql Database Privileges error',
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
                'data' => 'Revoke MySql Database Privileges',
                'message' => 'MySql database privileges has been successfully revoked'
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
    
    public function updatePrivileges(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'name' => 'required',
            'username' => 'required',
            'privileges' => 'required',
        ]);
        if($validator->fails() ){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' =>$validator->getMessageBag()->toArray()], 400);
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
            'module' => 'MySql Databases',
            'requestedFor' => serialize(['name' => 'Update MySql Database Privileges', 'username' => $request->username, 'name' => $request->name]),
            'response' => serialize($errorArray)
        ];
        if(!$this->checkName($request->cpanel_server, $request->name, 'db'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid name']], 400);
        if(!$this->checkName($request->cpanel_server, $request->username, 'user'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid username']], 400);
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){       
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            $accCreated = $this->updateMySqlDbPrivileges($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->name,  $request->username,  $request->privileges);
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
                    'data' => 'Update MySql Database Privileges error',
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
                'data' => 'Update MySql Database Privileges',
                'message' => 'MySql database privileges has been successfully updated'
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
    
    public function deleteDatabase(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'database' => 'required'
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
            'module' => 'MySql Databases',
            'requestedFor' => serialize(['name' => 'Delete MySql Database', 'database' => $request->database]),
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
            $accCreated = $this->deleteMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->database);
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
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => 'Delete MySql Database',
                'message' => 'MySql database has been successfully deleted'
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
