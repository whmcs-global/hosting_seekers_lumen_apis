<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, OrderTransaction, OrderedProduct, Invoice, CompanyServerPackage, UserServer, UserTerminatedAccount, DomainBandwidthStat, CloudfareUser};
use Illuminate\Support\Facades\{DB, Config, Validator};
use Illuminate\Support\Str;
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait, CloudfareTrait};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PleskX\Api\Client;
use Carbon\Carbon;

class CpanelController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait, CloudfareTrait;
    
    private $client;
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
            $cancelled = null;
            if($request->has('sort_by')){
                $sortBy = $request->sort_by;
            }
            if($request->has('cancelled')){
                $cancelled = 1;
            }
            if($request->has('dir')){
                $orderBy = $request->dir;
            }
            $orders = Order::whereHas('ordered_product')->when(($request->has('daterange_filter') && $request->daterange_filter != ''), function($q) use($start, $end){
                $q->whereBetween('created_at', [$start, $end]);
            })->when(($request->has('search_keyword') && $request->search_keyword != ''), function($q) use($request){
                $q->where(function ($quer) use ($request) {
                    $quer->whereHas('user_server', function( $qu ) use($request){
                        $qu->where('name', 'LIKE', '%'.$request->search_keyword.'%')->orWhere('domain', 'LIKE', '%'.$request->search_keyword.'%');
                    });
                });
            })->when(!$cancelled, function($q) use($request){
                $q->where('is_cancelled', 0);
            })->when($cancelled, function($q) use($request){
                $q->where('is_cancelled', 1);
            })->when(($request->has('search_keyword') && $request->search_keyword != ''), function($q) use($request){
                $q->where(function ($quer) use ($request) {
                    $quer->whereHas('user_server', function( $qu ) use($request){
                        $qu->where('name', 'LIKE', '%'.$request->search_keyword.'%')->orWhere('domain', 'LIKE', '%'.$request->search_keyword.'%');
                    });
                });
            })->where(['user_id' => $request->userid, 'status' => 1])->orderBy($sortBy, $orderBy)->paginate(config('constants.PAGINATION_NUMBER'));
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
                    $billingCycle = $this->billingCycleName($order->ordered_product->billing_cycle);
                    $cancelDays = config('constants.DAYS_FOR_MONTHLY_BILLING');
                    if($billingCycle == 'Annually')
                    $cancelDays = config('constants.DAYS_FOR_YEARLY_BILLING');
                    $to = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at);
                    $from = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'));
                    $cancelService = false;
                    $diff_in_days = $to->diffInDays($from) + 1;
                    if($order->hosting_claim == 0 && $diff_in_days <= $cancelDays && $order->is_cancelled == 0)
                    $cancelService = true;
                    $orderDataArray = ['id'=> jsencode_userdata($order->id), 'company_name' => $order->ordered_product->product->user->company_detail->company_name, 'product_name' => $order->ordered_product->product->name, 'product_detail' => html_entity_decode(substr(strip_tags($order->ordered_product->product->features), 0, 50)), 'currency_icon' => $order->currency->icon, 'payable_amount' => $order->payable_amount, 'is_cancelled' => $order->is_cancelled, 'cancelled_on' => $order->cancelled_on ? change_date_format($order->cancelled_on) : null, 'created_at' => change_date_format($order->created_at), 'expiry' => change_date_format(add_days_to_date($order->created_at, $billingCycle)), 'cancel_service' => $cancelService, 'servers' => $packageArray];
                    $cpanelAccount = null;
                    if(!is_null($order->user_server)){
                        if(!is_null($order->user_server->company_server_package)){

                            $linkserver = $order->user_server->company_server_package->company_server->link_server ? unserialize($order->user_server->company_server_package->company_server->link_server) : 'N/A';
                            $controlPanel = null;
                            if('N/A' != $linkserver)
                            $controlPanel = $linkserver['controlPanel'];
                            $cpanelAccount = ['id' => jsencode_userdata($order->user_server->id), 'name' => $order->user_server->name, 'domain' => $order->user_server->domain, 'imagePath' => $order->user_server->screenshot, 'package' => $order->user_server->company_server_package->package, 'server_ip' => $order->user_server->company_server_package->company_server->ip_address, 'server_name' => $order->user_server->company_server_package->company_server->name, 'server_type' => $controlPanel, 'server_location' => $order->user_server->company_server_package->company_server->state->name.', '.$order->user_server->company_server_package->company_server->country->name, 'bandwidth' => null];
                            
                            if($order->user_server->bandwidth->isNotEmpty()){
                                $bandwidthArray = $dateArray = [];
                                $counter = 1;
                                foreach($order->user_server->bandwidth as $bandwidth){
                                    if($counter == 6)
                                    break;
                                    array_push($dateArray, change_date_format($bandwidth->stats_date, 'd M'));
                                    array_push($bandwidthArray, $bandwidth->bandwidth);
                                    $counter++;
                                }
                                $cpanelAccount['bandwidth'] = ['dates' => $dateArray, 'stats' => $bandwidthArray];
                            }
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
            return $this->apiResponse('error', '400', $e->getMessage());
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
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
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
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
    
    public function deleteAccount(Request $request, $id) {
        try
        {
            $serverId = jsdecode_userdata($id);
            $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => $serverId])->first();
            if(!$serverPackage)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            
            $linkserver = $serverPackage->company_server_package->company_server->link_server ? unserialize($serverPackage->company_server_package->company_server->link_server) : 'N/A';
            $controlPanel = null;
            if('N/A' == $linkserver)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $controlPanel = $linkserver['controlPanel'];
            if('cPanel' == $controlPanel){
                $accCreated = $this->terminateCpanelAccount($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name));
                if(!is_array($accCreated) || !array_key_exists("metadata", $accCreated)){
                    return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
                }
                if (array_key_exists("result", $accCreated['metadata']) && 0 == $accCreated['metadata']["result"]) {
                    $error = $accCreated['metadata']["reason"];
                    return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Account terminating error', 'message' => $error]);
                }
            }
            if('Plesk' == $controlPanel){
                $packageName = $serverPackage->package;                
                try{

                    $this->client = new Client($serverPackage->company_server_package->company_server->ip_address);
                    $this->client->setCredentials($linkserver['username'], $linkserver['apiToken']);
                    $this->client->Webspace()->delete("name",$request->domain);
                }catch(\Exception $e){
                    return response()->json([
                        'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
                    ]);
                }
            }
            UserTerminatedAccount::create(['user_id' => $serverPackage->user_id, 'name' => $serverPackage->name, 'domain' => $serverPackage->domain ]);
            $serverPackage->delete();
            if($serverPackage->cloudfare_user_id)
                $this->deleteZone($serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email,  $serverPackage->cloudfare_user->user_api);
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $accCreated['metadata']["reason"], 'message' => 'Account has been successfully terminated']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
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
            $orders = Order::where(['user_id' => $request->userid, 'id' => $orderId, 'status' => 1, 'is_cancelled' => 0])->first();
            if(!$orders || !in_array($serverId, $orders->ordered_product->product->company_server_package->pluck('id')->toArray()))
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            try
            {
                $serverPackage = CompanyServerPackage::findOrFail($serverId);
            }
            // catch(Exception $e) catch any exception
            catch(ModelNotFoundException $e)
            {
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $e->getMessage()]);
            }
            
            $linkserver = $serverPackage->company_server->link_server ? unserialize($serverPackage->company_server->link_server) : 'N/A';
            $controlPanel = null;
            if('N/A' == $linkserver)
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            $controlPanel = $linkserver['controlPanel'];
            $accountCreate = [
                'user_id' => $request->userid,
                'order_id' => $orderId,
                'company_id' =>  $serverPackage->user_id,
                'company_server_package_id' => $serverPackage->id,
            ];
            $domainName = $this->getDomain($request->domain_name);
            $accountCreate['name'] = $request->account_name;
            $accountCreate['domain'] = $domainName;
            $accountCreate['password'] = 'G@ur@v123';
            if('cPanel' == $controlPanel){
                $packageName = str_replace(" ", "_", $serverPackage->package);
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
                $packageName = $serverPackage->package;                
                try{

                    $this->client = new Client($serverPackage->company_server->ip_address);
	                $this->client->setCredentials($linkserver['username'], $linkserver['apiToken']);
                    $customer = $this->checkCustomerExist($request->account_name);
                    if( empty($customer) ){
                        $customer = $this->client->customer()->create([
                            "pname"     =>  $request->account_name,
                            "login"     =>  $request->account_name,
                            "passwd"    =>  'G@ur@v123',
                            "email"     =>  $request->customer_email
                        ]);
                    }
                    $ip_address = $this->client->ip()->get();
                    $ip_address = reset($ip_address);
                    $domain = $this->client->webspace()->create([
                            'name'          => $request->domain_name,
                            'ip_address'    => $ip_address->ipAddress,
                            'owner-guid'      => $customer->guid
                        ],[
                            'ftp_login'         =>  preg_replace('/[^a-zA-Z0-9_ -]/s','',strtolower($request->account_name) ),
                            'ftp_password'      =>  'G@ur@v123'
                        ]
                    );
                }catch(\Exception $e){
                    return response()->json([
                        'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
                    ]);
                }
            }
            try{
                $createZone = $this->createZoneSet($domainName);
                $zoneInfo = $this->getSingleZone($domainName);
                if($zoneInfo['success'] && $zoneInfo['result']){
                    $accountCreate['ns_detail'] = serialize($zoneInfo['result'][0]['name_servers']);
                    
                    $cloudfareUser = CloudfareUser::where('status', 1)->first();
                    $accountCreate['cloudfare_id'] = $zoneId =  $zoneInfo['result'][0]['id'];
                    $userCount = UserServer::where(['cloudfare_user_id' => $cloudfareUser->id ])->count();
                    $updateData = ['domain_count' => $userCount+1];
                    if($userCount == 100){
                        $updateData = ['domain_count' => $userCount, 'status' => 0];
                        CloudfareUser::where('id', $cloudfareUser->id)->update($updateData);
                        CloudfareUser::where('domain_count', '!=', 100)->where(['status' =>  0])->update(['status' => 1]);
                        $cloudfareUser = CloudfareUser::where('status', 1)->first();
                    } else{
                        CloudfareUser::where('id', $cloudfareUser->id)->update($updateData);
                    }
                    $accountCreate['cloudfare_user_id'] = $cloudfareUser->id;
                    $dnsData = [
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => $domainName,
                            'cfdnsvalue' => $serverPackage->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ],
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'www.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ],
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'mail.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ],
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'webmail.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ],
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'cpanel.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ],
                        [
                            'zone_id' => $zoneId,
                            'cfdnstype' => 'A',
                            'cfdnsname' => 'ftp.'.$domainName,
                            'cfdnsvalue' => $serverPackage->company_server->ip_address,
                            'cfdnsttl' => '1',
                            'proxied' => 'true'
                        ]
                    ];
                    foreach ($dnsData as $dnsVal) {
                        $createDns = $this->createDNSRecord($dnsVal, $zoneId, $cloudfareUser->email, $cloudfareUser->user_api);
                    }
                }
                $userAccount = UserServer::updateOrCreate(['user_id' => $request->userid, 'order_id' => $orderId ], $accountCreate);
            } catch(\Exception $ex){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'DB error', 'message' => $ex->getMessage()]);
            }
            
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => ['cpanel_server_id' => jsencode_userdata($userAccount->id), 'name' => $userAccount->name, 'domain' => $userAccount->domain, 'company_name' => $serverPackage->company_server->user->company_detail->company_name, 'server_ip' => $serverPackage->company_server->ip_address, 'server_type' => $controlPanel], 'message' => 'Account has been successfully created']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
    public function installSsl(Request $request) {
		$validator = Validator::make($request->all(),[
            'cpanel_server' => 'required',
            'cert' => 'required',
            'key' => 'required'
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
            $accCreated = $this->installCertificate($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $serverPackage->domain,  $request->cert,  $request->key,  $request->cabundle);
            if(!is_array($accCreated) || !array_key_exists("result", $accCreated)){
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
            }
            
            if ($accCreated["result"]['status'] == "0") {
                $error = $accCreated['result']["errors"];
                return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'SSL updation error', 'message' => $error]);
            }
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => 'SSL has been successfully installed', 'message' => 'SSL has been successfully installed']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }

    /*
    Method Name:    check_customer_exist(HELPER)
    Developer:      Shine Dezign
    Created Date:   2022-01-06 (yyyy-mm-dd)
    Purpose:        Check customer with login name exist
    Params:         Login name
    */
    public function checkCustomerExist( $name ){
        try{
            $c = $this->client->customer()->get("login",$name);
            return $c;
        }catch(\Exception $e){
            return false;
        }
    }

    public function bandwidthStats(){
    
        try{
            $servers = UserServer::get();
            foreach($servers as $server){
                
                $dbw = DomainBandwidthStat::where(['user_server_id' => $server->id])->where('stats_date', 'LIKE', date('Y-m-d')."%")->count();
                if($dbw < 1){
                    $dbw = DomainBandwidthStat::where(['user_server_id' => $server->id])->count();
                    if($dbw >= 5){
                        $dbw = DomainBandwidthStat::where(['user_server_id' => $server->id])->first();
                        $dbw->delete();
                    }
                    $server->company_server_package->company_server;
                    $linkserver = $server->company_server_package->company_server->link_server ? unserialize($server->company_server_package->company_server->link_server) : 'N/A';
                    $controlPanel = $bandwidth = null;
                    if('N/A' == $linkserver)
                    return false;
                    $controlPanel = $linkserver['controlPanel'];
                    if('cPanel' == $controlPanel){
                        $cpanelStats = $this->getCpanelStats($server->company_server_package->company_server_id, strtolower($server->name));
                        if(!is_array($cpanelStats) ){
                            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
                        }
                        if ((array_key_exists("result", $cpanelStats) && $cpanelStats["result"]['status'] == "0")) {
                            $error = $cpanelStats["result"]['errors'];
                            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => $error]);
                        }
                        $cpanelStatArray = [];
                        foreach( $cpanelStats['result']['data'] as $cpanelStat){
                            $count = $cpanelStat['count'];
                            if(array_key_exists("_count", $cpanelStat))
                            $count = $cpanelStat['_count'];
                            if('bandwidthusage' == $cpanelStat['name'])
                            $bandwidth = $count;
                        }
                    }
                    if('Plesk' == $controlPanel){         
                        try{

                            $this->client = new Client($server->company_server_package->company_server->ip_address);
                            $this->client->setCredentials($linkserver['username'], $linkserver['apiToken']);
                            
                            $request = <<<STR
                            <packet>
                                <site>
                                <get>
                                <filter>
                                    <name>{$server->domain}</name>
                                </filter>
                                <dataset>
                                    <stat/>
                                </dataset>
                                </get>
                                </site>
                            </packet>
                            STR;
                            $response = $this->client->request($request);
                            
                            $bandwidth =   (string)$response->data->stat->traffic;
                        }catch(\Exception $e){
                            return response()->json([
                                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
                            ]);
                        }
                    }
                    if($bandwidth){
                        DomainBandwidthStat::create([
                            'user_server_id' => $server->id,
                            'stats_date' => date('Y-m-d H:i:s'),
                            'bandwidth' => $bandwidth,
                            'status' => 1
                        ]);
                    }
                }
            }
        } catch(\Exception $e){
            return false;
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
            if ((array_key_exists("data", $cpanelStats) && $cpanelStats["data"]['result'] == "0")) {
                $error = $cpanelStats["data"]['reason'];
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
                    $count = $cpanelStat['count'];
                    if(array_key_exists("_count", $cpanelStat))
                    $count = $cpanelStat['_count'];
                    $max = $cpanelStat['max'];
                    if(array_key_exists("_max", $cpanelStat))
                    $max = $cpanelStat['_max'];
                    array_push($cpanelStatArray, ['item' => $cpanelStat['item'], 'name' => $cpanelStat['name'], 'count' => $count, 'max' => $max, 'percent' => $cpanelStat['percent'], 'value' => $value, 'units' => $units]);
            }
            $phpVesion = null;
            $accCreated = $this->phpVersions($serverPackage->company_server_package->company_server_id, strtolower($serverPackage->name), $request->version);
            if(is_array($accCreated) && array_key_exists("metadata", $accCreated) && array_key_exists("result", $accCreated['metadata']) && 0 != $accCreated['metadata']["result"]){
                $phpVesion = 1;
            }
            $developementModeValue = $securityLevelValue = null;
            if($serverPackage->cloudfare_user_id){

                $developementMode = $this->getModeSetting('development_mode', $serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
                $securityLevel = $this->getModeSetting('security_level', $serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email, $serverPackage->cloudfare_user->user_api);
                
                if($developementMode['result'] != 'error' && $developementMode['success']){
                    $developementModeValue = $developementMode['result']['value'];
                }
                if($securityLevel['result'] != 'error' && $securityLevel['success']){
                    $securityLevelValue = $securityLevel['result']['value'];
                }
            }
            $domainInfo = [
                "accountStats" => $cpanelStatArray,
                'phpVersion' => $phpVesion,
                'developementMode' => $developementModeValue,
                'securityLevel' => $securityLevelValue
            ];
            return response()->json(['api_response' => 'success', 'status_code' => 200, 'data' => $domainInfo, 'message' => 'Domian information has been fetched']);
        }
        catch(Exception $ex){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
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
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
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
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $ex->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ConnectException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => $e->getMessage()]);
        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Server error', 'message' => $e->getMessage()]);
        }
    }
}
