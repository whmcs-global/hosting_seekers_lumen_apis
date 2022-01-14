<?php
namespace App\Http\Controllers\v1\delegate\plesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PleskX\Api\Client;
use Illuminate\Support\Facades\Validator;
use App\Traits\PleskTrait;
use App\Models\{UserServer};

class EmailAccountController extends Controller{
    use PleskTrait;
    private $client;

    function __construct() {
        $this->runPleskQuery();
    }

    /*
    Method Name:    create
    Developer:      Shine Dezign
    Created Date:   2021-12-24 (yyyy-mm-dd)
    Purpose:        Create Email 
    Params:         Request input
    */
    public function create( Request $request ){
        $validator = Validator::make($request->all(),[
            'email'     => 'required',
            'password'  => 'required',
            'quota'     => 'required|numeric',
            'quotasize' => 'required'
        ]);
        if($validator->fails()){
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
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
            $siteId = $this->getSiteId();
            $api_request = <<<EOL
                <?xml version="1.0" encoding="UTF-8"?>
                <packet>
                <mail>
                <create>
                <filter>
                    <site-id>{$siteId}</site-id>
                    <mailname>
                        <name>{$request->email}</name>
                        <mailbox>
                                <enabled>true</enabled>
                                <quota>{$quota}</quota>
                        </mailbox>
                        <password>
                                <value>{$request->password}</value>
                                <type>plain</type>
                        </password>
                    </mailname>
                </filter>
                </create>
                </mail>
                </packet>
            EOL;
            $response = $this->client->request($api_request);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [ ] , 'message' => 'Mail created successfully.'
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
    Created Date:   2021-12-24 (yyyy-mm-dd)
    Purpose:        Detail of email account 
    Params:         Request input
    */
    public function detail( Request $request ){
		$siteId = $this->getSiteId();
        $api_request = <<<EOL
        <packet>
            <mail>
            <get_info>
            <filter>
                <site-id>{$siteId}</site-id>
            </filter>
            <mailbox/>
            </get_info>
            </mail>
        </packet>
        EOL;
        try{
            $response = $this->client->request($api_request,2);
            $response_data = [];
            foreach( $response->mail->get_info->result as $mail ){
                    
                if(property_exists($mail, 'mailname')){
                    $response_data[] = [
                        'id'    =>  jsencode_userdata((string)$mail->mailname->id),
                        'name'  =>  (string)$mail->mailname->name,
                        'quota' =>  $mail->mailname->mailbox->quota . " Bytes"
                    ];
                }
            }
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => $response_data , 'message' => 'Mail detail fetched successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [  ], 'message' => $e->getMessage()
            ]);
        }
    }

    /*
    Method Name:    update
    Developer:      Shine Dezign
    Created Date:   2021-12-24 (yyyy-mm-dd)
    Purpose:        Email account updated
    Params:         Request input
    */
    public function update(Request $request){
        $validator = Validator::make($request->all(),[
            'email'      =>  'required',
            'password'  =>  'required'
        ]);
        if($validator->fails()){
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
		$siteId = $this->getSiteId();
        $api_request = <<<EOL
            <packet>
            <mail>
            <update>
            <set>
                <filter>
                    <site-id>{$siteId}</site-id>
                    <mailname>
                        <name>{$request->email}</name>
                        <password>
                                <value>{$request->password}</value>
                                <type>plain</type>
                        </password>
                    </mailname>
                </filter>
            </set>
            </update>
            </mail>
            </packet>
        EOL;
        try{

            $response = $this->client->request($api_request,2);
            $response_data = [];
            
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'Mail updated successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [  ], 'message' => $e->getMessage()
            ]);
        }
    }

    /*
    Method Name:    Delete
    Developer:      Shine Dezign
    Created Date:   2021-12-24 (yyyy-mm-dd)
    Purpose:        Email account delete
    Params:         Request input
    */
    public function delete(Request $request){
        $validator = Validator::make($request->all(),[
            'email'      =>  'required',
        ]);
        if($validator->fails()){
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
		$siteId = $this->getSiteId();
        try{
            $response = $this->client->mail()->delete( "name" , $request->email, $siteId );
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [
                   
                ] , 'message' => 'Mail deleted successfully.'
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [  ], 'message' => $e->getMessage()
            ]);
        }
    }
}
