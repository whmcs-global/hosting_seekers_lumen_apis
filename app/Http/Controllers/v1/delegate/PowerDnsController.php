<?php

namespace App\Http\Controllers\v1\delegate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CloudfareTrait, CpanelTrait};
use App\Models\{UserServer};

class PowerDnsController extends Controller
{
    use CloudfareTrait, CpanelTrait;

    public function getListing(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            if(!$serverPackage->cloudfare_user_id){
                $domainName = $serverPackage->domain;
                $cloudFare = $this->createZoneSet($domainName);
                $zoneInfo = $this->getSingleZone($domainName);
                $accountCreate = [];
                $cloudfareUser = null;
                if($zoneInfo['success']){
                    $accountCreate['ns_detail'] = serialize($zoneInfo['result'][0]['name_servers']);
                    
                    $cloudfareUser = CloudfareUser::where('status', 1)->first();
                    $accountCreate['cloudfare_id'] = $zoneInfo['result'][0]['id'];
                    $userCount = UserServer::where(['cloudfare_user_id' => $cloudfareUser->id ])->count();
                    $updateData = ['domain_count' => $userCount+1];
                    if($userCount == 100){
                        $updateData = ['domain_count' => $userCount, 'status' => 0];
                        CloudfareUser::where('id', $cloudfareUser->id)->update($updateData);
                        CloudfareUser::where('domain_count', '!=', 100)->where(['status' =>  0])->update(['status' => 1]);
                        $cloudfareUser = CloudfareUser::where('status', 1)->first();
                    } else{
                        CloudfareUser::where('id', $cloudfareUser->id)->update($updateData);
                    }
                    $accountCreate['cloudfare_user_id'] = $cloudfareUser->id;
                    $dnsData = [
                        [
                            'zone_id' => $zoneInfo['result'][0]['id'],
                            'cfdnstype' => 'A',
                            'cfdnsname' => $domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '86400',
                        ],
                        [
                            'zone_id' => $zoneInfo['result'][0]['id'],
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'www.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '86400',
                        ],
                        [
                            'zone_id' => $zoneInfo['result'][0]['id'],
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'mail.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                        'cfdnsttl' => '86400',
                        ],
                        [
                            'zone_id' => $zoneInfo['result'][0]['id'],
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'webmail.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '86400',
                        ],
                        [
                            'zone_id' => $zoneInfo['result'][0]['id'],
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'cpanel.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '86400',
                        ],
                        [
                            'zone_id' => $zoneInfo['result'][0]['id'],
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'ftp.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '86400',
                        ]
                        ];
                    foreach ($dnsData as $dnsVal) {
                        $createDns = $this->createDNSRecord($dnsVal, $zoneInfo['result'][0]['id'], $cloudfareUser->email, $cloudfareUser->user_api);
                    }
                }
                UserServer::where(['id' => $serverPackage->id])->update($accountCreate);
                $serverPackage = UserServer::where(['id' => $serverPackage->id])->first();
            }
            if($cloudfareUser){

                $userList = $this->listDNSRecords($serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            } else{
                $userList = ['result' => 'error', 'data' => ['apierror' => config('constants.ERROR.FORBIDDEN_ERROR')]];
            }
            
            
            $domainList = $this->domainList($serverPackage->company_server_package->company_server_id,  strtolower($serverPackage->name));
            
            if(!is_array($domainList) || !array_key_exists("result", $domainList)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($domainList["result"]['status'] == "0") {
                $error = $domainList["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            if($userList['result'] != 'error' && $userList['success'])
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => ['records' => $userList['result'], 'domains' => $domainList['result']["data"], 'name_servers' => $serverPackage->ns_detail ? unserialize($serverPackage->ns_detail) : null], 'message' => 'Zone records has been successfully fetched']);
            if($userList['result'] == 'error'){
                $errormsg = $userList['data']['apierror'];
            } else{
                $errormsg = $userList['errors'];
            }
            return response()->json(['api_response' => 'error', 'status_code' => 200, 'data' => ['records' => [], 'domains' => $domainList['result']["data"], 'name_servers' => $serverPackage->ns_detail ? unserialize($serverPackage->ns_detail) : null], 'message' => $errormsg]);
            
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
    }
    
    public function createRecord(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'name' => 'required',
            'type' => 'required',
            'ttl' => 'required',
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
            
			$fullHostName = $request->name.'.'.$serverPackage->domain; 
            $data =[
                'zone_id' => $serverPackage->cloudfare_id,
                'cfdnstype' => $request->type,
                'cfdnsname' => $fullHostName,
                'cfdnsvalue' => $request->content,
                'cfdnsttl' => $request->ttl,
                'cfmxpriority' => $request->priority??0,
            ];
            $createDns = $this->createDNSRecord($data, $serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($createDns['result'] == 'error'){
                $errormsg = $createDns['data']['apierror'];
            } else{
                $errormsg = $createDns['errors'];
            }
            if($createDns['result'] == 'error' || !$createDns['success']){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Zone Record adding error', 'message' => $errormsg]);
            }
            $userList = $this->listDNSRecords($serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($userList['result'] != 'error' && $userList['success'])
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $userList['result'], 'message' => 'Zone records has been successfully added']);
            if($userList['result'] == 'error'){
                $errormsg = $userList['data']['apierror'];
            } else{
                $errormsg = $userList['errors'];
            }
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => [], 'message' => $errormsg]);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Zone Record adding error', 'message' => $ex->getMessage()]);
        }
    }
    
    public function updateRecord(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'id' => 'required',
            'name' => 'required',
            'type' => 'required',
            'ttl' => 'required',
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
            
			$fullHostName = $request->name.'.'.$serverPackage->domain; 
            $data =[
                'dnsrecordid' => $request->id,
                'cfdnstype' => $request->type,
                'cfdnsname' => $fullHostName,
                'cfdnsvalue' => $request->content,
                'cfdnsttl' => $request->ttl,
                'cfmxpriority' => $request->priority??0,
            ];
            $createDns = $this->editDNSRecord($data, $serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($createDns['result'] == 'error'){
                $errormsg = $createDns['data']['apierror'];
            } else{
                $errormsg = $createDns['errors'];
            }
            if($createDns['result'] == 'error' || !$createDns['success']){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Zone Record adding error', 'message' => $errormsg]);
            }
            $userList = $this->listDNSRecords($serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($userList['result'] != 'error' && $userList['success'])
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $userList['result'], 'message' => 'Zone records has been successfully added']);
            if($userList['result'] == 'error'){
                $errormsg = $userList['data']['apierror'];
            } else{
                $errormsg = $userList['errors'];
            }
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => [], 'message' => $errormsg]);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Zone Record adding error', 'message' => $ex->getMessage()]);
        }
    }
    
    public function deleteRecord(Request $request) {
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $data =[
                'dnsrecordid' => $request->id,
            ];
            $createDns = $this->deleteDNSRecord($data, $serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($createDns['result'] == 'error'){
                $errormsg = $createDns['data']['apierror'];
            } else{
                $errormsg = $createDns['errors'];
            }
            if($createDns['result'] == 'error' || !$createDns['success']){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Zone Record deleting error', 'message' => $errormsg]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Zone records has been successfully deleted']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Zone Record adding error', 'message' => $ex->getMessage()]);
        }
    }
}
