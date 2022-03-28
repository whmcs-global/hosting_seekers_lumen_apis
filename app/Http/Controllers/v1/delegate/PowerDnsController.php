<?php

namespace App\Http\Controllers\v1\delegate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{PowerDnsTrait, CpanelTrait};
use App\Models\{UserServer};

class PowerDnsController extends Controller
{
    use PowerDnsTrait, CpanelTrait;

    public function getListing(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $nameserver = $serverPackage->company_server_package->company_server->name_servers ? unserialize($serverPackage->company_server_package->company_server->name_servers) : [];
            $userList = $this->wgsReturnDomainData(strtolower($serverPackage->domain), $serverPackage->company_server_package->company_server->ip_address, $nameserver);
            
            $domainList = $this->domainList($serverPackage->company_server_package->company_server_id,  strtolower($serverPackage->name));
            
            if(!is_array($domainList) || !array_key_exists("result", $domainList)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($domainList["result"]['status'] == "0") {
                $error = $domainList["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            if($userList['status'] == 'success')
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => ['records' => $userList['data'], 'domains' => $domainList['result']["data"]], 'message' => 'Zone records has been successfully fetched']);
            return response()->json(['api_response' => 'error', 'status_code' => 200, 'data' => ['records' => $userList['data'], 'domains' => $domainList['result']["data"]], 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
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
            
            $domainId = $this->wgsReturnDomainId($serverPackage->domain); 
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
