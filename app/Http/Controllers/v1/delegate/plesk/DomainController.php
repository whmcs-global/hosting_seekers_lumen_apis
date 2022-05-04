<?php

namespace App\Http\Controllers\v1\delegate\plesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PleskX\Api\Client;
use App\Models\{UserServer};
use Illuminate\Support\Facades\Validator;
use App\Traits\{PleskTrait, CloudfareTrait};
class DomainController extends Controller
{
    use PleskTrait, CloudfareTrait;
    private $client;

    function __construct() {
        $this->runPleskQuery();
    }
    /*
    Method Name:    getAll
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Get all domains(Webspaces) on Plesk
    Params:         client of plesk
    */
    public function getAll( ){
        try{
            $all_domains = $this->client->Webspace()->getAll();
            $response_data = [];
            foreach( $all_domains as $single_domain ){
                $response_data[] = [
                    'name'  =>  $single_domain->name,
                    'guid'  =>  $single_domain->guid
                ];
            }
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => ['all_domains' => $response_data], 'message' => 'Domains fetched successfully.' 
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
    /*
    Method Name:    getDomain
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Get detail of the domain
    Params:         client of plesk and request input
    */
    public function getDomain( Request $request ){
        $serverId = jsdecode_userdata(request()->cpanel_server);
        $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
        if(!$server)
        return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        try{
            $siteId = $this->getSiteId();
            $request = <<<STR
            <packet>
                <site>
                <get>
                <filter>
                    <name>{$server->domain}</name>
                </filter>
                <dataset>
                    <disk_usage/>
                    <stat/>
                    <gen_info/>
                    <hosting/>
                    <prefs/>
                </dataset>
                </get>
                </site>
            </packet>
            STR;
            $response = $this->client->request($request);
            $response_data = [
                'document_root' =>  (string)$response->data->hosting->vrt_hst->www_root,
                'ip'            =>  (string)$response->data->hosting->vrt_hst->ip_address,
                'account_stats' =>  [
                    [
                        'item'  =>  'Monthly Bandwidth Transfer',
                        'name'  =>  'traffic',
                        'value' =>  (string)$response->data->stat->traffic
                    ],[
                        'item'  =>  'Sub domain',
                        'name'  =>  'subdom',
                        'value' =>  (string)$response->data->disk_usage->subdomains
                    ],[
                        'item'  =>  'Database',
                        'name'  =>  'db',
                        'value' =>  (string)$response->data->stat->db
                    ],[
                        'item'  =>  'Disk usage',
                        'name'  =>  'db',
                        'value' =>  number_format($response->data->disk_usage->httpdocs / 1024 / 1024 , 2) . " MB"
                    ]
                ]
            ];
            if( isset($response->data->hosting->vrt_hst->property) ){
                foreach( $response->data->hosting->vrt_hst->property as $single_property ){
                    if( $single_property->name == "www_root" ){
                        $response_data['document_root'] = (string)$single_property->value;
                        break;
                    }
                }
            }
            
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => $response_data , 'message' => 'Domain fetched successfully.' 
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
    /*
    Method Name:    dnsInfo
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Get detail of the domain
    Params:         client of plesk and request input
    */
    public function dnsInfo( Request $request ){
        $serverId = jsdecode_userdata(request()->cpanel_server);
        $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
        if(!$server)
        return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        try{
            $siteId = $this->getSiteId();
            $request = <<<STR
            <packet>
                <site>
                <get>
                <filter>
                    <name>{$server->domain}</name>
                </filter>
                <dataset>
                    <hosting/>
                </dataset>
                </get>
                </site>
            </packet>
            STR;
            $response = $this->client->request($request);
            $response_data = [
                'document_root' =>  (string)$response->data->hosting->vrt_hst->www_root,
                'ip'            =>  (string)$response->data->hosting->vrt_hst->ip_address,
                'dns' =>  unserialize($server->company_server_package->company_server->name_servers)
            ];
            if( isset($response->data->hosting->vrt_hst->property) ){
                foreach( $response->data->hosting->vrt_hst->property as $single_property ){
                    if( $single_property->name == "www_root" ){
                        $response_data['document_root'] = (string)$single_property->value;
                        break;
                    }
                }
            }
            
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => $response_data , 'message' => 'Domain DNS fetched successfully.' 
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
    /*
    Method Name:    delete
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Delete the domain
    Params:         client of plesk
    */
    public function delete( Request $request ){
        $messages = [
            'domain.required' => 'We need to know domain'
        ];
        $rules = [
            'domain' => 'required|string'
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $this->client->Webspace()->delete("name",$request->domain);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'Domain deleted successfully.' 
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
    /*
    Method Name:    getPlans
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Get plans
    Params:         client of plesk
    */
    public function getPlans(  ){
        try{
            $all_plans = $this->client->ServicePlan()->getAll();
            $response_plans = [];
            foreach( $all_plans as $single_plan ){
                $response_plans[] = [
                    'id'    =>  $single_plan->id,
                    'name'  =>  $single_plan->name,
                    'guid'  =>  $single_plan->guid
                ];
            }
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [
                    'all_plans' => $response_plans
                ], 'message' => 'Domains fetched successfully.' 
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }

    /*
    Method Name:    create
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Create the customer and domain(Webspace)
    Params:         client of plesk
    */
    public function create( Request $request ){
        $messages = [
            'domain.required' => 'We need to know domain'
        ];
        $rules = [
            'domain' => 'required|string',
            'plan_name'         =>  'required',
            'customer_name'     =>  'required',
            'customer_email'    =>  'required',
            'customer_password' =>  'required'
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $customer = $this->check_customer_exist($request->customer_name);
            if( empty($customer) ){
                $customer = $this->client->customer()->create([
                    "pname"     =>  $request->customer_name,
                    "login"     =>  $request->customer_name,
                    "passwd"    =>  $request->customer_password,
                    "email"     =>  $request->customer_email
                ]);
            }
            $ip_address = $this->client->ip()->get();
            $ip_address = reset( $ip_address );
            $domain = $this->client->webspace()->create([
                    'name'          => $request->domain,
                    'ip_address'    => $ip_address->ipAddress,
                    'owner-guid'      => $customer->guid
                ],[
                    'ftp_login'         =>  preg_replace('/[^a-zA-Z0-9_ -]/s','',strtolower($request->customer_name) ),
                    'ftp_password'      =>  $request->customer_password
                ]
            );
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'Domain created successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }

    /*
    Method Name:    check_customer_exist(HELPER)
    Developer:      Shine Dezign
    Created Date:   2021-12-22 (yyyy-mm-dd)
    Purpose:        Check customer with login name exist
    Params:         Login name
    */
    public function check_customer_exist( $name ){
        try{
            $c = $this->client->customer()->get("login",$name);
            return $c;
        }catch(\Exception $e){
            return false;
        }
    }

    /*
    Method Name:    createSubdomain(HELPER)
    Developer:      Shine Dezign
    Created Date:   2021-12-27 (yyyy-mm-dd)
    Purpose:        Create sub domain
    Params:         Request input
    */
    public function createSubdomain(Request $request){
		$validator = Validator::make($request->all(),[
            'domain' => 'required',
            'subdomain' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try{
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $api_request = <<<EOL
            <packet>
            <subdomain>
                <add>
                <parent>{$request->domain}</parent>
                <name>{$request->subdomain}</name>
                <property>
                    <name>www_root</name>
                    <value>/{$request->homedir}</value>
                </property>
                </add>
            </subdomain>
            </packet>
            EOL;
            $response = $this->client->request($api_request);
            
            $accountCreate = [];
            $accountCreate['subdomain'] = $request->subdomain;
            $accountCreate['domain'] = $request->domain;
            $accountCreate['ipaddress'] = $serverPackage->company_server_package->company_server->ip_address;
            
            $zoneId = $serverPackage->cloudfare_id;
            if(!$serverPackage->cloudfare_user_id){
                $domainName = $serverPackage->domain;
                $cloudFare = $this->createZoneSet($domainName);
                $zoneInfo = $this->getSingleZone($domainName);
                $accountCreate = [];
                $cloudfareUser = null;
                if($zoneInfo['success']){
                    $accountCreate['ns_detail'] = serialize($zoneInfo['result'][0]['name_servers']);
                    
                    $cloudfareUser = CloudfareUser::where('status', 1)->first();
                    $accountCreate['cloudfare_id'] = $zoneId = $zoneInfo['result'][0]['id'];
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
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'Sub Domain created successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    public function updateSubdomain(Request $request){
		$validator = Validator::make($request->all(),[
            'subdomain' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try{
            $api_request = <<<EOL
            <packet>
            <subdomain>
            <set>
            <filter>
            <name>{$request->subdomain}</name>
            </filter>
            <property>
                <name>www_root</name>
                <value>/{$request->homedir}</value>
            </property>
            </set>
            </subdomain>
            </packet>
            EOL;
            $response = $this->client->request($api_request);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'Sub Domain updated successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    public function subDomains(Request $request){
        try{
            $serverId = jsdecode_userdata(request()->cpanel_server);
            $server = UserServer::where(['user_id' => request()->userid, 'id' => $serverId])->first();
            if(!$server)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
                
            $allSubdomains = $this->client->Subdomain()->getAll("parent-name",$server->domain);
            $return_response = [];
            foreach( $allSubdomains as $domain ){
                $return_response[] = [
                    'parent_domain'     =>  $domain->parent,
                    'domain'     =>  $domain->name,
                    'domain_id' =>  jsencode_userdata( $domain->id ),
                    'document_root' =>  $domain->properties['www_root']
                ];
            }

            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => $return_response, 'message' => 'Sub Domain created successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }

    public function deleteSubDomain(Request $request){
        $rules = [
            'subdomain'     =>  'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $serverId = jsdecode_userdata(request()->cpanel_server);
            $server = UserServer::where(['user_id' => request()->userid, 'id' => $serverId])->first();
            if(!$server)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $this->client->Subdomain()->delete("name",$request->subdomain);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [
                    
                ], 'message'        => 'Sub Domain deleted successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }

    }

    public function loginSession(Request $request){
        try{
            $serverId = jsdecode_userdata(request()->cpanel_server);
            $server = UserServer::where(['user_id' => request()->userid, 'id' => $serverId])->first();
            if(!$server)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $request = <<<STR
            <packet>
            <server>
                <create_session>
                    <login>{$server->name}</login>
                </create_session>
            </server>
            </packet>
            STR;
            $response = $this->client->request($request);
            $token = (string)$response->id;
            $ip_address = $this->client->ip()->get();
            $ip_address = reset( $ip_address )->ipAddress;
            $parameters = [
                'PLESKSESSID'   =>  $token
            ];
            $login_url = $server->company_server_package->company_server->host.":8443/enterprise/rsession_init.php";

            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [
                    'session_token' =>  $token,
                    'redirect_to'   =>  "$login_url?".http_build_query($parameters)
                ], 'message'        => 'Session token generated successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }

    public function testing(Request $request){
       echo (jsencode_userdata( 23 ));
    }
}