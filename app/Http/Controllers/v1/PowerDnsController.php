<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CloudfareTrait, CpanelTrait};
use App\Models\{UserServer, CloudfareUser};

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
            $cloudfareUser = true;
            if(!$serverPackage->cloudfare_user_id){
                $domainName = $serverPackage->domain;
                $cloudFare = $this->createZoneSet($domainName);
                $zoneInfo = $this->getSingleZone($domainName);
                $accountCreate = [];
                $cloudfareUser = null;
                if($zoneInfo['success']){
                    
                    $cloudfareUser = CloudfareUser::where('status', 1)->first();
                    $accountCreate['cloudfare_id'] = $zoneInfo['result'][0]['id'];
                    $userCount = UserServer::where(['cloudfare_user_id' => $cloudfareUser->id ])->count();
                    $updateData = ['domain_count' => $userCount+1];
                    if($userCount == 100){
                        $updateData = ['domain_count' => $userCount, 'status' => 0];
                        CloudfareUser::where('id', $cloudfareUser->id)->update($updateData);
                        $cloudfareUser = CloudfareUser::where('domain_count', '!=', 100)->where(['status' =>  0])->update(['status' => 1]);
                    } else{
                        CloudfareUser::where('id', $cloudfareUser->id)->update($updateData);
                    }
                    $accountCreate['cloudfare_user_id'] = $cloudfareUser->id;
                    $dnsData = [
                        [
                            'zone_id' => $zoneInfo['result'][0]['id'],
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'www',
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '120',
                        ]
                    ];
                    foreach ($dnsData as $dnsVal) {
                        $createDns = $this->createDNSRecord($dnsVal, $zoneInfo['result'][0]['id'], $cloudfareUser->email, $cloudfareUser->user_api);
                    }
                }
                $serverPackage = UserServer::where(['id' => $serverPackage->id])->update($accountCreate);
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
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => ['records' => $userList['result'], 'domains' => $domainList['result']["data"]], 'message' => 'Zone records has been successfully fetched']);
            if($userList['result'] == 'error'){
                $errormsg = $userList['data']['apierror'];
            } else{
                $errormsg = $userList['result'];
            }
            return response()->json(['api_response' => 'error', 'status_code' => 200, 'data' => ['records' => [], 'domains' => $domainList['result']["data"]], 'message' => $errormsg]);
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
            
            $domainId = $this->createDNSRecord($serverPackage->domain); 
            if($domainId['status'] == 'error')
            return response()->json(['api_response' => 'error', 'status_code' => 200, 'data' => 'Zone Record adding error', 'message' => $domainId['data']]);
			$stringErrpr = '';
			$response = [];
			$fullHostName = $request->name.'.'.$serverPackage->domain; 
            $data = [
                'domain_id' => strval($domainId['data']),
                'name' => $fullHostName,
                'type' => $request->type,
                'content' => $request->content,
                'ttl' => $request->ttl,
                'change_date' => time()
            ];
            if($request->priority)
            $data['prio'] = $request->priority;
            try{
                $resultQuery = DB::connection('mysql3')->table('records')->insert($data);
            } catch(Exception $ex){
                $stringErrpr =  $ex->get_message();
            }
			if(!empty($stringErrpr)){
                return response()->json(['api_response' => 'error', 'status_code' => 200, 'data' => 'Zone Record adding error', 'message' => $stringErrpr]);
			}		
            $nameserver = $serverPackage->company_server_package->company_server->name_servers ? unserialize($serverPackage->company_server_package->company_server->name_servers) : [];
            $userList = $this->wgsReturnDomainData(strtolower($serverPackage->domain), $serverPackage->company_server_package->company_server->ip_address, $nameserver);
            if($userList['status'] == 'success')
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $userList['data'], 'message' => 'Zone records has been successfully added']);
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Zone records has been successfully added']);
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
            
			$stringErrpr = '';
			$response = [];
			$fullHostName = $request->name.'.'.$serverPackage->domain; 
            $data = [
                'name' => $fullHostName,
                'type' => $request->type,
                'content' => $request->content,
                'ttl' => $request->ttl,
                'change_date' => time()
            ];
            if($request->priority)
            $data['prio'] = $request->priority;
            try{
                $resultQuery = DB::connection('mysql3')->table('records')->where('id', jsdecode_userdata($request->id))->update($data);
            } catch(Exception $ex){
                $stringErrpr =  $ex->get_message();
            }
			if(!empty($stringErrpr)){
                return response()->json(['api_response' => 'error', 'status_code' => 200, 'data' => 'Zone Record adding error', 'message' => $stringErrpr]);
			}		
            $nameserver = $serverPackage->company_server_package->company_server->name_servers ? unserialize($serverPackage->company_server_package->company_server->name_servers) : [];
            $userList = $this->wgsReturnDomainData(strtolower($serverPackage->domain), $serverPackage->company_server_package->company_server->ip_address, $nameserver);
            if($userList['status'] == 'success')
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $userList['data'], 'message' => 'Zone records has been successfully added']);
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Zone records has been successfully added']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Zone Record adding error', 'message' => $ex->getMessage()]);
        }
    }
    
    public function deleteRecord(Request $request, $id) {
        try
        {
            $id = jsdecode_userdata($id);
            try{
                $resultQuery = DB::connection('mysql3')->table('records')->where('id', $id)->delete();
            } catch(Exception $ex){
                $stringErrpr =  $ex->get_message();
            }
			if(!empty($stringErrpr)){
                return response()->json(['api_response' => 'error', 'status_code' => 200, 'data' => 'Zone Record adding error', 'message' => $stringErrpr]);
			}		
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Zone records has been successfully deleted']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Zone Record adding error', 'message' => $ex->getMessage()]);
        }
    }
}
