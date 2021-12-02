<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, OrderTransaction, CompanyServerPackage, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

class MySqlController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;

    public function loginPHPMYADMIN(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->loginPhpMyAdminAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                $error = $accCreated['metadata']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account login error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['data'], 'message' => 'Account is ready for login']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }

    public function getListing(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::findOrFail($serverId);
            $userList = $this->getMySqlUsers($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($userList) || !array_key_exists("result", $userList)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if (0 == $userList['result']['status'] ) {
                $error = $userList['result']['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql User and databases fetching error', 'message' => $error]);
            }
            $dbList = $this->getMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($dbList) || !array_key_exists("result", $dbList)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if (0 == $dbList['result']['status'] ) {
                $error = $dbList['result']['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql User and databases fetching error', 'message' => $error]);
            }
            $userArray = $dbArray = [];
            foreach($userList['result']['data'] as $user){
                array_push($userArray, $user['user']);
            }
            foreach($dbList['result']['data'] as $db){
                array_push($dbArray, $db['database']);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => ['users' => $userArray, 'databases' => $dbArray], 'message' => 'MySql User and databases has been successfully fetched']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }

    public function checkName($id, $name, $type) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::findOrFail($serverId);
            $userCreated = $this->getMySqlUserRestrictions($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(is_array($userCreated) && array_key_exists("result", $userCreated) && $userCreated['result']['status'] == 1) {
                if('user' == $type && str_contains($name, $userCreated['result']['data']['prefix']) && $userCreated['result']['data']['max_username_length'] > strlen($name)){
                    return true;
                }
                if('db' == $type && str_contains($name, $userCreated['result']['data']['prefix']) && $userCreated['result']['data']['max_database_name_length'] > strlen($name)){
                    return true;
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
    public function getPrivileges() {
        $privileges = ["ALL PRIVILEGES", ["ALTER", "ALTER ROUTINE", "CREATE", "CREATE ROUTINE", "CREATE TEMPORARY TABLES", "CREATE VIEW", "DELETE", "DROP", "EVENT", "EXECUTE", "INDEX", "INSERT", "LOCK TABLES", "REFERENCES", "SELECT", "SHOW VIEW", "TRIGGER", "UPDATE"] ];
        return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $privileges, 'message' => 'Mysql privileges has been fetched']);
    }
    public function getUsersRestrictions(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->getMySqlUserRestrictions($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql Name Restrictions fetching error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["result"]["data"], 'message' => 'MySql Name Restrictions has been successfully fetched']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }

    public function getUsers(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->getMySqlUsers($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql Users fetching error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["result"]["data"], 'message' => 'MySql Users has been successfully fetched']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    
    public function addUser(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'username' => 'required',
            'password' => 'required',
        ]);
        if($validator->fails() ){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' =>$validator->getMessageBag()->toArray()], 400);
        }
        if(!$this->checkName($request->cpanel_server, $request->username, 'user'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid username']], 400);
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->createMySqlUser($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->username,  $request->password);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql user creation error', 'message' => $error]);
            }

            $emails = $this->getUsers($request, $request->cpanel_server)->getOriginalContent();
            if(!is_array($emails)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }          
            if ($emails['api_response'] == 'error') {
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account fetching error', 'message' => $emails['message']]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $emails['data'], 'message' => 'MySql user has been successfully created']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    
    public function updateUser(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'newname' => 'required',
            'oldname' => 'required',
        ]);
        if($validator->fails() ){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' =>$validator->getMessageBag()->toArray()], 400);
        }
        if(!$this->checkName($request->cpanel_server, $request->newname, 'user'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid username']], 400);
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->updateMySqlUser($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->newname,  $request->oldname);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql user creation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'MySql user updated', 'message' => 'MySql user has been successfully updated']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    
    public function updateUserPassword(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'username' => 'required',
            'password' => 'required',
        ]);
        if($validator->fails() ){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' =>$validator->getMessageBag()->toArray()], 400);
        }
        if(!$this->checkName($request->cpanel_server, $request->username, 'user'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid username']], 400);
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->updateMySqlUserPassword($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->username,  $request->password);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql user creation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'Update Password', 'message' => 'MySql user password has been successfully updated']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    
    public function deleteUser(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'user' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->deleteMySqlUser($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->user);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql user creation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'MySql user delete', 'message' => 'MySql user has been successfully deleted']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
}