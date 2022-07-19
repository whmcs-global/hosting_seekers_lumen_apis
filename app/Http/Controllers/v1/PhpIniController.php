<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{CompanyServerPackage, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

class PhpIniController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;
    public function getPhpIni(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'version' => 'required'
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
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'PHP Version',
            'requestedFor' => serialize(['name' => 'Get PHP INI File Content']),
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
            $accCreated = $this->getPhpIniFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->version);
            if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                $error = $accCreated['metadata']["reason"];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Get PHP INI File Content error '.$serverPackage->domain,
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["data"]["content"], 'message' => 'PHP INI file content has been successfully fetched']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
    }

    public function updatePhpIni(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'version' => 'required',
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
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'PHP Version',
            'requestedFor' => serialize(['name' => 'Update PHP INI File Content', 'version' => $request->version]),
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
            $accCreated = $this->updatePhpIniFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->version, $request->content);
            if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                $error = $accCreated['metadata']["reason"];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Update PHP INI File Content error '.$serverPackage->domain,
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => 'Update PHP INI File Content',
                'message' => 'PHP INI file content has been successfully updated'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["data"], 'message' => 'PHP INI file content has been successfully updated']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
    }

    public function getVersion(Request $request, $id) {
        $errorArray = [
            'api_response' => 'error',
            'status_code' => 400,
            'data' => 'Connection error',
            'message' => config('constants.ERROR.FORBIDDEN_ERROR')
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'PHP Version',
            'requestedFor' => serialize(['name' => 'Get PHP Versions']),
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
            $phpVersions = $this->phpVersions($serverPackage->company_server_package->company_server_id, $serverPackage->domain);
            $phpCurrentVersion = $this->phpCurrentVersion($serverPackage->company_server_package->company_server_id, $serverPackage->domain);
            if(!is_array($phpVersions) || !is_array($phpCurrentVersion) || !array_key_exists("metadata", $phpVersions) || !array_key_exists("metadata", $phpVersions)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            
            if ($phpVersions["metadata"]['result'] == "0") {
                $error = $phpVersions["metadata"]['reason'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'PHP Version fetching error '.$serverPackage->domain,
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($phpCurrentVersion["metadata"]['result'] == "0") {
                $error = $phpCurrentVersion["metadata"]['reason'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            $domainInfo = [
                'versions' => $phpVersions['data']['versions'],
                "current" => $phpCurrentVersion['data']['version'],
            ];
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $domainInfo, 'message' => 'PHP versions has been fetched']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
    }

    public function getDirectives(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'version' => 'required'
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
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'PHP Version',
            'requestedFor' => serialize(['name' => 'Get PHP Directives']),
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
            $phpGetDirectives = $this->phpIniGetDirectives($serverPackage->company_server_package->company_server_id, $serverPackage->domain, $request->version);
            if(!is_array($phpGetDirectives) || !array_key_exists("metadata", $phpGetDirectives)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            
            if ($phpGetDirectives["metadata"]['result'] == "0") {
                $error = $phpGetDirectives["metadata"]['reason'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Get PHP Directives error '.$serverPackage->domain,
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $phpGetDirectives['data']['directives'], 'message' => 'PHP versions has been fetched']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
    }

    public function updateDirectives(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'version' => 'required',
            'directive' => 'required'
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
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'PHP Version',
            'requestedFor' => serialize(['name' => 'Update PHP Directives', 'version' => $request->version, 'directive' => $request->directive]),
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
            $phpGetDirectives = $this->phpIniUpdateDirectives($serverPackage->company_server_package->company_server_id, $serverPackage->domain, $request->version, $request->directive);
            if(!is_array($phpGetDirectives) || !array_key_exists("metadata", $phpGetDirectives)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            
            if ($phpGetDirectives["metadata"]['result'] == "0") {
                $error = $phpGetDirectives["metadata"]['reason'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Update PHP Directives error '.$serverPackage->domain,
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => 'Update PHP Directive',
                'message' => 'PHP Directive has been successfully updated'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'PHP Directive update', 'message' => 'PHP Directive has been successfully updated']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
    }

    public function updateVersion(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'version' => 'required'
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
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'PHP Version',
            'requestedFor' => serialize(['name' => 'Update PHP Version', 'version' => $request->version]),
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
            $accCreated = $this->updatePhpCurrentVersion($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name),$request->version);
            if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
                return response()->json($errorArray);
            }
            if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                $error = $accCreated['metadata']["reason"];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Update PHP Version error '.$serverPackage->domain,
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                $postData['errorType'] = 'System Error';
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
                $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
                return response()->json($errorArray);
            }
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => 'Update PHP Version',
                'message' => 'PHP version has been successfully updated'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'PHP version update', 'message' => 'PHP version has been successfully update']);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
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
            $postData['errorType'] = 'System Error';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData);  
            $errorArray['message'] = config('constants.ERROR.FORBIDDEN_ERROR');
            return response()->json($errorArray);
        }
    }
}
