<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Config, Validator};
use Illuminate\Support\Str;
use App\Models\{Order, OrderTransaction, WalletPayment, Invoice, UserServer, UserTerminatedAccount, User, DelegateDomainAccess};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait, PowerDnsTrait, GetDataTrait};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PleskX\Api\Client;
use Carbon\Carbon;

class ServiceController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait, PowerDnsTrait, SendResponseTrait, CommonTrait, GetDataTrait;

    public function cancelService(Request $request){
        try {
            $id = jsdecode_userdata($request->order_id);
            $orders = Order::where(['user_id' => $request->userid, 'id' => $id, 'status' => 1, 'is_cancelled' => 0])->first();
            if($orders) {
                $billingCycle = $this->billingCycleName($orders->ordered_product->billing_cycle);
                $cancelDays = config('constants.DAYS_FOR_MONTHLY_BILLING');
                if($billingCycle == 'Annually')
                $cancelDays = config('constants.DAYS_FOR_YEARLY_BILLING');
                $to = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $orders->created_at);
                $from = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'));
                $diff_in_days = $to->diffInDays($from) + 1;
                if($diff_in_days <= $cancelDays){
                    if(!is_null($orders->user_server)){
                        try
                        {
                            $serverId = $orders->user_server->id;
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
                                        'api_response' => 'error', 'status_code' => 400, 'message' => $e->getMessage()
                                    ]);
                                }
                            }
                            $serverPackage->status = 2;
                            $serverPackage->save();
                            DelegateDomainAccess::where('user_server_id', $serverPackage->id)->delete();
                            $this->wgsDeleteDomain(['domain' => $serverPackage->domain]);
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
                    $orders->is_cancelled = 1;
                    $orders->cancelled_on = date('Y-m-d H:i:s');
                    if($orders->user_server){
                        $orders->user_server->comments = $request->comments;
                    }
                    $orders->push();
                    $browseDetail = $this->getUserBrowseDetail();
                    $ordersTransaction = OrderTransaction::create([
                        'payment_mode' => 'Wallet',
                        'user_id' => $orders->user_id,
                        'invoice_id' => $orders->invoice[0]->id,
                        'order_id' => $orders->id,
                        'currency_id' => $orders->currency_id,
                        'amount' => $orders->payable_amount,
                        'payable_amount' => $orders->payable_amount,
                        'ipn_id' => 'Wallet', 
                        'trans_id' => Str::random(12).time().Str::random(12),
                        'trans_detail' => serialize($browseDetail),
                        'trans_status' => 'Refunded',
                        'status' => 3,
                    ]);
                    WalletPayment::create([
                        'user_id' => $orders->user_id,
                        'credit_by' => $orders->company_id,
                        'payment_mode' => 'Credit',
                        'currency_id' => $orders->currency_id,
                        'amount' => $orders->payable_amount,
                        'comments' => $request->comments,
                        'order_id' => $orders->id,
                        'order_transaction_id' => $ordersTransaction->id,
                        'raw_data' => serialize($browseDetail),
                        'status' => 1
                    ]);
                    $usersDetail = User::where('id', $request->userid)->first();
                    $amount = $this->getCurrency($orders->currency->name, $orders->payable_amount, $usersDetail->currency->name);
                    $usersDetail->amount = $usersDetail->amount+$amount;
                    $usersDetail->save();
                    return $this->apiResponse('success', '200', "An amount of ".$amount." ".$usersDetail->currency->name." has been refunded to your wallet.");
                }
            }
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        } catch ( \Exception $e ) {
            return $e;
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
}
