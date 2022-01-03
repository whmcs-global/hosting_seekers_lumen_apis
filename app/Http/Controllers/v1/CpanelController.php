<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, OrderTransaction, OrderedProduct, Invoice, CompanyServerPackage, UserServer, UserTerminatedAccount};
use Illuminate\Support\Facades\{DB, Config, Validator};
use Illuminate\Support\Str;
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CpanelController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait;
    public function orderedServers(Request $request){
        
        try {
            $start = $end = $daterange = '';
            if ($request->has('daterange_filter') && $request->daterange_filter != '') {
                $daterange = $request->daterange_filter;
                $daterang = explode(' / ', $daterange);
                $start = $daterang[0].' 00:00:00';
                $end = $daterang[1].' 23:59:59';
            }
            $sortBy = 'id';
            $orderBy = 'DESC';
            if($request->has('sort_by')){
                $sortBy = $request->sort_by;
            }
            if($request->has('dir')){
                $orderBy = $request->dir;
            }
            $orders = Order::when(($request->has('daterange_filter') && $request->daterange_filter != ''), function($q) use($start, $end){
                $q->whereBetween('created_at', [$start, $end]);
            })->when(($request->has('search_keyword') && $request->search_keyword != ''), function($q) use($request){
                $q->where(function ($quer) use ($request) {
                    $quer->whereHas('user_server', function( $qu ) use($request){
                        $qu->where('name', 'LIKE', '%'.$request->search_keyword.'%')->orWhere('domain', 'LIKE', '%'.$request->search_keyword.'%');
                    });
                });
            })->where(['user_id' => $request->userid, 'trans_status' => 'approved'])->orderBy($sortBy, $orderBy)->paginate(config('constants.PAGINATION_NUMBER'));
            $orderArray = [];
            $page = 1;
            if($request->has('page'))
            $page = $request->page;
            if($orders->isNotEmpty()) {
                $ordersData = [
                    'count' => $orders->count(),
                    'currentPage' => $orders->currentPage(),
                    'hasMorePages' => $orders->hasMorePages(),
                    'lastPage' => $orders->lastPage(),
                    'nextPageUrl' => $orders->nextPageUrl(),
                    'perPage' => $orders->perPage(),
                    'previousPageUrl' => $orders->previousPageUrl(),
                    'total' => $orders->total()
                ];
                $orderData = [];
                foreach($orders as $order){                    
                    try{
                        $packageList = $order->ordered_product->product->company_server_package;
                    } catch(\Exception $e){
                        $packageList = null;
                    }
                    $packageArray = [];
                    if($packageList){
                        foreach($packageList as $package){
                            array_push($packageArray, ['id' => jsencode_userdata($package->id), 'server_name' => $package->company_server->name, 'server_location' => $package->company_server->state->name.', '.$package->company_server->country->name]);
                        }
                    }
                    $orderDataArray = ['id'=> jsencode_userdata($order->id), 'company_name' => $order->ordered_product->product->user->company_detail->company_name, 'product_name' => $order->ordered_product->product->name, 'product_detail' => html_entity_decode(substr(strip_tags($order->ordered_product->product->features), 0, 50)), 'currency_icon' => $order->currency->icon, 'payable_amount' => $order->payable_amount, 'created_at' => change_date_format($order->updated_at), 'expiry' => change_date_format(add_days_to_date($order->updated_at, $this->billingCycleName($order->ordered_product->billing_cycle))), 'servers' => $packageArray];
                    $cpanelAccount = null;
                    if(!is_null($order->user_server)){
                        if(!is_null($order->user_server->company_server_package)){

                            $linkserver = $order->user_server->company_server_package->company_server->link_server ? unserialize($order->user_server->company_server_package->company_server->link_server) : 'N/A';
                            $controlPanel = null;
                            if('N/A' != $linkserver)
                            $controlPanel = $linkserver['controlPanel'];
                            $cpanelAccount = ['id' => jsencode_userdata($order->user_server->id), 'name' => $order->user_server->name, 'domain' => $order->user_server->domain, 'imagePath' => $order->user_server->screenshot, 'package' => $order->user_server->company_server_package->package, 'server_ip' => $order->user_server->company_server_package->company_server->ip_address, 'server_name' => $order->user_server->company_server_package->company_server->name, 'server_type' => $controlPanel, 'server_location' => $order->user_server->company_server_package->company_server->state->name.', '.$order->user_server->company_server_package->company_server->country->name];
                        }
                    } 
                    $orderDataArray['cpanelAccount'] = $cpanelAccount;
                    array_push($orderData, $orderDataArray);
                }
                $ordersData['data'] = $orderData;
                $orderArray = $ordersData;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $orderArray);
            
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
    
    public function createImage(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'url' => 'required',
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
            $response = hitCurl('https://www.hostingseekers.com/c-review/generate/review', 'POST', [ 'url' => 'https://'.$request->url, 'imageName' => Str::slug($request->url).'-website-screenshot.png']);
            $response = json_decode($response);
            if($response->success){
                $imagePath = str_replace('http://localhost:4000/',  'https://www.hostingseekers.com/c-review/', $response->path);
                $serverPackage->update(['screenshot' => $imagePath]);
                return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $imagePath, 'message' => 'Website has been captured successfully']);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => [], 'message' => $response->message]);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
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
    
    public function deleteAccount(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->terminateCpanelAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                $error = $accCreated['metadata']["reason"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account terminating error', 'message' => $error]);
            }
            UserTerminatedAccount::create(['user_id' => $serverPackage->user_id, 'name' => $serverPackage->name, 'domain' => $serverPackage->domain ]);
            $serverPackage->delete();
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['metadata']["reason"], 'message' => 'Account has been successfully terminated']);
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
    public function addDomain(Request $request) {
		$validator = Validator::make($request->all(),[
            'order_id' => 'required',
            'account_name' => 'required',
            'server_location' => 'required',
            'domain_name' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->server_location);
            $orderId = jsdecode_userdata($request->order_id);
            try
            {
                $serverPackage = CompanyServerPackage::findOrFail($serverId);
            }
            // catch(Exception $e) catch any exception
            catch(ModelNotFoundException $e)
            {
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            $linkserver = $serverPackage->company_server->link_server ? unserialize($serverPackage->company_server->link_server) : 'N/A';
            $controlPanel = null;
            if('N/A' != $linkserver)
            $controlPanel = $linkserver['controlPanel'];
            $accountCreate = [
                'user_id' => $request->userid,
                'order_id' => $orderId,
                'company_id' =>  $serverPackage->user_id,
                'company_server_package_id' => $serverPackage->id,
            ];
            $packageName = str_replace(" ", "_", $serverPackage->package);
            $domainName = $this->getDomain($request->domain_name);
            $accountCreate['name'] = $request->account_name;
            $accountCreate['domain'] = $domainName;
            $accountCreate['password'] = 'G@ur@v123';
            if('cPanel' == $controlPanel){
                $accCreated = $this->createAccount($serverPackage->company_server_id, $domainName, $request->account_name, 'G@ur@v123', $packageName);
                if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                    return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
                }
                
                if ($accCreated['metadata']["result"] == "0") {
                    $error = $accCreated['metadata']['reason'];
                    $error = substr($error, strpos($error, ")")+1);
                    return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
                }
            }
            if('Plesk' == $controlPanel){
                
            }
            try{
                $userAccount = UserServer::updateOrCreate(['user_id' => $request->userid, 'order_id' => $orderId ], $accountCreate);
            } catch(\Exception $ex){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'DB error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => ['cpanel_server_id' => jsencode_userdata($userAccount->id), 'name' => $userAccount->name, 'domain' => $userAccount->domain, 'company_name' => $serverPackage->company_server->user->company_detail->company_name, 'server_ip' => $serverPackage->company_server->ip_address, 'server_type' => $controlPanel], 'message' => 'Account has been successfully created']);
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
            if(!is_array($cpanelStats) ){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
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
                    $count = $cpanelStat['count'];
                    if(array_key_exists("_count", $cpanelStat))
                    $count = $cpanelStat['_count'];
                    $max = $cpanelStat['max'];
                    if(array_key_exists("_max", $cpanelStat))
                    $max = $cpanelStat['_max'];
                    array_push($cpanelStatArray, ['item' => $cpanelStat['item'], 'name' => $cpanelStat['name'], 'count' => $count, 'max' => $max, 'percent' => $cpanelStat['percent'], 'value' => $value, 'units' => $units]);
            }
            $domainInfo = [
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
    public function dnsInfo(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->domainInfo($serverPackage->company_server_package->company_server_id, $serverPackage->domain);
            $nameCreated = $this->domainNameServersInfo($serverPackage->company_server_package->company_server_id, $serverPackage->domain);
            if(!is_array($accCreated) || !is_array($nameCreated)){
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
            $domainInfo = [
                "user" => $accCreated["data"]['userdata']["user"],
                "servername" => $accCreated["data"]['userdata']['servername'],
                "documentroot" => $accCreated["data"]['userdata']['documentroot'],
                "homedir" => $accCreated["data"]['userdata']['homedir'],
                "ip" => $accCreated["data"]['userdata']['ip'],
                "port" => $accCreated["data"]['userdata']['port'],
                "nameservers" => $nameCreated['data']['nameservers'],
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
    public function getDomains(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $accCreated = $this->domainList($serverPackage->company_server_package->company_server_id,  strtolower($serverPackage->name));
            
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated["result"]['errors'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated["result"]["data"], 'message' => 'Domian information has been fetched']);
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
