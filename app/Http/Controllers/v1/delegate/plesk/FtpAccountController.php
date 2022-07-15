<?php

namespace App\Http\Controllers\v1\delegate\plesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{UserServer};
use PleskX\Api\Client;
use Illuminate\Support\Facades\Validator;
use App\Traits\PleskTrait;
class FtpAccountController extends Controller
{
    use PleskTrait;
    private $client;

    function __construct() {
        $this->runPleskQuery();
    }

    /*
    Method Name:    create
    Developer:      Shine Dezign
    Created Date:   2021-12-22 (yyyy-mm-dd)
    Purpose:        Create FTP account 
    Params:         Input request
    */
    public function create(Request $request){
        $messages = [
            'domain.required' => 'We need to know domain'
        ];
        $rules = [
            'domain'         => 'required',
            'username'          =>  'required',
            'password'      =>  'required',
            'quota'         =>  'required|numeric',
            'quotasize' => [
                'required',
                'regex:(MB|GB|TB|PB)'
            ]
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        //$quota = $request->quota * 1024 * 1024;
        try{
            $quota = 0;
            if('MB' == $request->quotasize){
                $quota = $request->quota * 1024 * 1024;
            } elseif('GB' == $request->quotasize){
                $quota = $request->quota * 1024 * 1024 * 1024;
            } elseif('TB' == $request->quotasize){
                $quota = $request->quota * 1024 * 1024 * 1024 * 1024;
            } elseif('PB' == $request->quotasize){
                $quota = $request->quota * 1024 * 1024 * 1024 * 1024 * 1024;
            }

            $api_request = <<<EOL
            <packet>
                <ftp-user>
                <add>
                <name>{$request->username}</name>
                <password>{$request->password}</password>
                <home/>
                <quota>{$quota}</quota>
                    <permissions>
                    <read>true</read>
                    <write>true</write>
                    </permissions>
                    <webspace-name>{$request->domain}</webspace-name>
                </add>
                </ftp-user>
            </packet>
            EOL;
            $response = $this->client->request($api_request);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'FTP account created successfully.' 
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => config('constants.ERROR.FORBIDDEN_ERROR')
            ]);
        }
    }

    /*
    Method Name:    detail
    Developer:      Shine Dezign
    Created Date:   2021-12-23 (yyyy-mm-dd)
    Purpose:        Get detail of FTP account
    Params:         Input request
    */
    public function detail(Request $request,$ftp_id = null){
        $serverId = jsdecode_userdata(request()->cpanel_server);
        $server = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
        if(!$server)
        return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        try{
            if( empty($ftp_id) ){
                $api_request = <<<EOL
                <packet>
                    <ftp-user>
                    <get>
                    <filter>
                        <webspace-name>{$server->domain}</webspace-name>
                    </filter>
                    </get>
                    </ftp-user>
                </packet>
                EOL;
                $response = $this->client->request($api_request,2);
                $ftp_user = "ftp-user";
                $response_data = [];
                foreach( $response->$ftp_user->get->result as $single_result ){
                    if($single_result->name != '')
                    $response_data[] = [
                        'id'    =>  jsencode_userdata((string)$single_result->id),
                        'name'    =>  (string)$single_result->name,
                        'home'    =>  (string)$single_result->home,
                    ];
                }
            }else{
                $ftp_id = jsdecode_userdata($ftp_id);
                $api_request = <<<EOL
                <packet>
                    <ftp-user>
                    <get>
                    <filter>
                        <id>{$ftp_id}</id>
                    </filter>
                    </get>
                    </ftp-user>
                </packet>
                EOL;
                $response_data = $this->client->request($api_request);
                $response_data = [
                    'id'    =>  jsencode_userdata((string)$response_data->id),
                    'name'    =>  (string)$response_data->name,
                    'home'    =>  (string)$response_data->home,
                ];
            }
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => $response_data, 'message' => 'FTP detail fetched successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => config('constants.ERROR.FORBIDDEN_ERROR')
            ]);
        }
       
    }

    /*
    Method Name:    Update
    Developer:      Shine Dezign
    Created Date:   2021-12-23 (yyyy-mm-dd)
    Purpose:        Update FTP account
    Params:         Input request
    */
    public function update( Request $request ){
        $rules = [
            'username'          =>  'required',
            'password'      =>  'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $api_request = <<<EOL
            <packet>
            <ftp-user>
            <set>
            <filter>
                <name>{$request->username}</name>
            </filter>
            <values>
                <password>{$request->password}</password>
            </values>
            </set>
            </ftp-user>
            </packet>
            EOL;
            $response_data = $this->client->request($api_request);
            if($response_data->status == 'ok')
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'FTP account updated successfully.'
            ]);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => config('constants.ERROR.FORBIDDEN_ERROR')
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => config('constants.ERROR.FORBIDDEN_ERROR')
            ]);
        }
    }

    /*
    Method Name:    delete
    Developer:      Shine Dezign
    Created Date:   2021-12-23 (yyyy-mm-dd)
    Purpose:        Delete FTP account
    Params:         Input request
    */
    public function delete( Request $request ){
        $rules = [
            'user'          =>  'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $api_request = <<<EOF
            <packet>
                <ftp-user>
                    <del>
                        <filter>
                            <name>{$request->user}</name>
                        </filter>
                    </del>
                </ftp-user>
            </packet>
            EOF;
            $response_data = $this->client->request($api_request);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'FTP account deleted successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => config('constants.ERROR.FORBIDDEN_ERROR')
            ]);
        }
    }
}
