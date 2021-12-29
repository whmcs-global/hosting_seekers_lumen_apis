<?php

namespace App\Http\Controllers\v1\plesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PleskX\Api\Client;
use Illuminate\Support\Facades\Validator;
use App\Traits\PleskTrait;
class DatabaseController extends Controller
{
    use PleskTrait;
    private $client;

    function __construct() {
        $this->runQuery();
    }

    /*
    Method Name:    create
    Developer:      Shine Dezign
    Created Date:   2021-12-22 (yyyy-mm-dd)
    Purpose:        Create database 
    Params:         Login name
    */
    public function create( Request $request ){
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

    /*
    Method Name:    detail
    Developer:      Shine Dezign
    Created Date:   2021-12-23 (yyyy-mm-dd)
    Purpose:        Get all database details or specific database information
    Params:         Input request
    */
    public function detail(Request $request,$database_id = null){
        $messages = [
            'domain.required' => 'We need to know domain'
        ];
        $rules = [
            'domain'         => 'required'
        ];
        try{
            if( empty($database_id) ){
                $validator = Validator::make($request->all(), $rules, $messages);
                if ($validator->fails()) {
                    return response()->json([
                        'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
                    ]);
                }
                $domain_info = $this->client->database()->getAll("webspace-name",$request->domain);
                $return_response = [];
                foreach( $domain_info as $single_domain ){
                    $return_response[] = [
                        'id'    =>  jsencode_userdata($single_domain->id),
                        'name'  =>  $single_domain->name,
                        'type'  =>  $single_domain->type,
                        'webspaceid'   =>   $single_domain->webspaceId
                    ];
                }
            }else{
                $return_response = $this->client->database()->get("id",jsdecode_userdata($database_id));
                //var_dump( empty($return_response->id) );exit;
                if( empty($return_response->id) ){
                    throw new \Exception("Database not found");
                }
                $return_response = [
                    'id'    =>  jsencode_userdata($return_response->id),
                    'name'  =>  $return_response->name,
                    'type'  =>  $return_response->type,
                    'webspaceid'   =>   $return_response->webspaceId
                ];
            }
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [
                    'detail'    =>  $return_response
                ] , 'message' => 'Database details fetched successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
       
    }

    /*
    Method Name:    deleteDatabase
    Developer:      Shine Dezign
    Created Date:   2021-12-23 (yyyy-mm-dd)
    Purpose:        Delete database 
    Params:         Input request
    */
    public function delete( Request $request , $database_id){
        try{
            $return_response = $this->client->database()->get("id",jsdecode_userdata($database_id));
            if( empty($return_response->id) ){
                throw new \Exception("Database not found");
            }
            $this->client->database()->delete("id", jsdecode_userdata($database_id));
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'Database deleted successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }

    /*
    Method Name:    createUser
    Developer:      Shine Dezign
    Created Date:   2021-12-23 (yyyy-mm-dd)
    Purpose:        Delete database 
    Params:         Input request
    */
    public function createUser( Request $request ){
        $messages = [
            'role.regex'      => 'Please set one value from (readWrite,readOnly,writeOnly)'
        ];
        $rules = [
            'database_id'         => ['required'],
            'database_user'     =>  'required',
            'user_password'     =>  'required',
            'role'              =>  [
                'required',
                'regex:(readWrite|readOnly|writeOnly)'
            ]
        ];
        $validator = Validator::make($request->all(), $rules,$messages);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $user = $this->client->database()->createUser([
                "db-id" =>  jsdecode_userdata($request->database_id),
                "login" =>  $request->database_user,
                "password"  =>  $request->user_password
            ]);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => $user , 'message' => 'User created successfully.'
            ]);

        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }

    /*
    Method Name:    User details
    Developer:      Shine Dezign
    Created Date:   2021-12-23 (yyyy-mm-dd)
    Purpose:        Get all database user or specific user information
    Params:         Input request
    */
    public function userDetail( Request $request , $user_id = null ){
        try{
            if( $user_id ){
                $response = $this->client->database()->getUser( "id" , jsdecode_userdata($user_id) );
                $detail = [
                    'id'    =>  jsencode_userdata($response->id),
                    'name' =>  $response->login
                ];
            }else{
                $rules = [
                    'database_id'         => 'required'
                ];
                $validator = Validator::make($request->all(), $rules);
                if ($validator->fails()) {
                    return response()->json([
                        'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
                    ]);
                }
                $response = $this->client->database()->getAllUsers( "db-id" , jsdecode_userdata($request->database_id) );
                $detail = [];
                foreach( $response as $single_user ){
                    $detail[] = [
                        'id'    =>  jsencode_userdata($single_user->id),
                        'name' =>  $single_user->login
                    ];
                }
            }
            return response()->json([
                'api_response'  => 'success', 'status_code' => 200, 'data' => [
                    'detail'    =>  $detail
                ] , 'message'   => 'User detail fetched successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }

    /*
    Method Name:    deleteUser
    Developer:      Shine Dezign
    Created Date:   2021-12-23 (yyyy-mm-dd)
    Purpose:        Delete user 
    Params:         Input request
    */
    public function deleteUser( Request $request , $user_id ){
        try{
            $this->client->database()->deleteUser( "id" , jsdecode_userdata($user_id) );
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'User deleted successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }

    /*
    Method Name:    updateUser
    Developer:      Shine Dezign
    Created Date:   2021-12-23 (yyyy-mm-dd)
    Purpose:        Update user 
    Params:         Input request
    */
    public function updateUser( Request $request ){
        $messages = [
            'id.required' => 'We need to know user id'
        ];
        $rules = [
            'id'         => 'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $update_user = $request->all();
            $update_user['id'] = jsdecode_userdata($request->id);
            $this->client->database()->updateUser( $update_user );
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'User updated successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
}