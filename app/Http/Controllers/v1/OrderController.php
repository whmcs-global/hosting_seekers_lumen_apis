<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, OrderTransaction, Invoice, WalletPayment};
use App\Traits\{AutoResponderTrait, SendResponseTrait, GetDataTrait};
use DB;

class OrderController extends Controller
{
    use SendResponseTrait, AutoResponderTrait, GetDataTrait;
    public function invoiceList(Request $request, $id = null){
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
            $statusArray = ['UnPaid', 'Paid', 'Cancelled'];
            $orders = Invoice::when(($request->has('daterange_filter') && $request->daterange_filter != ''), function($q) use($start, $end){
                $q->whereBetween('created_at', [$start, $end]);
            })->when(($request->has('search_keyword') && $request->search_keyword != ''), function($q) use($request){
                $q->where(function ($quer) use ($request) {
                    $quer->whereHas('order', function( $qu ) use($request){
                        $qu->where('order_id', 'LIKE', '%'.$request->search_keyword.'%');
                    });
                })->orWhere('invoice_id', 'LIKE', '%'.$request->search_keyword.'%');
            })->when(($request->has('status') && $request->status != ''), function($q) use($request, $statusArray){
                $q->where('status', array_search($request->status, $statusArray));
            })->when($id, function($q) use($id){
                $q->where('order_id', jsdecode_userdata($id));
            })->where('user_id', $request->userid)->orderBy($sortBy, $orderBy)->paginate(config('constants.PAGINATION_NUMBER'));
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
                    array_push($orderData, ['id'=> jsencode_userdata($order->id), 'invoice_id' => $order->invoice_id, 'order_id' => $order->order->order_id, 'currency_icon' => $order->order->currency->icon, 'invoice_url' => 'https://dev.hostingseekers.com/invoice/'.jsencode_userdata($order->id),  'payable_amount' => $order->order->payable_amount, 'status' => $statusArray[$order->status], 'created_at' => change_date_format($order->created_at)]);
                }
                $ordersData['data'] = $orderData;
                $orderArray = $ordersData;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $orderArray);
            
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
    public function ordersHistory(Request $request){
        
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
            $statusArray = ['In Progress', 'Completed', 'Cancelled'];
            $orders = Order::when(($request->has('daterange_filter') && $request->daterange_filter != ''), function($q) use($start, $end){
                $q->whereBetween('created_at', [$start, $end]);
            })->when(($request->has('search_keyword') && $request->search_keyword != ''), function($q) use($request){
                $q->where('order_id', 'LIKE', '%'.$request->search_keyword.'%');
            })->when(($request->has('status') && $request->status != ''), function($q) use($request, $statusArray){
                $q->where('status', array_search($request->status, $statusArray));
            })->where('user_id', $request->userid)->orderBy($sortBy, $orderBy)->paginate(config('constants.PAGINATION_NUMBER'));
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
                    array_push($orderData, ['id'=> jsencode_userdata($order->id), 'order_id' => $order->order_id, 'currency_icon' => $order->currency->icon,  'payable_amount' => $order->payable_amount, 'is_cancelled' => $order->is_cancelled, 'cancelled_on' => $order->cancelled_on ? change_date_format($order->cancelled_on) : null, 'status' => $statusArray[$order->status], 'created_at' => change_date_format($order->created_at)]);
                }
                $ordersData['data'] = $orderData;
                $orderArray = $ordersData;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $orderArray);
            
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
    public function ordersTransactions(Request $request){
        
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
            $statusArray = ['Failed', 'Completed', 'Cancelled', 'Refunded'];
            $orders = OrderTransaction::when(($request->has('daterange_filter') && $request->daterange_filter != ''), function($q) use($start, $end){
                $q->whereBetween('created_at', [$start, $end]);
            })->when(($request->has('search_keyword') && $request->search_keyword != ''), function($q) use($request){
                $q->where(function ($quer) use ($request) {
                    $quer->whereHas('order', function( $qu ) use($request){
                        $qu->where('order_id', 'LIKE', '%'.$request->search_keyword.'%');
                    })->orWhereHas('invoice', function( $qu ) use($request){
                        $qu->where('id', 'LIKE', '%'.$request->search_keyword.'%')
                        ->orWhere('invoice_id', 'LIKE', '%'.$request->search_keyword.'%');
                    });
                })->orWhere('trans_id', 'LIKE', '%'.$request->search_keyword.'%');
            })->when(($request->has('status') && $request->status != ''), function($q) use($request, $statusArray){
                $q->where('status', array_search($request->status, $statusArray));
            })->where('user_id', $request->userid)->orderBy($sortBy, $orderBy)->paginate(config('constants.PAGINATION_NUMBER'));
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
                    array_push($orderData, ['id'=> jsencode_userdata($order->id), 'invoice_id' => $order->invoice->invoice_id, 'order_id' => $order->order->order_id, 'trans_id' => $order->trans_id, 'currency_icon' => $order->currency->icon, 'payable_amount' => $order->payable_amount, 'status' => $statusArray[$order->status], 'created_at' => change_date_format($order->updated_at)]);
                }
                $ordersData['data'] = $orderData;
                $orderArray = $ordersData;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $orderArray);
            
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
    public function walletTransactions(Request $request){
        
        try {
            
            $walletPayment = WalletPayment::select(DB::raw('sum(amount) as total'), 'payment_mode')->where('user_id', $request->userid)
            ->groupBy('payment_mode')
            ->get();
            $walletPayments = $walletPayment->toArray();
            $creditAmount = $debitAmount = 0;
            foreach($walletPayments as $amounts){
                if($amounts['payment_mode'] == 'Credit'){
                    $creditAmount = $amounts['total'];
                }
                else
                $debitAmount = $amounts['total'];
            }
            $totalAmount = $creditAmount - $debitAmount;
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
            $statusArray = ['Failed', 'Completed', 'Cancelled', 'Refunded'];
            $orders = WalletPayment::when(($request->has('daterange_filter') && $request->daterange_filter != ''), function($q) use($start, $end){
                $q->whereBetween('created_at', [$start, $end]);
            })->when(($request->has('search_keyword') && $request->search_keyword != ''), function($q) use($request){
                $q->where(function ($quer) use ($request) {
                    $quer->whereHas('order', function( $qu ) use($request){
                        $qu->where('order_id', 'LIKE', '%'.$request->search_keyword.'%');
                    })->orWhereHas('invoice', function( $qu ) use($request){
                        $qu->where('id', 'LIKE', '%'.$request->search_keyword.'%')
                        ->orWhere('invoice_id', 'LIKE', '%'.$request->search_keyword.'%');
                    });
                })->orWhere('trans_id', 'LIKE', '%'.$request->search_keyword.'%');
            })->when(($request->has('status') && $request->status != ''), function($q) use($request, $statusArray){
                $q->where('status', array_search($request->status, $statusArray))
                ->orWhereHas('order_transaction', function( $qu ) use($request, $statusArray){
                    $qu->where('status', array_search($request->status, $statusArray));
                });
            })->where('user_id', $request->userid)->orderBy($sortBy, $orderBy)->paginate(config('constants.PAGINATION_NUMBER'));
            $orderArray = [];
            $page = 1;
            if($request->has('page'))
            $page = $request->page;
            if($orders->isNotEmpty()) {
                $ordersData = [
                    'currency_icon' => $orders[0]->currency->icon,
                    'walletAmount' => $totalAmount,
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
                    array_push($orderData, ['id'=> jsencode_userdata($order->id), 'payment_mode' => $order->payment_mode, 'comments' => $order->comments, 'currency_icon' => $order->currency->icon, 'amount' => $order->amount, 'status' => $statusArray[$order->status], 'created_at' => change_date_format($order->created_at)]);
                }
                
                $ordersData['data'] = $orderData;
                $orderArray = $ordersData;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $orderArray);
            
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
}
