<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{UserServer};
use Illuminate\Support\Facades\{Config, Validator};
use App\Traits\{CpanelTrait};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class WordpressController extends Controller
{
    use CpanelTrait;
    
    public function extractFiles(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'dir' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->extractFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->dir);
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'File extracting error', 'message' => $error]);
            }   
            $this->deleteCpanelFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            $this->deleteCpanelFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), '/public_html/curl.php');
            $contentText = '<?php
            // ** MySQL settings - You can get this info from your web host ** //
            /** The name of the database for WordPress */
            define("DB_NAME", "database_name_here");
            
            /** MySQL database username */
            define("DB_USER", "username_here");
            
            /** MySQL database password */
            define("DB_PASSWORD", "password_here");
            
            /** MySQL hostname */
            define("DB_HOST", "localhost");
            
            /** Database Charset to use in creating database tables. */
            define("DB_CHARSET", "utf8");
            
            /** The Database Collate type. Don"t change this if in doubt. */
            define("DB_COLLATE", "");
            define("AUTH_KEY",		"put your unique phrase here");
            define("SECURE_AUTH_KEY",	"put your unique phrase here");
            define("LOGGED_IN_KEY",		"put your unique phrase here");
            define("NONCE_KEY",		"put your unique phrase here");
            define("AUTH_SALT",		"put your unique phrase here");
            define("SECURE_AUTH_SALT",	"put your unique phrase here");
            define("LOGGED_IN_SALT",	"put your unique phrase here");
            define("NONCE_SALT",		"put your unique phrase here");
            $table_prefix  = "wp_";
            
            define( "WP_DEBUG", false );
            /* Add any custom values between this line and the "stop editing" line. */
            
            /* That"s all, stop editing! Happy publishing. */
            
            /** Absolute path to the WordPress directory. */
            if ( ! defined( "ABSPATH" ) ) {
                define( "ABSPATH", __DIR__ . "/" );
            }
            
            /** Sets up WordPress vars and included files. */
            require_once ABSPATH . "wp-settings.php";';
            $this->uploadFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->dir.'/wordpress', $contentText, 'wp-config.php');
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["cpanelresult"]["data"], 'message' => 'File has been extracted successfully']);
        }
        catch(Exception $ex){
            return $ex;
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
    }

    public function uploadFiles(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'dir' => 'required',
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $contentText = '<?php
            $url = "https://wordpress.org/latest.zip"; // URL of what you wan to download
            $zipFile = "wordpress.zip"; // Rename .zip file
            $zipResource = fopen($zipFile, "w");            
            // Get The Zip File From Server
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
            curl_setopt($ch, CURLOPT_FILE, $zipResource);            
            $page = curl_exec($ch);            
            if(!$page) {
                echo "Error :- ".curl_error($ch);
            } else{
                echo "Downloaded!!";
            }
            curl_close($ch);';
            $accCreated = $this->uploadFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->dir, $contentText, 'curl.php');
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["result"]["data"], 'message' => 'File has been uploaded successfully']);
        }
        catch(Exception $ex){
            return $ex;
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
    }
    
}
