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
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
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
                
                $ratingArray = $permissionData;
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
                
                $ratingArray = $permissionData;
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
