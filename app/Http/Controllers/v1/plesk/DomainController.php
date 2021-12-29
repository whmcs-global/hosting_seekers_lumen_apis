<?php

namespace App\Http\Controllers\v1\plesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PleskX\Api\Client;
use Illuminate\Support\Facades\Validator;
use App\Traits\PleskTrait;
class DomainController extends Controller
{
    use PleskTrait;
    private $client;

    function __construct() {
        $this->runQuery();
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
    public function getDomain( Request $request){
        $messages = [
            'cpanel_server.required' => 'We need to know cpanel_server'
        ];
        $rules = [
            'cpanel_server' => 'required|string'
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $request = <<<STR
<packet>
<site>
   <get>
   <filter>
    <name>{$request->domain}</name>
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
            $webspace_id = "webspace-id";
            $domain_id = (string)$response->data->gen_info->$webspace_id;
            $response_data = [
                'domain_id'     =>  jsencode_userdata($domain_id),
                'document_root' =>  '',
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
            
            
            /*foreach( $response->data->stat as $key_stat =>  $value ){
                foreach( $value as $x => $v ){
                    $response_data['api_response'][] = [
                        'item'      =>  $x,
                        'name'      =>  $x,
                        'count'     =>  (string)$v,
                        "max"       => "",
                        "percent"   => 0,
                        "value"     => null,
                        "units"     => null
                    ];
                }
            }*/

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
        $rules = [
            'parent_domain'         =>  'required',
            'sub_domain'     =>  'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $subdomain = $this->client->Subdomain()->create([
                "parent"    =>  $request->parent_domain,
                "name"      =>  $request->sub_domain
            ]);
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

    public function subDomainDetail(Request $request){
        $rules = [
            'sub_domain'     =>  'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $info = $this->client->Subdomain()->get("name",$request->sub_domain);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [
                    'parent_domain'     =>  $info->parent,
                    'domain'     =>  $info->name,
                    'domain_id' =>  jsencode_userdata( $info->id ),
                    'document_root' =>  $info->properties['www_root'],
                ] , 'message' => 'Sub Domain created successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
        
    }

    public function subDomains(Request $request){
        $rules = [
            'parent_domain'     =>  'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $all_subdomains = $this->client->Subdomain()->getAll("parent-name",$request->parent_domain);
            $return_response = [];
            foreach( $all_subdomains as $domain ){
                $return_response[] = [
                    'parent_domain'     =>  $domain->parent,
                    'domain'     =>  $domain->name,
                    'domain_id' =>  jsencode_userdata( $domain->id ),
                    'document_root' =>  $domain->properties['www_root']
                ];
            }

            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [
                    'sub_domains'   =>   $return_response
                ], 'message'        => 'Sub Domain created successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }

    public function deleteSubDomain(Request $request){
        $rules = [
            'sub_domain'     =>  'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $this->client->Subdomain()->delete("name",$request->sub_domain);
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
        $rules = [
            'customer_name'     =>  'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $request = <<<STR
<packet>
  <server>
    <create_session>
        <login>{$request->customer_name}</login>
    </create_session>
  </server>
</packet>
STR;
            $response = $this->client->request($request);
            $token = (string)$response->id;
            $ip_address = $this->client->ip()->get();
            $ip_address = reset( $ip_address )->ipAddress;
            $ip_address = "goofy-gates.51-83-123-186.plesk.page";
            $parameters = [
                'PHPSESSID'     =>  $token,
                'PLESKSESSID'   =>  $token
            ];
            $login_url = "https://$ip_address:8443/enterprise/rsession_init.php";
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