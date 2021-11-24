<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, OrderTransaction, CompanyServerPackage, UserServer};
use Illuminate\Support\Facades\{DB, Config, Validator};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait};

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
                    $orderDataArray = ['id'=> jsencode_userdata($order->id), 'product_name' => $order->ordered_product->product->name, 'product_detail' => html_entity_decode(substr(strip_tags($order->ordered_product->product->features), 0, 50)), 'currency_icon' => $order->currency->icon, 'payable_amount' => $order->payable_amount, 'created_at' => change_date_format($order->updated_at), 'expiry' => change_date_format(add_days_to_date($order->updated_at, $this->billingCycleName($order->ordered_product->billing_cycle))), 'servers' => $packageArray];
                    $cpanelAccount = null;
                    if(!is_null($order->user_server)){
                        if(!is_null($order->user_server->company_server_package))
                        $cpanelAccount = ['id' => jsencode_userdata($order->user_server->id), 'name' => $order->user_server->name, 'domain' => $order->user_server->domain, 'package' => $order->user_server->company_server_package->package, 'server_name' => $order->user_server->company_server_package->company_server->name, 'server_location' => $order->user_server->company_server_package->company_server->state->name.', '.$order->user_server->company_server_package->company_server->country->name];
                    }
                    $orderDataArray['cpanelAccount'] = $cpanelAccount;
                    array_push($orderData, $orderDataArray);
                }
                $ordersData['data'] = $orderData;
                $orderArray = ['refinedData' => $ordersData];
            }
            return $this->apiResponse('success', '200', 'Data fetched', $orderArray);
            
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
    
    public function addDomain(Request $request) {
		$validator = Validator::make($request->all(),[
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
            $serverPackage = CompanyServerPackage::findOrFail($serverId);
            
            $accountCreate = [
                'user_id' => $request->userid,
                'order_id' => $orderId,
                'company_id' =>  $serverPackage->user_id,
                'company_server_package_id' => $serverPackage->id,
            ];
            $packageName = $serverPackage->package;
            $domainName = $this->getDomain($request->domain_name);
            $accountCreate['name'] = $request->account_name;
            $accountCreate['domain'] = $domainName;
            $accountCreate['password'] = 'G@ur@v123';
            $accCreated = $this->createAccount($serverPackage->company_server_id, $domainName, $request->account_name, 'G@ur@v123', $packageName);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if (array_key_exists("result", $accCreated) && $accCreated["result"][0]['status'] == "0") {
                $error = $accCreated["result"][0]['statusmsg'];
                $error = substr($error, strpos($error, ")")+1);
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }
            try{
            $userAccount = UserServer::updateOrCreate(['user_id' => $request->userid, 'order_id' => $orderId ], $accountCreate);
            } catch(\Exception $ex){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'DB error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => ['cpanel_server_id' => jsencode_userdata($userAccount->id), 'name' => $userAccount->name, 'domain' => $userAccount->domain], 'message' => 'Account has been successfully created']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    public function getEmailAccount(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->listEmailAccounts($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']["data"], 'message' => 'Account has been successfully created']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    
    public function addEmailAccount(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'email' => 'required',
            'password' => 'required',
            'quota' => 'required|numeric'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->createEmailAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->email.'@'.$serverPackage->domain,  $request->password,  $request->quota);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }

            $emails = $this->getEmailAccount($request, $request->cpanel_server)->getOriginalContent();
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $emails['data'], 'message' => 'Account has been successfully created']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    public function updateEmailPasswrod(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'email' => 'required',
            'password' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->changeEmailPassword($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->email.'@'.$serverPackage->domain,  $request->password);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'Email Account done', 'message' => 'Account password has been successfully update']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    
    public function updateEmailAccount(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'email' => 'required',
            'quota' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->changeEmailQuota($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->email,  $request->quota);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'Email Account done', 'message' => 'Account quota has been successfully update']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    
    public function deleteEmailAccount(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'email' => 'required'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->deleteEmailsAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->email);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'Email Account done', 'message' => 'Account quota has been successfully update']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    
    public function getFtpAccount(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->listFtpAccounts($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['cpanelresult']["data"], 'message' => 'Account has been successfully created']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    
    public function addFtpAccount(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'username' => 'required',
            'password' => 'required',
            'quota' => 'required|numeric'
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->createFtpAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->username,  $request->password,  $request->quota);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }

            $emails = $this->getFtpAccount($request, $request->cpanel_server)->getOriginalContent();
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $emails['data'], 'message' => 'Account has been successfully created']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
    
    public function updateFtpAccount(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'username' => 'required',
            'password' => 'required',
        ]);
        if($validator->fails()){
            return response()->json(["success"=>false, "errors"=>$validator->getMessageBag()->toArray()],400);
        }
        try
        {
            $serverId = jsdecode_userdata($request->cpanel_server);
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->changeFtpPassword($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->username,  $request->password,  $request->quota);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if(!array_key_exists("cpanelresult", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            if (array_key_exists("error", $accCreated['cpanelresult'])) {
                $error = $accCreated['cpanelresult']["error"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account creation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'FTP Account done', 'message' => 'Account password has been successfully update']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
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
            $serverPackage = UserServer::findOrFail($serverId);
            $accCreated = $this->domainInfo($serverPackage->company_server_package->company_server_id, $serverPackage->domain);
            if(!is_array($accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if (array_key_exists("metadata", $accCreated) && $accCreated["metadata"]['result'] == "0") {
                $error = $accCreated["metadata"]['reason'];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
            }
            $domainInfo = [
                "user" => $accCreated["data"]['userdata']["user"],
                "servername" => $accCreated["data"]['userdata']['servername'],
                "documentroot" => $accCreated["data"]['userdata']['documentroot'],
                "homedir" => $accCreated["data"]['userdata']['homedir'],
                "ip" => $accCreated["data"]['userdata']['ip'],
                "port" => $accCreated["data"]['userdata']['port'],
            ];
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $domainInfo, 'message' => 'Domian information has been fetched']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => Config::get('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => 'Linked server connection test failed. Connection Timeout']);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => 'Server internal error. Check your server and server licence']);
        }
    }
}
