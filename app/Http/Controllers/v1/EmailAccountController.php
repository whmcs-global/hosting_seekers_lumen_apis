<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, OrderTransaction, CompanyServerPackage, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

class EmailAccountController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;
    public function getEmailAccount(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->listEmailAccounts($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account fetching error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']["data"], 'message' => 'Accounts has been successfully fetched']);
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
    
    public function addEmailAccount(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'email' => 'required',
            'password' => 'required',
            'quota' => 'required|numeric'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->createEmailAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->email.'@'.$serverPackage->domain,  $request->password,  $request->quota);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }

            $emails = $this->getEmailAccount($request, $request->cpanel_server)->getOriginalContent();
            if(!is_array($emails)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }            
            if ($emails['api_response'] == 'error') {
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account fetching error', 'message' => $emails['message']]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $emails['data'], 'message' => 'Account has been successfully created']);
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
    public function updateEmailPasswrod(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'email' => 'required',
            'password' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->changeEmailPassword($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->email.'@'.$serverPackage->domain,  $request->password);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account updation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'Email Account password updated', 'message' => 'Account password has been successfully update']);
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
    
    public function deleteEmailAccount(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'email' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->deleteEmailsAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->email);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if(!array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("errors", $accCreated['result']) && $accCreated['result']["errors"]) {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account deleting error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'Email Account done', 'message' => 'Email Account has been successfully deleted']);
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
