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
            $accCreated = $this->extractFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), '/public_html');
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'File extracting error', 'message' => $error]);
            }   
            $this->deleteCpanelFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            $this->deleteCpanelFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), '/public_html/curl.php');
            $this->deleteCpanelFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), '/public_html/wordpressSql.sql');
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["cpanelresult"]["data"], 'message' => 'File has been extracted successfully']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
    }

    public function uploadFiles(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            // 'database' => 'required',
            // 'db_user' => 'required',
            // 'db_password' => 'required',
            // 'username' => 'required',
            // 'password' => 'required',
            // 'sitename' => 'required',
            // 'email' => 'required',
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
        $requestedFor = [
            'name' => 'Install Wordpress',
            // 'database' => $request->database,
            // 'db_user' => $request->db_user,
            // 'db_password' => $request->db_password,
            // 'username' => $request->username,
            // 'sitename' => $request->sitename,
            // 'email' => $request->email,
        ];
        $postData = [
            'userId' => jsencode_userdata($request->userid),
            'api_response' => 'error',
            'logType' => 'cPanel',
            'module' => 'Install Wordpress',
            'requestedFor' => serialize($requestedFor),
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
            $cpanelStats = $this->getCpanelStats($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($cpanelStats) ){
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            if ((array_key_exists("data", $cpanelStats) && $cpanelStats["data"]['result'] == "0")) {
                $error = $cpanelStats["data"]['reason'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Fetching error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            if ((array_key_exists("result", $cpanelStats) && $cpanelStats["result"]['status'] == "0")) {
                $error = $cpanelStats["result"]['errors'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Fetching error',
                    'message' => $error
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $count = $max = [];
            foreach( $cpanelStats['result']['data'] as $cpanelStat){
                if($cpanelStat['item'] == 'MySQL Databases'){
                    $count = $cpanelStat['count'];
                    if(array_key_exists("_count", $cpanelStat))
                    $count = $cpanelStat['_count'];
                    $max = $cpanelStat['max'];
                    if(array_key_exists("_max", $cpanelStat))
                    $max = $cpanelStat['_max'];
                } 
            }
            if($max != 'unlimited' && $max == $count && $count > 0){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Database check',
                    'message' => 'you have exceeded the limit for adding database'
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            
            $userCreated = $this->getMySqlUserRestrictions($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            
            if(is_array($userCreated) && array_key_exists("result", $userCreated) && $userCreated['result']['status'] == 0) {
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'MySql User Restrictions',
                    'message' => $userCreated['result']['errors']
                ];
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);

            }

            $dbName = 'intlwordpress';
            if($userCreated['result']['data']['prefix']){
                $dbName = $userCreated['result']['data']['prefix'].$dbName;
            }

            // Create database to install wordpress
            $accCreated = $this->createMySqlDb($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $dbName);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Create MySql Database',
                    'message' => config('constants.ERROR.FORBIDDEN_ERROR')
                ];
                $requestedFor['database'] = $dbName;
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            if ($accCreated["result"]['status'] == "0" && !str_contains($accCreated['result']["errors"][0], 'already exists')) {
                $error = $accCreated['result']["errors"][0];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Create MySql Database',
                    'message' => $error
                ];
                $requestedFor['database'] = $dbName;
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }

            // Create database user to install wordpress
            $accCreated = $this->createMySqlUser($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $dbName,  $dbName);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Create MySql User Connection Error',
                    'message' => config('constants.ERROR.FORBIDDEN_ERROR')
                ];
                $requestedFor['User'] = $dbName;
                $requestedFor['password'] = $dbName;
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            
            if ($accCreated["result"]['status'] == "0" && !str_contains($accCreated['result']["errors"][0], 'already exists')) {
                $error = $accCreated['result']["errors"][0];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Create MySql User',
                    'message' => $error
                ];
                $requestedFor['user'] = $dbName;
                $requestedFor['password'] = $dbName;
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }

            $accCreated = $this->updateMySqlDbPrivileges($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $dbName,  $dbName,  'ALL');
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Update MySql Database Privileges Connection Error',
                    'message' => config('constants.ERROR.FORBIDDEN_ERROR')
                ];
                $requestedFor['User'] = $dbName;
                $requestedFor['password'] = $dbName;
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Update MySql Database Privileges',
                    'message' => $userCreated['result']['errors']
                ];
                $requestedFor['user'] = $dbName;
                $requestedFor['password'] = $dbName;
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }

            $contentText = '<?php
            $mysqli = new mysqli("localhost", "'.$dbName.'", "'.$dbName.'", "'.$dbName.'");
            if ($mysqli->connect_error) {
              die("Connection failed: " . $mysqli->connect_error);
            }
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
            }
            curl_close($ch);
            
            $sqlUrl = "https://hostingseekers.com/fline/wordpressSql.sql"; // URL of what you wan to download
            $sqlFile = "wordpressSql.sql"; // Rename .zip file
            $zipResource = fopen($sqlFile, "w");            
            // Get The Zip File From Server
            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, $sqlUrl);
            curl_setopt($ch1, CURLOPT_FAILONERROR, true);
            curl_setopt($ch1, CURLOPT_HEADER, 0);
            curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch1, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch1, CURLOPT_BINARYTRANSFER,true);
            curl_setopt($ch1, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0); 
            curl_setopt($ch1, CURLOPT_FILE, $zipResource);            
            $page = curl_exec($ch1);   
            $siteUrl = "https://'.$serverPackage->domain.'/wordpress";
            $userEmail = "'.$serverPackage->user->email.'";
            $userName = "admin";
            $userPassword = "'.md5($serverPackage->name).'";
            $siteTitle = "'.$serverPackage->domain.'";
            $sql = file_get_contents($sqlFile);
            $sql = str_replace("http://pkkchemical.com/wordpress/", $siteUrl, $sql);
            $sql = str_replace("gauravch.shinedezign@gmail.com", $userEmail, $sql);
            $sql = str_replace("adminuseradmin", $userName, $sql);
            $sql = str_replace("adminpassword", $userPassword, $sql);
            $sql = str_replace("PKK Chemicals", $siteTitle, $sql);

            /* execute multi query */
            $mysqli->multi_query($sql); 
            
            if(!$page) {
                echo "Error :- ".curl_error($ch1);
            } else{
                echo "Downloaded!!";
            }
            curl_close($ch1);';
            $accCreated = $this->uploadFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), '/public_html', $contentText, 'curl.php');
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){      
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Upload curl.php file error',
                    'message' => config('constants.ERROR.FORBIDDEN_ERROR')
                ];
                $requestedFor['name'] = "Upload curl.php file on server";
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated["result"]['errors'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Upload curl.php file error',
                    'message' => $error
                ];
                $requestedFor['name'] = "Upload curl.php file on server";
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }

            // Hit this url to download latest wordpress zip
            $url = 'https://'.$serverPackage->domain.'/curl.php';

            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $responseData = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!str_contains($responseData, 'Downloaded')) { 
                
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Hit curl.php file error',
                    'message' => $responseData
                ];
                $requestedFor['name'] = "Hit curl.php file on server";
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            $accCreated = $this->extractFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), '/public_html');
            if(!is_array($accCreated) || !array_key_exists("cpanelresult", $accCreated)){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Extract wordpress.zip file error',
                    'message' => config('constants.ERROR.FORBIDDEN_ERROR')
                ];
                $requestedFor['name'] = "Extract wordpress.zip file on server";
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            if (array_key_exists("data", $accCreated['cpanelresult']) && array_key_exists("result", $accCreated['cpanelresult']['data']) && 0 == $accCreated['cpanelresult']["data"]['result']) {
                $error = $accCreated['cpanelresult']['data']["reason"];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Extract wordpress.zip file error',
                    'message' => $error
                ];
                $requestedFor['name'] = "Extract wordpress.zip file on server";
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }            
            $this->deleteCpanelFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            $this->deleteCpanelFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), '/public_html/curl.php');
            $this->deleteCpanelFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), '/public_html/wordpressSql.sql');
            $contentText = '<?php
            /**
             * The base configuration for WordPress
             *
             * The wp-config.php creation script uses this file during the installation.
             * You dont have to use the web site, you can copy this file to "wp-config.php"
             * and fill in the values.
             *
             * This file contains the following configurations:
             *
             * * Database settings
             * * Secret keys
             * * Database table prefix
             * * ABSPATH
             *
             * @link https://wordpress.org/support/article/editing-wp-config-php/
             *
             * @package WordPress
             */
            
            // ** Database settings - You can get this info from your web host ** //
            /** The name of the database for WordPress */
            define( "DB_NAME", "'.$dbName.'" );
            
            /** Database username */
            define( "DB_USER", "'.$dbName.'" );
            
            /** Database password */
            define( "DB_PASSWORD", "'.$dbName.'" );
            
            /** Database hostname */
            define( "DB_HOST", "localhost" );
            
            /** Database charset to use in creating database tables. */
            define( "DB_CHARSET", "utf8" );
            
            /** The database collate type. Don"t change this if in doubt. */
            define( "DB_COLLATE", "" );
            
            /**#@+
             * Authentication unique keys and salts.
             *
             * Change these to different unique phrases! You can generate these using
             * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
             *
             * You can change these at any point in time to invalidate all existing cookies.
             * This will force all users to have to log in again.
             *
             * @since 2.6.0
             */
            define( "AUTH_KEY",         "put your unique phrase here" );
            define( "SECURE_AUTH_KEY",  "put your unique phrase here" );
            define( "LOGGED_IN_KEY",    "put your unique phrase here" );
            define( "NONCE_KEY",        "put your unique phrase here" );
            define( "AUTH_SALT",        "put your unique phrase here" );
            define( "SECURE_AUTH_SALT", "put your unique phrase here" );
            define( "LOGGED_IN_SALT",   "put your unique phrase here" );
            define( "NONCE_SALT",       "put your unique phrase here" );
            
            /**#@-*/
            
            /**
             * WordPress database table prefix.
             *
             * You can have multiple installations in one database if you give each
             * a unique prefix. Only numbers, letters, and underscores please!
             */
            $table_prefix = "wp_";
            
            /**
             * For developers: WordPress debugging mode.
             *
             * Change this to true to enable the display of notices during development.
             * It is strongly recommended that plugin and theme developers use WP_DEBUG
             * in their development environments.
             *
             * For information on other constants that can be used for debugging,
             * visit the documentation.
             *
             * @link https://wordpress.org/support/article/debugging-in-wordpress/
             */
            define( "WP_DEBUG", false );
            
            /* Add any custom values between this line and the "stop editing" line. */
            
            
            
            /* That"s all, stop editing! Happy publishing. */
            
            /** Absolute path to the WordPress directory. */
            if ( ! defined( "ABSPATH" ) ) {
                define( "ABSPATH", __DIR__ . "/" );
            }
            
            /** Sets up WordPress vars and included files. */
            require_once ABSPATH . "wp-settings.php";';
            $accCreated =  $this->uploadFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), '/public_html/wordpress', $contentText, 'wp-config.php');
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Upload wp-settings.php file error',
                    'message' => config('constants.ERROR.FORBIDDEN_ERROR')
                ];
                $requestedFor['name'] = "Upload wp-settings.php file on server";
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated["result"]['errors'];
                $errorArray = [
                    'api_response' => 'error',
                    'status_code' => 400,
                    'data' => 'Upload wp-settings.php file error',
                    'message' => $error
                ];
                $requestedFor['name'] = "Upload wp-settings.php file on server";
                $postData['requestedFor'] = serialize($requestedFor);
                $postData['response'] = serialize($errorArray);
                //Hit node api to save logs
                hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
                return response()->json($errorArray);
            }
            UserServer::where('id', $serverPackage->id)->update(['install_wordpress' => 1]);
            $errorArray = [
                'api_response' => 'success',
                'status_code' => 200,
                'data' => [
                    'website_url' => 'https://'.$serverPackage->domain.'/wordpress',
                    'admin_login_url' => 'https://'.$serverPackage->domain.'/wordpress/wp-admin',
                    'admin_username' => 'admin',
                    'admin_password' => $serverPackage->name
                ],
                'message' => 'Wordpress has been successfully installed on your server'
            ];
            $postData['response'] = serialize($errorArray);
            $postData['api_response'] = 'success';
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
        catch(Exception $ex){
            $errorArray = [
                'api_response' => 'error',
                'status_code' => 400,
                'data' => 'Connection error',
                'message' => $ex->getMessage()
            ];
            $postData['response'] = serialize($errorArray);
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
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
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
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
            //Hit node api to save logs
            hitCurl(config('constants.NODE_URL').'/apiLogs/createApiLog', 'POST', $postData); 
            return response()->json($errorArray);
        }
    }
    
}
