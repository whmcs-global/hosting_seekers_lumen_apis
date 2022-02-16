<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\SendResponseTrait;
use App\Models\User;

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
                        array_push($serviceArray, ['id' => jsencode_userdata($order->id), 'order_id' => $order->order_id,'serviceProviderId' => jsencode_userdata($order->company_id), 'serviceProvider' => $serviceProvider]);
                    }
                }
                $userArray['services'] = $serviceArray;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $userArray);
        }
        return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
    }
}
