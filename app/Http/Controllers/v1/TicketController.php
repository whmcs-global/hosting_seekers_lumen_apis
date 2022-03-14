<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\SendResponseTrait;
use App\Models\{User, Role, Order};

class TicketController extends Controller
{
    use SendResponseTrait;
    public function getDetails(Request $request) {
        $user = User::find($request->user_id);
        if($user){
            $userArray = [
                'id' => jsencode_userdata($user->id),
                'name' => $user->first_name.' '.$user->last_name,
                'email' => $user->email,
                'role' => $request->role,
                'currency' => $user->currency->icon,
                'currencyName' => $user->currency->name,
                'amount' => $user->amount,
                'currencyId' => jsencode_userdata($user->currency_id),
                'currencyUpdated' => $user->currency_updated,
            ];
            if($request->role == 'Company'){
                $userArray['companyName'] = $user->company_detail->company_name;
                $userArray['companySlug'] = $user->company_detail->slug;
            }
            if($request->role == 'User'){
                $serviceArray = [];
                if($user->order){
                    foreach($user->order as $order){
                        $serviceProvider = "OrderNo. #".$order->order_id." ".$order->company->company_detail->company_name;
                        if($order->user_server){
                            $serviceProvider = $serviceProvider."(".$order->user_server->domain.")";
                        }
                        array_push($serviceArray, ['id' => jsencode_userdata($order->id.'#'.$order->company_id), 'order_id' => $order->order_id,'serviceProviderId' => jsencode_userdata($order->company_id), 'serviceProvider' => $serviceProvider]);
                    }
                }
                $userArray['services'] = $serviceArray;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $userArray);
        }
        return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
    }
    public function getUserDetails(Request $request) {
        $userIdArray = [];
        if($request->companyTicket){
            array_push($userIdArray, jsdecode_userdata($request->companyTicket));
        }
        if(count($userIdArray) > 0){
            try{
            $companies = Order::whereIn('id', $userIdArray)->first();
            } catch ( \Exception $e ) {
                return $this->apiResponse('error', '400', $e->getMessage());
            }
            $userDetails = null;
            if($companies){
                $serviceProvider = "OrderNo. #".$companies->order_id." ".$companies->company->company_detail->company_name;
                if($companies->user_server){
                    $serviceProvider = $serviceProvider."(".$companies->user_server->domain.")";
                }
                $userDetails = ['id' => jsencode_userdata($companies->id), 'order_id' => $companies->order_id, 'serviceProviderId' => jsencode_userdata($companies->company_id), 'serviceProvider' => $companies->company->company_detail->company_name, 'serviceBuyer' => $companies->user->first_name.' '.$companies->user->last_name, 'services' => $serviceProvider ];
            }
            return $this->apiResponse('success', '200', 'Data fetched', $userDetails);
        }
        return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
    }
    public function getTickets(Request $request){
        $apiUrl = config('constants.TICKET_URL').'/usertickets/'.jsencode_userdata($request->user_id);
        $headers = ['Content-Type: application/json'];

        $response = hitCurl($apiUrl, 'GET', null, $headers);
        $response = json_decode($response, true);
        $tickets = null;
        if($response && $response['success']){ 
            $data = [];
            $tickets = $response['data'];
        }
        return $this->apiResponse('success', '200', 'Data fetched', $tickets);
    }
    
    public function createTicket(Request $request) {
        try {
            $apiUrl = config('constants.TICKET_URL').'/createTicket';
            // dd($apiUrl);
            $headers = ['Content-Type: application/json']; 
            $response = hitCurl($apiUrl, 'POST', $request->all() , $headers);
            // return [$apiUrl, $response ];
            $response = json_decode($response, true);
            if($response && $response['success']){
                return $this->apiResponse('success', '200', 'Feedback has been submitted successfully.');
            }
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        } catch ( \Exception $e ) { 
            return $this->apiResponse('error', '400', $e->getMessage());
        }
    }
}
