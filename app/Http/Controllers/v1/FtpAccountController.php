<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, OrderTransaction, CompanyServerPackage, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

class FtpAccountController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;
    
    public function getFtpServerInfo(Request $request) {
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
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->checkFtpAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->user);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'FTP Server Information error', 'message' => $error]);
            }
            $ftpServer = $this->getFtpConfiguration($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            
            if(!is_array($ftpServer) || !array_key_exists("result", $ftpServer)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("errors", $ftpServer['result']) && '' != $ftpServer['result']["errors"]) {
                $error = $ftpServer['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'FTP Server Information error', 'message' => $error]);
            }

            $ftpPort = $this->getFtpPort($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            
            if(!is_array($ftpPort) || !array_key_exists("result", $ftpPort)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("errors", $ftpPort['result']) && '' != $ftpPort['result']["errors"]) {
                $error = $ftpPort['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'FTP Server Information error', 'message' => $error]);
            }
            $email = explode('@', $request->user);
            if(count($email) == 2){
                list($account, $domain) = $email; 
                $email = $request->user;
            }
            else {
                $email = $request->user.'@'.$serverPackage->domain;
            }
            $ftpDetails = [
                'Username' =>  $email,
                'servername' =>  'ftp.'.$serverPackage->domain,
                'Port' =>   $ftpPort['result']["data"]["port"]
            ];
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $ftpDetails, 'message' => 'FTP Server Information has been successfully fetched']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
    
    public function getFtpAccount(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->listFtpAccounts($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            
            $domainList = $this->domainList($serverPackage->company_server_package->company_server_id,  strtolower($serverPackage->name));
            
            if(!is_array($domainList) || !array_key_exists("result", $domainList)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($domainList["result"]['status'] == "0") {
                $error = $domainList["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => ['ftp' => $accCreated['result']["data"], 'domains' => $domainList['result']["data"]], 'message' => 'Account has been successfully fetched']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
    
    public function addFtpAccount(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'username' => 'required',
            'domain' => 'required',
            'password' => 'required',
            'quota' => 'required|numeric',
            'quotasize' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        $quota = 0;
        if('MB' == $request->quotasize){
            $quota = $request->quota;
        } elseif('GB' == $request->quotasize){
            $quota = $request->quota*1024;
        } elseif('TB' == $request->quotasize){
            $quota = $request->quota*1024*1024;
        } elseif('PB' == $request->quotasize){
            $quota = $request->quota*1024*1024*1024;
        }
        if($quota > '4294967296'){
            return response()->json(["success"=>false, "errors"=>'Quotas cannot exceed 4 PB.'],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->createFtpAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->username.'@'.$request->domain,  $request->password,  $request->quota,  $request->homedir);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }

            $emails = $this->getFtpAccount($request, $request->cpanel_server)->getOriginalContent();
            if(!is_array($emails)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account fetching error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }          
            if ($emails['api_response'] == 'error') {
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account fetching error', 'message' => $emails['message']]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $emails['data'], 'message' => 'Account has been successfully created']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
    
    public function updateFtpAccount(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'username' => 'required',
            'password' => 'required',
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account updation error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->changeFtpPassword($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->username,  $request->password,  $request->quota);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account updation error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account updation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'FTP Account password updated', 'message' => 'Account password has been successfully updated']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account updation error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
    
    public function deleteFtpAccount(Request $request) {
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
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account deleting error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->deleteFtpUser($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->user);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account deleting error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account deleting error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'FTP Account done', 'message' => 'FTP Account has been successfully deleted']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account deleting error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
}
