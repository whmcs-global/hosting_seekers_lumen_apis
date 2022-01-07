<?php

namespace App\Http\Controllers\v1\delegate\plesk;

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
    
    public function getPrivileges() {
        $privileges = ["readWrite", "readOnly", "writeOnly"];
        return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $privileges, 'message' => 'Mysql privileges has been fetched']);
    }

    /*
    Method Name:    create
    Developer:      Shine Dezign
    Created Date:   2021-12-22 (yyyy-mm-dd)
    Purpose:        Create database 
    Params:         Login name
    */
    public function create( Request $request ){
        $rules = [
            'name'     =>  'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
		$siteId = $this->getSiteId();
        try{
            $database = $this->client->database()->create([
                "webspace-id"   =>  $siteId,
                "name"          =>  $request->name,
                "type"          =>  "mysql"
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
    public function detail(Request $request){
		$siteId = $this->getSiteId();
        try{
            $domainInfo = $this->client->database()->getAll("webspace-id", $siteId);
            $return_response = [];
            foreach( $domainInfo as $single_domain ){
                
                $response = $this->client->database()->getAllUsers( "db-id" , $single_domain->id);
                $detail = [];
                foreach( $response as $single_user ){
                    if($single_user->id != 0)
                    $detail[] = [
                        'id'    =>  jsencode_userdata($single_user->id),
                        'name' =>  $single_user->login
                    ];
                }
                $return_response[] = [
                    'id'    =>  jsencode_userdata($single_domain->id),
                    'name'  =>  $single_domain->name,
                    'type'  =>  $single_domain->type,
                    'users' => $detail
                ];
            }
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => $return_response , 'message' => 'Database details fetched successfully.'
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
    public function delete( Request $request){
        try{
            $return_response = $this->client->database()->get("id",jsdecode_userdata($request->database_id));
            if( empty($return_response->id) ){
                throw new \Exception("Database not found");
            }
            $this->client->database()->delete("id", jsdecode_userdata($request->database_id));
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
            'username'     =>  'required',
            'password'     =>  'required',
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
                "login" =>  $request->username,
                "password"  =>  $request->password,
                "role"          =>  $request->role
            ]);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => 'User created successfully.'
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
                'api_response'  => 'success', 'status_code' => 200, 'data' => $detail, 'message'   => 'User detail fetched successfully.'
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
    public function deleteUser( Request $request){
        try{
            $this->client->database()->deleteUser( "id" , jsdecode_userdata($request->user_id) );
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