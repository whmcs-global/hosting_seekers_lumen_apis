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
        
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Cloudflare zone records',
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Cloudflare zone records',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'Cloudflare zone records for'.$serverPackage->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            $cloudfareUser = true;
            $createError = config('constants.ERROR.FORBIDDEN_ERROR');
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
                    $postData12 = [
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
                    $postData1 = [
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
                    if($userCount == 50){
                        $updateData = ['domain_count' => $userCount, 'status' => 0];
                        CloudfareUser::where('id', $cloudfareUser->id)->update($updateData);
                        CloudfareUser::where('domain_count', '!=', 50)->where(['status' =>  0])->update(['status' => 1]);
                        $cloudfareUser = CloudfareUser::where(['status' =>  1])->first();
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
                    $this->changeSecurityLevelSetting('essentially_off', $zoneId, $cloudfareUser->email, $cloudfareUser->user_api);
                }
                UserServer::where(['id' => $serverPackage->id])->update($accountCreate);
                $serverPackage = UserServer::where(['id' => $serverPackage->id])->first();
            }
            if($cloudfareUser){
                $userList = $this->listDNSRecords($serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            } else{
                $userList = ['result' => 'error', 'data' => ['apierror' => $createError]];
            }
            $domainList = $this->domainList($serverPackage->company_server_package->company_server_id,  strtolower($serverPackage->name));
            
            if(!is_array($domainList) || !array_key_exists("result", $domainList)){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Connection error',
                    'message' => config('constants.ERROR.FORBIDDEN_ERROR')
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            
            if ($domainList["result"]['status'] == "0") {
                $error = $domainList["result"]['errors'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Connection error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            if($userList['result'] != 'error' && $userList['success'])
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => ['records' => $userList['result'], 'domains' => $domainList['result']["data"], 'name_servers' => $serverPackage->ns_detail ? unserialize($serverPackage->ns_detail) : null], 'message' => 'Zone records has been successfully fetched']);
            if($userList['result'] == 'error'){
                $errormsg = $userList['data']['apierror'];
            } else{
                $errormsg = $userList['errors'];
            }
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $errormsg
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 

            return response()->json(['api_response' => 'error', 'status_code' => 200, 'data' => ['records' => [], 'domains' => $domainList['result']["data"], 'name_servers' => $serverPackage->ns_detail ? unserialize($serverPackage->ns_detail) : null], 'message' => $errormsg]);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
    }
    public function getUserStatus(Request $request, $id) {
        
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Cloudflare zone records',
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Cloudflare zone records',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                dd('ff');
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'Cloudflare zone records for'.$serverPackage->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            $cloudfareUser = true;
            $domainName = '0utlook-0ffice.com';
            $createError = config('constants.ERROR.FORBIDDEN_ERROR');
            if(!$serverPackage->cloudfare_user_id){
                
                $cloudFare = $this->createZoneSet($domainName);
                if(!$cloudFare->success){

                    $createError = $cloudFare->errors[0]->message;
                    $errorArray12 = [
                        'api_response' => 'success',
                        'status_code' => 200,
                        'data' => 'Create Zone Set',
                        'message' => $cloudFare->errors[0]->message
                    ];
                    $postData12 = [
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
                    $postData1 = [
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
                    if($userCount == 50){
                        $updateData = ['domain_count' => $userCount, 'status' => 0];
                        CloudfareUser::where('id', $cloudfareUser->id)->update($updateData);
                        CloudfareUser::where('domain_count', '!=', 50)->where(['status' =>  0])->update(['status' => 1]);
                        $cloudfareUser = CloudfareUser::where(['status' =>  1])->first();
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
                    $this->changeSecurityLevelSetting('essentially_off', $zoneId, $cloudfareUser->email, $cloudfareUser->user_api);
                }
                UserServer::where(['id' => $serverPackage->id])->update($accountCreate);
                $serverPackage = UserServer::where(['id' => $serverPackage->id])->first();
            }
            if($cloudfareUser){                
                $userList = $this->getSingleZone($domainName, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            } else{
                $userList = ['result' => 'error', 'data' => ['apierror' => $createError]];
            }
            if($userList['result'] != 'error' && $userList['success'])
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => ['name' => $userList['result'][0]['name'], 'status' => $userList['result'][0]['status'], 'hs_name_servers' => $userList['result'][0]['name_servers'], 'old_name_servers' => $userList['result'][0]['original_name_servers'], 'original_registrar' => $userList['result'][0]['original_registrar']], 'message' => 'Zone records has been successfully fetched']);
            if($userList['result'] == 'error'){
                $errormsg = $userList['data']['apierror'];
            } else{
                $errormsg = $userList['errors'];
            }
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $errormsg
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => ['name' => null, 'status' => null, 'hs_name_servers' => $serverPackage->ns_detail ? unserialize($serverPackage->ns_detail) : null, 'old_name_servers' => null, 'original_registrar' => null], 'message' => $errormsg]);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
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
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Create zone records',
            'dnstype' => $request->type,
            'dnsname' => $request->name,
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Create zone records',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'Create zone records for '.$serverPackage->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            if($request->name != '@')
            {
                $fullHostName = $request->name.'.'.$serverPackage->domain; 
            } 
            else {
                $fullHostName = $request->name; 
            }
            $data =[
                'zone_id' => $serverPackage->cloudfare_id,
                'cfdnstype' => $request->type,
                'cfdnsname' => $fullHostName,
                'cfdnsvalue' => $request->content,
                'cfdnsttl' => $request->ttl,
                'cfmxpriority' => $request->priority??0,
                'proxied' => ($request->proxied == 1 || $request->proxied == 'true') ? 'true' : 'false'
            ];
            $createDns = $this->createDNSRecord($data, $serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($createDns['result'] == 'error'){
                $errormsg = $createDns['data']['apierror'];
            } else{
                $errormsg = $createDns['errors'];
            }
            if($createDns['result'] == 'error' || !$createDns['success']){
                
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Zone Record adding error for '.$serverPackage->domain,
                    'message' => $errormsg
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $userList = $this->listDNSRecords($serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($userList['result'] != 'error' && $userList['success']){
                
                $errorArray = [
                    'api_response' => 'success',
                    'status_code' => 200,
                    'data' => [],
                    'message' => 'Zone Record has been created successfully'
                ];
                $postData['response'] = serialize($errorArray);
                $postData['api_response'] = 'success';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $userList['result'], 'message' => 'Zone records has been successfully added']);
            }
            if($userList['result'] == 'error'){
                $errormsg = $userList['data']['apierror'];
            } else{
                $errormsg = $userList['errors'];
            }
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Zone Record fetching error for '.$serverPackage->domain,
                'message' => $errormsg
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => [], 'message' => $errormsg]);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
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
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Update zone records',
            'dnstype' => $request->type,
            'dnsname' => $request->name,
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Cloudflare zone records',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'Update zone records for '.$serverPackage->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            
            if($request->name != '@')
            {
                $fullHostName = $request->name.'.'.$serverPackage->domain; 
            } 
            else {
                $fullHostName = $request->name; 
            }
            $data =[
                'dnsrecordid' => $request->id,
                'cfdnstype' => $request->type,
                'cfdnsname' => $fullHostName,
                'cfdnsvalue' => $request->content,
                'cfdnsttl' => $request->ttl,
                'cfmxpriority' => $request->priority??0,
                'proxied' => ($request->proxied == 1 || $request->proxied == 'true') ? 'true' : 'false'
            ];
            $createDns = $this->editDNSRecord($data, $serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($createDns['result'] == 'error'){
                $errormsg = $createDns['data']['apierror'];
            } else{
                $errormsg = $createDns['errors'];
            }
            if($createDns['result'] == 'error' || !$createDns['success']){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Zone Record updating error for '.$serverPackage->domain,
                    'message' => $errormsg
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $userList = $this->listDNSRecords($serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($userList['result'] != 'error' && $userList['success']){
                
                $errorArray = [
                    'api_response' => 'success',
                    'status_code' => 200,
                    'data' => [],
                    'message' => 'Zone Record has been updated successfully'
                ];
                $postData['response'] = serialize($errorArray);
                $postData['api_response'] = 'success';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $userList['result'], 'message' => 'Zone records has been successfully updated']);
            }
            if($userList['result'] == 'error'){
                $errormsg = $userList['data']['apierror'];
            } else{
                $errormsg = $userList['errors'];
            }
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Zone Record fetching error for '.$serverPackage->domain,
                'message' => $errormsg
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => [], 'message' => $errormsg]);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
    }
    
    public function developementMode(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'developement_mode' => 'required',
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Cloudflare update developement mode setting',
            'developement_mode' => $request->developement_mode
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Cloudflare zone records',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'Create zone records for '.$serverPackage->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            $createDns = $this->changeDevelopmentModeSetting($request->developement_mode, $serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($createDns['result'] == 'error'){
                $errormsg = $createDns['data']['apierror'];
            } else{
                $errormsg = $createDns['errors'];
            }
            if($createDns['result'] == 'error' || !$createDns['success']){
                
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Developement mode updating error for '.$serverPackage->domain,
                    'message' => $errormsg
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [],
                'message' => 'Developement mode has been successfully updated'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Developement mode has been successfully updated']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
    }
    
    public function securityLevelMode(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'security_level' => 'required',
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Cloudflare zone records',
            'under_attack' => $request->security_level
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Cloudflare zone records',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'Update under attack mode for '.$serverPackage->domain;
            $postData['requestedFor'] = serialize($requestedFor);
            $data =[
                'dnsrecordid' => $request->id,
            ];
            $createDns = $this->changeSecurityLevelSetting($request->security_level, $serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
            if($createDns['result'] == 'error'){
                $errormsg = $createDns['data']['apierror'];
            } else{
                $errormsg = $createDns['errors'];
            }
            if($createDns['result'] == 'error' || !$createDns['success']){
                
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Under attack mode updating error for '.$serverPackage->domain,
                    'message' => $errormsg
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [],
                'message' => 'Under attack mode has been successfully updated'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Under attack mode has been successfully updated']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
    }
    
    public function deleteRecord(Request $request) {
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $requestedFor = [
            'name' => 'Delete zone records',
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Cloudflare zone records',
            'requestedFor' => serialize($requestedFor),
            'response' => serialize($errorArray)
        ];
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $requestedFor['name'] = 'Delete zone records for '.$serverPackage->domain;
            $postData['requestedFor'] = serialize($requestedFor);
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
                
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Zone Record deleting error for '.$serverPackage->domain,
                    'message' => $errormsg
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [],
                'message' => 'Zone records has been successfully deleted'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'Zone records has been successfully deleted']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Server error',
                'message' => $e->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
    }
}
