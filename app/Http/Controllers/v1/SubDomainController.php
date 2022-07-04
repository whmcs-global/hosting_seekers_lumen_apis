<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{BlockedIp, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait, CloudfareTrait};

class SubDomainController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait, CloudfareTrait;
    public function getSubDomain(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->getSubDomains($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account login error', 'message' => $error]);
            }   
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']['data'], 'message' => 'Subdomains has been successfully fetched']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
    
    public function addSubDomain(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'domain' => 'required',
            'subdomain' => 'required',
            'homedir' => 'required'
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
            $domain = $request->subdomain.'.'.$request->domain;
            $accCreated = $this->createSubDomain($serverPackage->company_server_package->company_server_id, $domain, $request->homedir);
            if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                $error = $accCreated['metadata']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account login error', 'message' => $error]);
            }   

            $zoneId = $serverPackage->cloudfare_id;
            if(!$serverPackage->cloudfare_user_id){
                $domainName = $serverPackage->domain;
                $createZone = $this->createZoneSet($domainName);
                if(!$createZone->success){

                    $createError = $createZone->errors[0]->message;
                    $errorArray12 = [
                        'api_response' => 'success',
                        'status_code' => 200,
                        'data' => 'Create Zone Set',
                        'message' => $createZone->errors[0]->message
                    ];
                    $postDat12 = [
                        'userId' => jsencode_userdata($request->userid),
                        'api_response' => 'success',
                        'logType' => 'cPanel',
                        'module' => 'Create zone set for '.$domainName,
                        'requestedFor' => serialize(['name' => 'Create zone set', 'domain' => $domainName]),
                        'response' => serialize($errorArray12)
                    ];
                    $postData12['response'] = serialize($errorArray12);
                    //Hit node api to save logs
                    hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData12); 
                }
                $zoneInfo = $this->getSingleZone($domainName);
                $accountCreate = [];
                $cloudfareUser = null;
                if($zoneInfo['success'] && $zoneInfo['result']){
                    $errorArray1 = [
                        'api_response' => 'success',
                        'status_code' => 200,
                        'data' => 'Create Zone Set',
                        'message' => 'Zone Set has been created on cloudflare'
                    ];
                    $postDat1 = [
                        'userId' => jsencode_userdata($request->userid),
                        'api_response' => 'success',
                        'logType' => 'cPanel',
                        'module' => 'Create zone set for '.$domainName,
                        'requestedFor' => serialize(['name' => 'Create zone set', 'domain' => $domainName]),
                        'response' => serialize($errorArray1)
                    ];
                    $postData1['response'] = serialize($errorArray1);
                    //Hit node api to save logs
                    hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData1); 
                    $accountCreate['ns_detail'] = serialize($zoneInfo['result'][0]['name_servers']);
                    
                    $cloudfareUser = CloudfareUser::where('status', 1)->first();
                    $accountCreate['cloudfare_id'] = $zoneId = $zoneInfo['result'][0]['id'];
                    $userCount = UserServer::where(['cloudfare_user_id' => $cloudfareUser->id ])->count();
                    $updateData = ['domain_count' => $userCount+1];
                    if($userCount == 100){
                        $updateData = ['domain_count' => $userCount, 'status' => 0];
                        CloudfareUser::where('id', $cloudfareUser->id)->update($updateData);
                        $cloudfareUser = CloudfareUser::where('domain_count', '!=', 100)->where(['status' =>  0])->update(['status' => 1]);
                        $cloudfareUser = CloudfareUser::where('status', 1)->first();
                    } else{
                        CloudfareUser::where('id', $cloudfareUser->id)->update($updateData);
                    }
                    $accountCreate['cloudfare_user_id'] = $cloudfareUser->id;
                    $dnsData = [
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => $domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ],
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'www.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ],
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'mail.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ],
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'webmail.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ],
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'cpanel.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ],
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'ftp.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ]
                    ];
                    foreach ($dnsData as $dnsVal) {
                        $createDns = $this->createDNSRecord($dnsVal, $zoneId, $cloudfareUser->email, $cloudfareUser->user_api);
                    }
                }
                UserServer::where(['id' => $serverPackage->id])->update($accountCreate);
                $serverPackage = UserServer::where(['id' => $serverPackage->id])->first();
            } 
            if($zoneId){
                $subDomain = $request->subdomain;
                $dnsData = [
                    [
                        'zone_id' => $zoneId,
                        'cfdnstype' => 'A',
                        'cfdnsname' => $subDomain,
                        'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                        'cfdnsttl' => '1',
                        'proxied' => 'true'
                    ],
                    [
                        'zone_id' => $zoneId,
                        'cfdnstype' => 'A',
                        'cfdnsname' => 'webmail.'.$subDomain,
                        'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                        'cfdnsttl' => '1',
                        'proxied' => 'true'
                    ],
                    [
                        'zone_id' => $zoneId,
                        'cfdnstype' => 'A',
                        'cfdnsname' => 'cpanel.'.$subDomain,
                        'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                        'cfdnsttl' => '1',
                        'proxied' => 'true'
                    ],
                    [
                        'zone_id' => $zoneId,
                        'cfdnstype' => 'A',
                        'cfdnsname' => 'ftp.'.$subDomain,
                        'cfdnsvalue' => $serverPackage->company_server_package->company_server->ip_address,
                        'cfdnsttl' => '1',
                        'proxied' => 'true'
                    ]
                ];
                foreach ($dnsData as $dnsVal) {
                    $createDns = $this->createDNSRecord($dnsVal, $zoneId, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
                }
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Subdomain has been successfully created']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
    
    public function updateSubDomain(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'domain' => 'required',
            'subdomain' => 'required',
            'homedir' => 'required'
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
            $domain = $request->domain;
            $accCreated = $this->changeRootDir($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $domain, $request->subdomain, $request->homedir);
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data'][0]) && 0 == $accCreated['cpanelresult']["data"][0]['result']) {
                $error = $accCreated['cpanelresult']['data'][0]["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account login error', 'message' => $error]);
            } 
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Subdomain has been successfully updated']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
    
    public function deleteSubDomain(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'subdomain' => 'required',
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

            $accCreated = $this->delSubDomain($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->subdomain);
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data'][0]) && 0 == $accCreated['cpanelresult']["data"][0]['result']) {
                $error = $accCreated['cpanelresult']['data'][0]["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account login error', 'message' => 'Failed to find the domain(s): "'.$request->subdomain.'"']);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Subdomain has been successfully deleted']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
}
