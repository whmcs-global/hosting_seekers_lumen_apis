<?php

namespace App\Http\Controllers\v1\plesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PleskX\Api\Client;
use Illuminate\Support\Facades\Validator;
class DomainController extends Controller
{
    private $client;

    function __construct() {
        //parent::__construct();
        $this->client = new Client("51.83.123.186");
        $this->client->setCredentials("root", "1wR2guc3J@rujrOl");
    }
    /*
    Method Name:    getAll
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Get all domains(Webspaces) on Plesk
    Params:         client of plesk
    */
    public function getAll( Client $client ){
        try{
            $all_domains = $client->Webspace()->getAll();
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
    Method Name:    getDetail
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Get detail of the domain
    Params:         client of plesk and request input
    */
    public function getDomain( Client $client , Request $request){
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
   </dataset>
   </get>
</site>
</packet>
STR;
            $response = $this->client->request($request);
            //$domain_detail->stat = $response->data->stat;
            //$domain_detail->disk_usage = $response->data->disk_usage;

            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => $response->data , 'message' => 'Domain fetched successfully.' 
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
    public function delete( Client $client , Request $request ){
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
            $client->Webspace()->delete("name",$request->domain);
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
    public function getPlans( Client $client ){
        try{
            $all_plans = $client->ServicePlan()->getAll();
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
    public function create( Client $client , Request $request ){
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
                $customer = $client->customer()->create([
                    "pname"     =>  $request->customer_name,
                    "login"     =>  $request->customer_name,
                    "passwd"    =>  $request->customer_password,
                    "email"     =>  $request->customer_email
                ]);
            }
            $ip_address = $client->ip()->get();
            $ip_address = reset( $ip_address );
            $domain = $client->webspace()->create([
                    'name'          => $request->domain,
                    'ip_address'    => $ip_address->ipAddress,
                    'owner-guid'      => $customer->guid
                ],[
                    'ftp_login'         =>  $request->customer_name . uniqid(),
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
    Method Name:    createDatabase
    Developer:      Shine Dezign
    Created Date:   2021-12-22 (yyyy-mm-dd)
    Purpose:        Create database 
    Params:         Login name
    */
    public function createDatabase( Request $request ){
        $messages = [
            'domain.required' => 'We need to know domain',
            'role.regex'      => 'Please set one value from (readWrite,readOnly,writeOnly)'
        ];
        $rules = [
            'domain'         => 'required',
            'database_name'     =>  'required',
            'database_user'     =>  'required',
            'user_password'     =>  'required',
            'role'              =>  [
                'required',
                'regex:(readWrite|readOnly|writeOnly)'
            ]
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $api_request = <<<EOL
<packet>
<webspace>
   <get>
    <filter>
        <name>{$request->domain}</name>
    </filter>
    <dataset>
      <hosting/>
    </dataset>
   </get>
</webspace>
</packet>
EOL;
            $response = $this->client->request($api_request);
            
            $database = $this->client->database()->create([
                "webspace-id"   =>  (string)$response->id,
                "name"          =>  $request->database_name,
                "type"          =>  "mysql"
            ]);
            $database_user = $this->client->database()->createUser([
                "db-id"         =>  $database->id,
                "login"         =>  $request->database_user,
                "password"      =>  $request->user_password,
                "role"          =>  $request->role
            ]);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [

                ] , 'message' => 'Database created successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
}