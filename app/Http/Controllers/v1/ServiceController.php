<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Config, Validator};
use Illuminate\Support\Str; 
use hisorange\BrowserDetect\Parser as Browser;
use App\Models\{Order, OrderTransaction, WalletPayment, Invoice, UserServer, UserTerminatedAccount, User, DelegateDomainAccess};
use App\Traits\{CpanelTrait, SendResponseTrait, CommonTrait, CloudfareTrait, GetDataTrait, AutoResponderTrait };
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PleskX\Api\Client;
use Carbon\Carbon;

class ServiceController extends Controller
{
    use CpanelTrait, CommonTrait, SendResponseTrait, CloudfareTrait, SendResponseTrait, CommonTrait, GetDataTrait, AutoResponderTrait ;

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
                            if($serverPackage->cloudfare_user_id)
                                $this->deleteZone($serverPackage->cloudfare_id, $serverPackage->cloudfare_user->email,  $serverPackage->cloudfare_user->user_api);
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
                    $domain ="";
                    $orders->is_cancelled = 1;
                    $orders->cancelled_on = date('Y-m-d H:i:s');
                    if($orders->user_server){
                        $orders->user_server->comments = $request->comments;
                        $domain = '<strong>Domain : </strong>'.$request->domain;
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

                    /*Send Email*/
                    $userEmail = $usersDetail->email;  
                    $userid = $usersDetail->id;   
                    $service = $orders->ordered_product->product->name;  
                    $companyName = $orders->ordered_product->product->user->company_detail->company_name;
                    $name = $usersDetail->first_name.' '.$usersDetail->last_name; 
                    $logtoken = Str::random(12);
                    $logtokenUrl = 'https://www.hostingseekers.com/footer/image/'.$logtoken.'.png'; 
                    $unsubscribeUrl = 'https://www.hostingseekers.com/unsubscribe?email='.jsencode_userdata($userEmail).'&c='.jsencode_userdata('Company');
                    $unsubscribe = 'If you prefer not to receive emails, you may
                    <a href="'.$unsubscribeUrl.'" target="_blank" style="color:#ee1c2ab5;text-decoration:underline;">
                    unsubscribe
                    </a>';

                    $template = $this->get_template_by_name('CANCELLATION_REQUEST_CONFIRMATION');
                    $string_to_replace = array('{{client_name}}', '{{$service}}', '{{domain_name}}', '{{$company_name}} ', '{{$logToken}}', '{{$unsubscribe}}');
                    $string_replace_with = array($name, $service, $domain, $companyName, $logtokenUrl, $unsubscribe);
                    $newval = str_replace($string_to_replace, $string_replace_with, $template->template);
                    
                    $emailArray = [];
                    array_push($emailArray, ['user_id' => $userid, 'templateId' => $template->id, 'templateName' => $template->name, 'email' => $userEmail, 'subject' => $template->subject, 'body' => $newval, 'logtoken' => $logtoken, 'raw_data' => null, 'bcc' => null]);
                    $emailData = ['emails' => $emailArray];
                    $routesUrl = Config::get('constants.SMTP_URL');
                    $response = hitCurl($routesUrl, 'POST', $emailData);
                    
                    /*End Send Email*/ 

                    return $this->apiResponse('success', '200', "An amount of ".$amount." ".$usersDetail->currency->name." has been refunded to your wallet.");
                }
            }
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', $e->getMessage());
        }
    }
}
