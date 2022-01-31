<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function cancelService(Request $request, $id){
        
        try {
            $id = jsdecode_userdata($id);
            $orders = Order::where(['user_id' => $request->userid, 'status' => 1])->first();
            if($orders) {
                $billingCycle = $this->billingCycleName($order->ordered_product->billing_cycle);
                $cancelDays = config('constants.DAYS_FOR_MONTHLY_BILLING');
                if($billingCycle == 'Annually')
                $cancelDays = config('constants.DAYS_FOR_YEARLY_BILLING');
                $to = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at);
                $from = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'));
                $diff_in_days = $to->diffInDays($from) + 1;
                if($diff_in_days <= $cancelDays){
                    $order->is_cancelled = 1;
                    $order->cancelled_on = date('Y-m-d H:i:s');
                    $order->save();
                }
            }
            return $this->apiResponse('success', '200', 'Data fetched', $orderArray);
            
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
}
