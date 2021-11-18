<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, OrderTransaction, Invoice};
use App\Traits\{AutoResponderTrait, SendResponseTrait, GetDataTrait};

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
            $orders = Invoice::when(($request->has('daterange_filter') && $request->daterange_filter != ''), function($q) use($start, $end){
                $q->whereBetween('created_at', [$start, $end]);
            })->when(($request->has('search_keyword') && $request->search_keyword != ''), function($q) use($request){
                $q->where(function ($quer) use ($request) {
                    $quer->whereHas('order', function( $qu ) use($request){
                        $qu->where('order_id', 'LIKE', '%'.$request->search_keyword.'%');
                    });
                })->orWhere('invoice_id', 'LIKE', '%'.$request->search_keyword.'%');
            })->when(($request->has('status') && $request->status != ''), function($q) use($request){
                $q->where('trans_status', $request->status);
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
                    array_push($orderData, ['id'=> jsencode_userdata($order->id), 'invoice_id' => $order->invoice_id, 'order_id' => $order->order->order_id, 'currency_icon' => $order->order->currency->icon, 'invoice_url' => 'http://192.168.0.129:8000/invoice/'.jsencode_userdata($order->id),  'payable_amount' => $order->order->payable_amount, 'trans_status' => $order->trans_status, 'created_at' => change_date_format($order->created_at)]);
                }
                $ordersData['data'] = $orderData;
                $orderArray = ['refinedData' => $ordersData];
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
            $orders = Order::when(($request->has('daterange_filter') && $request->daterange_filter != ''), function($q) use($start, $end){
                $q->whereBetween('created_at', [$start, $end]);
            })->when(($request->has('search_keyword') && $request->search_keyword != ''), function($q) use($request){
                $q->where('order_id', 'LIKE', '%'.$request->search_keyword.'%');
            })->when(($request->has('status') && $request->status != ''), function($q) use($request){
                $q->where('trans_status', $request->status);
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
                    array_push($orderData, ['id'=> jsencode_userdata($order->id), 'order_id' => $order->order_id, 'currency_icon' => $order->currency->icon,  'payable_amount' => $order->payable_amount, 'trans_status' => $order->trans_status, 'created_at' => change_date_format($order->created_at)]);
                }
                $ordersData['data'] = $orderData;
                $orderArray = ['refinedData' => $ordersData];
            }
            return $this->apiResponse('success', '200', 'Data fetched', $orderArray);
            
        } catch ( \Exception $e ) {
            dd($e);
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
            $orders = OrderTransaction::when(($request->has('daterange_filter') && $request->daterange_filter != ''), function($q) use($start, $end){
                $q->whereBetween('created_at', [$start, $end]);
            })->when(($request->has('search_keyword') && $request->search_keyword != ''), function($q) use($request){
                $q->where(function ($quer) use ($request) {
                    $quer->whereHas('order', function( $qu ) use($request){
                        $qu->where('order_id', 'LIKE', '%'.$request->search_keyword.'%');
                    })->orWhereHas('invoice', function( $qu ) use($request){
                        $qu->where('id', 'LIKE', '%'.$request->search_keyword.'%');
                    });
                })->orWhere('trans_id', 'LIKE', '%'.$request->search_keyword.'%');
            })->when(($request->has('status') && $request->status != ''), function($q) use($request){
                $q->where('trans_status', $request->status);
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
                    array_push($orderData, ['id'=> jsencode_userdata($order->id), 'invoice_id' => $order->invoice->invoice_id, 'order_id' => $order->order->order_id, 'trans_id' => $order->trans_id, 'currency_icon' => $order->currency->icon, 'payable_amount' => $order->payable_amount, 'trans_status' => $order->trans_status, 'created_at' => change_date_format($order->updated_at)]);
                }
                $ordersData['data'] = $orderData;
                $orderArray = ['refinedData' => $ordersData];
            }
            return $this->apiResponse('success', '200', 'Data fetched', $orderArray);
            
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
}
