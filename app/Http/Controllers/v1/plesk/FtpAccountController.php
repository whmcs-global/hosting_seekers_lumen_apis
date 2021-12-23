<?php

namespace App\Http\Controllers\v1\plesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PleskX\Api\Client;
use Illuminate\Support\Facades\Validator;

class FtpAccountController extends Controller
{
    function __construct() {
        $this->client = new Client("51.83.123.186");
        $this->client->setCredentials("root", "1wR2guc3J@rujrOl");
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
            'domain_id.required' => 'We need to know domain_id'
        ];
        $rules = [
            'domain_id'         => 'required|numeric',
            'ftp_name'          =>  'required',
            'ftp_password'      =>  'required'
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
<ftp-user>
<add>
<name>{$request->ftp_name}</name>
<password>{$request->ftp_password}</password>
<home/>
   <permissions>
     <read>true</read>
     <write>true</write>
    </permissions>
<webspace-id>{$request->domain_id}</webspace-id>
   
   
   
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
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
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
        try{
            $messages = [
                'domain_id.required' => 'We need to know domain id'
            ];
            $rules = [
                'domain_id'         => 'required'
            ];
        
            if( empty($ftp_id) ){
                $validator = Validator::make($request->all(), $rules, $messages);
                if ($validator->fails()) {
                    return response()->json([
                        'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
                    ]);
                }
                $api_request = <<<EOL
                <packet>
<ftp-user>
<get>
   <filter>
      <webspace-id>{$request->domain_id}</webspace-id>
   </filter>
</get>
</ftp-user>
</packet>
EOL;
                $response = $this->client->request($api_request,2);
                $ftp_user = "ftp-user";
                $response_data = [];
                foreach( $response->$ftp_user->get->result as $single_result ){
                    $response_data[] = [
                        'id'    =>  (string)$single_result->id,
                        'name'    =>  (string)$single_result->name,
                        'home'    =>  (string)$single_result->home,
                    ];
                }
            }else{
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
                    'id'    =>  (string)$response_data->id,
                    'name'    =>  (string)$response_data->name,
                    'home'    =>  (string)$response_data->home,
                ];
            }
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [
                    'detail'    =>  $response_data
                ] , 'message' => 'FTP detail fetched successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
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
        try{
            $messages = [
                'ftp_id.required' => 'We need to know FTP account id'
            ];
            $rules = [
                'ftp_id'         => 'required|numeric'
            ];
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json([
                    'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
                ]);
            }
            $password = "";
            if( $request->filled('password') ){
                $password = "<password>{$request->password}</password>";
            }
            $name = "";
            if( $request->filled('name') ){
                $name = "<name>{$request->name}</name>";
            }
            $api_request = <<<EOL
            <packet>
<ftp-user>
<set>
   <filter>
      <id>{$request->ftp_id}</id>
   </filter>
   <values>
      {$name}
      {$password}
      
      <permissions>
         <read>true</read>
         <write>true</write>
      </permissions>
   </values>
</set>
</ftp-user>
</packet>
EOL;
                $response_data = $this->client->request($api_request);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'FTP account updated successfully.'
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
    Created Date:   2021-12-23 (yyyy-mm-dd)
    Purpose:        Delete FTP account
    Params:         Input request
    */
    public function delete( $ftp_id ){
        try{
            $api_request = <<<EOF
            <packet>
<ftp-user>
<del>
   <filter>
      <id>{$ftp_id}</id>
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
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
}
