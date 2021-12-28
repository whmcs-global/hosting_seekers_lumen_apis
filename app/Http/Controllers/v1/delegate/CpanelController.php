<?php

namespace App\Http\Controllers\v1\delegate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, OrderTransaction, CompanyServerPackage, UserServer, DelegateDomainAccess};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

class CpanelController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;
    public function orderedServers(Request $request, $id = null){
        
        try {
            $records = DelegateDomainAccess::when(($id != ''), function($q) use($id){
                $q->where('user_server_id', jsdecode_userdata($id));
            })->where(['delegate_account_id' => $request->delegate_account_id])->get();
            $ratingArray = [];
            if($records->isNotEmpty()){ 
                $permissionData = [];
                foreach($records as $row){
                    $permissions = [];
                    foreach($row->delegate_domain_access as $permission){
                        array_push($permissions, ['id'=> jsencode_userdata($permission->delegate_permission->id), 'name' => $permission->delegate_permission->name, 'displayname' => $permission->delegate_permission->displayname, 'slug' => $permission->delegate_permission->slug]);
                    }
                    array_push($permissionData, ['id'=> jsencode_userdata($row->user_server->id), 'name' => $row->user_server->name, 'domain' => $row->user_server->domain, 'server_location' => $row->user_server->company_server_package->company_server->state->name.', '.$row->user_server->company_server_package->company_server->country->name, 'created_at' => change_date_format($row->user_server->order->updated_at), 'expiry' => change_date_format(add_days_to_date($row->user_server->order->updated_at, $this->billingCycleName($row->user_server->order->ordered_product->billing_cycle))), 'permissions' => $permissions]);
                }
                $ratingArray['domains'] = $permissionData;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $ratingArray);
            
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
    
    public function loginAccount(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->loginCpanelAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                $error = $accCreated['metadata']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account login error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['data'], 'message' => 'Account is ready for login']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    public function getUserInfo(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $cpanelStats = $this->getCpanelStats($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            $accCreated = $this->domainInfo($serverPackage->company_server_package->company_server_id, $serverPackage->domain);
            $nameCreated = $this->domainNameServersInfo($serverPackage->company_server_package->company_server_id, $serverPackage->domain);
            if(!is_array($accCreated) || !is_array($nameCreated) || !is_array($cpanelStats) ){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ((array_key_exists("metadata", $accCreated) && $accCreated["metadata"]['result'] == "0")) {
                $error = $accCreated["metadata"]['reason'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            if ((array_key_exists("metadata", $nameCreated) && $nameCreated["metadata"]['result'] == "0")) {
                $error = $nameCreated["metadata"]['reason'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            if ((array_key_exists("result", $cpanelStats) && $cpanelStats["result"]['status'] == "0")) {
                $error = $cpanelStats["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            $cpanelStatArray = [];
            foreach( $cpanelStats['result']['data'] as $cpanelStat){
                    $value = $units = null;
                    if(array_key_exists("units", $cpanelStat))
                    $units = $cpanelStat['units'];
                    if(array_key_exists("value", $cpanelStat))
                    $value = $cpanelStat['value'];
                    array_push($cpanelStatArray, ['item' => $cpanelStat['item'], 'name' => $cpanelStat['name'], 'count' => $cpanelStat['count'], 'max' => $cpanelStat['max'], 'percent' => $cpanelStat['percent'], 'value' => $value, 'units' => $units]);
            }
            $domainInfo = [
                "user" => $accCreated["data"]['userdata']["user"],
                "servername" => $accCreated["data"]['userdata']['servername'],
                "documentroot" => $accCreated["data"]['userdata']['documentroot'],
                "homedir" => $accCreated["data"]['userdata']['homedir'],
                "ip" => $accCreated["data"]['userdata']['ip'],
                "port" => $accCreated["data"]['userdata']['port'],
                "nameservers" => $nameCreated['data']['nameservers'],
                "accountStats" => $cpanelStatArray
            ];
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $domainInfo, 'message' => 'Domian information has been fetched']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
}