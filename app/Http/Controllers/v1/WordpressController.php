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
            define( "DB_NAME", "pkkchemical_main" );
            
            /** Database username */
            define( "DB_USER", "pkkchemical_main" );
            
            /** Database password */
            define( "DB_PASSWORD", "pkkchemical_main" );
            
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
            'database' => 'required',
            'db_user' => 'required',
            'db_password' => 'required',
            'username' => 'required',
            'password' => 'required',
            'sitename' => 'required',
            'email' => 'required',
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
            
            $cpanelStats = $this->getCpanelStats($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($cpanelStats) ){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if ((array_key_exists("data", $cpanelStats) && $cpanelStats["data"]['result'] == "0")) {
                $error = $cpanelStats["data"]['reason'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            if ((array_key_exists("result", $cpanelStats) && $cpanelStats["result"]['status'] == "0")) {
                $error = $cpanelStats["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
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
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            $contentText = '<?php
            $mysqli = new mysqli("localhost", "'.$request->db_user.'", "'.$request->db_password.'", "'.$request->database.'");
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
            $userEmail = "'.$request->email.'";
            $userName = "'.$request->username.'";
            $userPassword = "'.md5($request->password).'";
            $siteTitle = "'.$request->sitename.'";
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
            $accCreated = $this->uploadFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->dir, $contentText, 'curl.php');
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
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
            $data = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
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
            define( "DB_NAME", "'.$request->database.'" );
            
            /** Database username */
            define( "DB_USER", "'.$request->db_user.'" );
            
            /** Database password */
            define( "DB_PASSWORD", "'.$request->db_password.'" );
            
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
            $this->uploadFile($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->dir.'/wordpress', $contentText, 'wp-config.php');
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => '', 'message' => 'File has been uploaded successfully']);
        }
        catch(Exception $ex){
            return $ex;
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
    }
    
}
