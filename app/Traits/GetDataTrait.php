<?php
 
namespace App\Traits;
Use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;
use App\Models\{Categories, Region, Language, User, User_detail, Plan, CurrencyExchangeRate, Currency};
use DB;
trait GetDataTrait {

     /*
    Method Name:    getPlans
    Developer:      Shine Dezign
    Created Date:   2021-02-23 (yyyy-mm-dd)
    Purpose:        To get category list
    Params:         
    */
    public function getUserPlans() {
        $plans = Plan::where('status', 1)->get();
        return $plans;
    }
    
    /*
    Method Name:    getUserCetail
    Developer:      Shine Dezign
    Created Date:   2021-02-23 (yyyy-mm-dd)
    Purpose:        To get category list
    Params:         
    */
    public function getUserDetail($id = NULL,$bool = 0) {

        if($bool == 0) {
            $user_data = DB::table('users')->select('users.first_name','users.last_name','users.email','users.currency_id','users.currency_updated','users.amount','user_details.mobile','user_details.address','user_details.city','user_details.state_id','user_details.country_id','user_details.zipcode', 'users.last_login', 'users.last_login_data')->join('user_details','user_details.user_id','=','users.id')->where(['users.id' => $id])->get()->toArray();
            return $user_data;
        }
        else{
            $user_data = User::with('user_details','user_profile','user_active_plans','user_plan_history','orders')
                ->where('id', '=', $id)
                ->first();
        }
        
        return $user_data;
    }
    
    /*
    Method Name:    getCategoryList
    Developer:      Shine Dezign
    Created Date:   2021-02-22 (yyyy-mm-dd)
    Purpose:        To get category list
    Params:         
    */
    public function getCategoryList() {
        $categories = Categories::where('status', 1)->get();
        return $categories;
    }
    
    
    // public function createToken(){
    //     $token = app('hash')->make(Str::random(15));
    //     return md5($token);
    // }
    
    public function getCurrency($currnecyName = null, $value = null, $currencyTo = null) {
        try{
            $currencyExchangeRate = CurrencyExchangeRate::first();
            $currencies = unserialize($currencyExchangeRate['rates']);
            $currentValue = $value/ $currencies[$currnecyName];
            $currency = Currency::where('name', $currencyTo)->first();
            return number_format($currentValue*$currencies[$currencyTo], 2);
        } catch(\Exception $e){
            return 0;
        }
    }
}