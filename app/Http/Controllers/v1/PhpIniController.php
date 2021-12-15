<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{CompanyServerPackage, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

class PhpIniController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;
    public function getPhpIni(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'version' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->getPhpIniFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->version);
            if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                $error = $accCreated['metadata']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'PHP Ini file content error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["data"]["content"], 'message' => 'PHP INI file content has been successfully fetched']);
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

    public function updatePhpIni(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'version' => 'required',
            'content' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->updatePhpIniFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->version, $request->content);
            if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                $error = $accCreated['metadata']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'PHP Ini file content error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["data"], 'message' => 'PHP INI file content has been successfully updated']);
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

    public function getVersion(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $phpVersions = $this->phpVersions($serverPackage->company_server_package->company_server_id, $serverPackage->domain);
            $phpCurrentVersion = $this->phpCurrentVersion($serverPackage->company_server_package->company_server_id, $serverPackage->domain);
            if(!is_array($phpVersions) || !is_array($phpCurrentVersion) || !array_key_exists("metadata", $phpVersions) || !array_key_exists("metadata", $phpVersions)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($phpVersions["metadata"]['result'] == "0") {
                $error = $phpVersions["metadata"]['reason'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            
            if ($phpCurrentVersion["metadata"]['result'] == "0") {
                $error = $phpCurrentVersion["metadata"]['reason'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            $domainInfo = [
                'versions' => $phpVersions['data']['versions'],
                "current" => $phpCurrentVersion['data']['version'],
            ];
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $domainInfo, 'message' => 'PHP versions has been fetched']);
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

    public function getDirectives(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'version' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $phpGetDirectives = $this->phpIniGetDirectives($serverPackage->company_server_package->company_server_id, $serverPackage->domain, $request->version);
            if(!is_array($phpGetDirectives) || !array_key_exists("metadata", $phpGetDirectives)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($phpGetDirectives["metadata"]['result'] == "0") {
                $error = $phpGetDirectives["metadata"]['reason'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $phpGetDirectives['data']['directives'], 'message' => 'PHP versions has been fetched']);
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

    public function updateDirectives(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'version' => 'required',
            'directive' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $phpGetDirectives = $this->phpIniUpdateDirectives($serverPackage->company_server_package->company_server_id, $serverPackage->domain, $request->version, $request->directive);
            if(!is_array($phpGetDirectives) || !array_key_exists("metadata", $phpGetDirectives)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($phpGetDirectives["metadata"]['result'] == "0") {
                $error = $phpGetDirectives["metadata"]['reason'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'PHP Directive update', 'message' => 'PHP Directive has been successfully updated']);
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

    public function updateVersion(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'version' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->updatePhpCurrentVersion($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name),$request->version);
            if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                $error = $accCreated['metadata']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'PHP version updation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'PHP version update', 'message' => 'PHP version has been successfully update']);
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
