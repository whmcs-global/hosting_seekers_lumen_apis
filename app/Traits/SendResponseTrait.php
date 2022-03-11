<?php
 
namespace App\Traits;
Use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;
use hisorange\BrowserDetect\Parser;

use Illuminate\Support\Facades\Crypt;
trait SendResponseTrait {

    public function apiResponse($apiResponse, $statusCode = '404', $message = 'No records Found', $data = []) {
        $responseArray = [];
        if($apiResponse == 'success'){
            $responseArray['api_response'] = $apiResponse;
            $responseArray['status_code'] = $statusCode;
            $responseArray['message'] = $message;
            $responseArray['data'] = $data;
        } else {
            $responseArray['api_response'] = 'error';
            $responseArray['status_code'] = $statusCode;
            $responseArray['message'] = $message;
            $responseArray['data'] = [];    
        }
        return response()->json($responseArray, $statusCode);
    }

    public function createToken(){
        $browse_detail = $this->getUserBrowseDetail();

        $token_value = $browse_detail['ip'].'#'.$browse_detail['browser'].'#'.$browse_detail['os'].'#'.time();
        return jsencode_api($token_value);
    }

    public function validateToken($token = NULL, $requestDetail = null){
        if(!$token)
            return FALSE;
        $browse_detail = $this->getUserBrowseDetail();
        if($requestDetail)
        $browse_detail = $requestDetail;
        $dectypy_token = jsdecode_api($token);
        $token_array = explode('#', $dectypy_token);
        // return [$browse_detail['ip'].'=='.$token_array[0], $browse_detail['os'].'=='.$token_array[2], $browse_detail['browser'].'=='.$token_array[1]];
        if(($browse_detail['ip'] == $token_array[0] || $token_array[0] == '127.0.0.1' || $token_array[0] == '3.232.141.230' || $token_array[0] == '172.70.92.177' || $token_array[0] == '172.70.92.209') && $browse_detail['os'] == $token_array[2] && $browse_detail['browser'] == $token_array[1])
        return TRUE;
        else
        return FALSE;
    }

    public function getUserBrowseDetail(){
        $browser = new Parser(null, null, [
            'cache' => [
                'interval' => 86400 // This will overide the default configuration.
            ]
        ]);

        $data = array (
            'ip' => request()->ip() ? :'postman',
            'browser' => $browser->browserName() ? :'postman',
            'os' => $browser->platformName() ? :'postman',
        );
        return $data;
    }

    public function encryptData($data){
        return Crypt::encrypt($data);
    }

    public function decryptData($data){
        return Crypt::decrypt($data);
    }
}