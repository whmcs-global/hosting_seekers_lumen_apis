<?php

namespace App\Http\Controllers\v1;

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

    public function getDatabases(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->getMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if (array_key_exists("error", $accCreated) || !array_key_exists("result", $accCreated) ) {
                $error = $accCreated['error'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql Databases fetching error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["result"]["data"], 'message' => 'MySql Databases has been successfully fetched']);
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["data"], 'message' => 'MySql Databases has been successfully fetched']);
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
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->createMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->name,);
            if(is_array($accCreated) && array_key_exists("result", $accCreated) && $accCreated['result']['status'] == 0) {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql Database creation error', 'message' => $error]);
            }

            $emails = $this->getDatabases($request, $request->cpanel_server)->getOriginalContent();
            if(!is_array($emails)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if (array_key_exists("error", $emails)) {
                $error = $emails['error'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql Database fetching error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $emails['data'], 'message' => 'MySql Database has been successfully created']);
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
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->updateMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->newname,  $request->oldname);
            if(is_array($accCreated) && array_key_exists("result", $accCreated) && $accCreated['result']['status'] == 0) {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql Database updation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'MySql Database updated', 'message' => 'MySql Database has been successfully updated']);
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
    
    public function removePrivileges(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'name' => 'required',
            'username' => 'required',
        ]);
        if($validator->fails() ){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' =>$validator->getMessageBag()->toArray()], 400);
        }
        if(!$this->checkName($request->cpanel_server, $request->name, 'db'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid name']], 400);
        if(!$this->checkName($request->cpanel_server, $request->username, 'user'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid username']], 400);
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->removeMySqlDbPrivileges($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->name,  $request->username);
            dd($accCreated);
            if(is_array($accCreated) && array_key_exists("result", $accCreated) && $accCreated['result']['status'] == 0) {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql Database updation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'MySql Database updated', 'message' => 'MySql Database has been successfully updated']);
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
        if(!$this->checkName($request->cpanel_server, $request->name, 'db'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid name']], 400);
        if(!$this->checkName($request->cpanel_server, $request->username, 'user'))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Validation error', 'errors' => ['Provide a valid username']], 400);
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->updateMySqlDbPrivileges($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->name,  $request->username,  $request->privileges);
            if(is_array($accCreated) && array_key_exists("result", $accCreated) && $accCreated['result']['status'] == 0) {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql Database updation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'MySql Database updated', 'message' => 'MySql Database has been successfully updated']);
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
    
    public function deleteDatabase(Request $request) {
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
            $accCreated = $this->deleteMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->user);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if(!array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("errors", $accCreated['result']) && $accCreated['result']["errors"]) {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'MySql user deleting error', 'message' => $error]);
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
