<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{BlockedIp, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

class BackUpController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;
    public function getBackupFiles(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->listBackups($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Backup files fetching error', 'message' => $error]);
            }   
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']['data'], 'message' => 'Backup files has been successfully fetched']);
        }  catch(\Exception $e){
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }

    public function createBackup(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required'
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
            $accCreated = $this->backupFiles($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Backup file error', 'message' => $error]);
            }
            
            $emails = $this->getBackupFiles($request, $request->cpanel_server)->getOriginalContent();
            if(!is_array($emails)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }            
            if ($emails['api_response'] == 'error') {
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Backup creating error', 'message' => $emails['message']]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $emails['data'], 'message' => 'Backup has been successfully created']);
            return $this->apiResponse('success', '200', 'Backup file listing');
        }  catch(\Exception $e){
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }

    public function downloadtBackupFiles(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'filename' => 'required'
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
            $accCreated = $this->copyFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->filename);
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Backup file fetching error', 'message' => $error]);
            }   
            $accCreated = $this->filePermission($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->filename);
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Backup file fetching error', 'message' => $error]);
            }   
            return $this->apiResponse('success', '200', 'Download Backup file', 'https://'.$serverPackage->domain.'/'.$request->filename);
        }  catch(\Exception $e){
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }

    public function deleteBackupFiles(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'filename' => 'required'
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
            $accCreated = $this->removeFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), '/'.$request->filename);
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Backup file deleting error', 'message' => $error]);
            }   
            return $this->apiResponse('success', '200', 'Backup file has been deleted successfully');
        }  catch(\Exception $e){
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
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
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->blockIp($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->ip_address);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Block ip error', 'message' => $error]);
            }
            BlockedIp::updateOrCreate(['user_id' => $request->userid, 'ip_address' => $request->ip_address]);
            $records = BlockedIp::where('user_id', $request->userid)->get();
            $ratingArray = [];
            if($records->isNotEmpty()){ 
                $permissionData = [];
                foreach($records as $row){
                    array_push($permissionData, ['id'=> jsencode_userdata($row->id), 'ip_address' => $row->ip_address]);
                }
                
                $rating['data'] = $permissionData;
                $ratingArray = ['refinedData' => $rating];
            }            
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $ratingArray, 'message' => 'Ip address has been successfully blocked']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
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
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);

            $accCreated = $this->unblockIp($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->ip_address);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Unblock ip error', 'message' => $error]);
            }
            BlockedIp::where(['user_id' => $request->userid, 'ip_address' => $request->ip_address])->delete();
            $records = BlockedIp::where('user_id', $request->userid)->get();
            $ratingArray = [];
            if($records->isNotEmpty()){ 
                $permissionData = [];
                foreach($records as $row){
                    array_push($permissionData, ['id'=> jsencode_userdata($row->id), 'ip_address' => $row->ip_address]);
                }
                
                $rating['data'] = $permissionData;
                $ratingArray = ['refinedData' => $rating];
            }      
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $ratingArray, 'message' => 'Ip address has been successfully unblocked']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        }
    }
}
