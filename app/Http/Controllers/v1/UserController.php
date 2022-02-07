<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;

use App\Models\User_detail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\AutoResponderTrait;
use App\Traits\SendResponseTrait;
use hisorange\BrowserDetect\Parser;
use App\Models\{UserProfile, User, UserToken, ContactDetail, Company_review, Country, State, Order, DelegateAccount, Currency};
use App\Traits\GetDataTrait;

class UserController extends Controller
{
    use SendResponseTrait, AutoResponderTrait, GetDataTrait;

    /*
    Method Name:    updateAddress
    Developer:      Shine Dezign
    Created Date:   2021-02-13 (yyyy-mm-dd)
    Purpose:        To update address after login
    Params:         [address, city, zipcode, state, country]
    */
    public function updateAddress(Request $request) {
        $validator = Validator::make($request->all(),[
			'address' => 'required|string',  
			'city' => 'required|string',
            'zipcode' => 'required|min:4|max:8',
			'state' => 'required',
			'country' => 'required',
        ]);

        if($validator->fails()){
            return $this->apiResponse('error', '422', $validator->errors()->all());
        }
        try
        {
            $users = User::findOrFail($request->userid);
            $postData = $request->all();
			$users->user_detail->address = $postData['address'];
			$users->user_detail->city = $postData['city'];
			$users->user_detail->zipcode = $postData['zipcode'];
			$users->user_detail->country_id = jsdecode_userdata($postData['country']);
			$users->user_detail->state_id = jsdecode_userdata($postData['state']);
            $users->push();       
            return $this->apiResponse('success', '200', 'Users address '.config('constants.SUCCESS.UPDATE_DONE'));

        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }

    /*
    Method Name:    getCountries
    Developer:      Shine Dezign
    Created Date:   2021-11/08 (yyyy-mm-dd)
    Purpose:        To get country Details
    Params:         [id]
    */

    public function getCountries($id = null) {
        if($id)
        $id = jsdecode_userdata($id);
        $countries = Country::when(($id != null), function($q) use($id){
            $q->where('id', $id);
        })
        ->get(['id', 'name']);
        $dataArray = [];
        foreach($countries as $country){
            array_push($dataArray, ['id' => jsencode_userdata($country->id), 'name' => $country->name ]);
        }
        return $this->apiResponse('success', '200', 'Data fetched', $dataArray);
    }

    /*
    Method Name:    getCurrencies
    Developer:      Shine Dezign
    Created Date:   2021-11/08 (yyyy-mm-dd)
    Purpose:        To get currencies Details
    Params:         [id]
    */

    public function getCurrencies($id = null) {
        if($id)
        $id = jsdecode_userdata($id);
        $countries = Currency::when(($id != null), function($q) use($id){
            $q->where('id', $id);
        })
        ->get(['id', 'name']);
        $dataArray = [];
        foreach($countries as $country){
            array_push($dataArray, ['id' => jsencode_userdata($country->id), 'name' => $country->name ]);
        }
        return $this->apiResponse('success', '200', 'Data fetched', $dataArray);
    }

    /*
    Method Name:    getStates
    Developer:      Shine Dezign
    Created Date:   2021-11/08 (yyyy-mm-dd)
    Purpose:        To get state Details by country id
    Params:         [countryId, id]
    */

    public function getStates($countryId,  $id = null) {
        if($id)
        $id = jsdecode_userdata($id);
        $countryId = jsdecode_userdata($countryId);
        $countries = State::when(($id != null), function($q) use($id){
            $q->where('id', $id);
        })
        ->where('country_id', $countryId)
        ->get(['id', 'country_id', 'name']);
        $dataArray = [];
        foreach($countries as $country){
            array_push($dataArray, ['id' => jsencode_userdata($country->id), 'country_id' => jsencode_userdata($country->country_id), 'name' => $country->name ]);
        }
        return $this->apiResponse('success', '200', 'Data fetched', $dataArray);
    }

    /*
    Method Name:    getDetails
    Developer:      Shine Dezign
    Created Date:   2021-11/08 (yyyy-mm-dd)
    Purpose:        To get user Details
    Params:         [userid]
    */

    public function getDetails(Request $request) {
        $user_data = $this->getUserDetail($request->userid);
        $userArray = [];
        foreach($user_data[0] as $key => $user){

            if('last_login_data' == $key){
                $userArray['last_login_ip'] = null;
                if($user){
                $lastLoginData = unserialize($user);
                $userArray['last_login_ip'] = $lastLoginData['ip_address'];
                }
            } elseif('state_id' == $key || 'country_id' == $key)
            {

                $userArray[$key] = $user ? jsencode_userdata($user) : null;
            } elseif('currency_id' == $key)
            {
                $currency  = Currency::where('id', $user)->first();
                $userArray[$key] = $currency ? $currency->icon : null;
            }
            else{
                $userArray[$key] = $user;
            }
        }
        $ratingCount = Company_review::where('user_id', $request->userid)->count();
        $userArray['total_reviews'] = $ratingCount;
        $orderCount = Order::where('user_id', $request->userid)->count();
        $userArray['total_orders'] = $orderCount;
        $delegateUsers = DelegateAccount::where('delegate_user_id', $request->userid)->get();
        $delegateUserArray = [];
        foreach($delegateUsers as $user){
            array_push($delegateUserArray, ['id' => jsencode_userdata($user->id), 'name' => $user->user->first_name.' '.$user->user->last_name]);
        }
        $userArray['delegateUsers'] = $delegateUserArray;
        $dataArray = $userArray;
        return $this->apiResponse('success', '200', 'Data fetched', $dataArray);
    }

    /*
    Method Name:    updateDetails
    Developer:      Shine Dezign
    Created Date:   2021-02-22 (yyyy-mm-dd)
    Purpose:        To update a UserDetails
    Params:         [userid,first_name,last_name,mobile]
    */

    public function updateDetails(Request $request) {

        $rules = [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
			'mobile' => 'required|digits:10|unique:user_details,mobile,'.$request->userid.',user_id',
        ];

        $messages = [
            'first_name.required' => 'First name cannot be Empty',
            'last_name.required' => 'Last name cannot be Empty',
            'mobile.required' => 'Mobile Cannot be Empty'
        ];

		$validator = Validator::make($request->all(), $rules, $messages);
		if ($validator->fails()) { 
            return $this->apiResponse('error', '422', $validator->errors()->all());
        }

        $browser = new Parser(null, null, [
            'cache' => [
                'interval' => 86400 // This will overide the default configuration.
            ]
        ]);
        
        try{
            $users = User::findOrFail($request->userid);
            $postData = $request->all();
			$users->first_name = $postData['first_name'];
			$users->last_name = $postData['last_name'];
			$users->user_detail->mobile = $postData['mobile'];
            if($request->has('currency_id')){
                $users->currency_id = jsdecode_userdata($postData['currency_id']);
                $users->currency_updated = 1;
            }
            $users->push();  

            return $this->apiResponse('success', '200', 'Users details '.config('constants.SUCCESS.UPDATE_DONE'));
        }
        catch(\Exception $e){
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
        
    }

    /*
    Method Name:    updatePassword
    Developer:      Shine Dezign
    Created Date:   2021-02-23 (yyyy-mm-dd)
    Purpose:        change password
    Params:         [password, old_password, password_confirmation]
    */
    
    public function updatePassword(Request $request){

        $rules = [
            'old_password' => 'required',
            'password' => 'required_with:password_confirmation|string|confirmed|required',
            'password_confirmation' => 'required'
        ];
        $messages = [
            'password.required' => 'Password is required',
            'password.confirmed' => 'Confirmed Password not matched with password'
        ];

        $validator = Validator::make($request->all(), $rules,$messages);
		if ($validator->fails()) { 
            return $this->apiResponse('error', '422', $validator->errors()->all());
        }

        try {
            $users = User::findOrFail($request->userid);
            if (!(app('hash')->check($request->get('old_password'), $users->password))) {
                return $this->apiResponse('error', '422', [config('constants.ERROR.PASSWORD_MISMATCH')]);
            }
            if(strcmp($request->get('old_password'), $request->get('password')) == 0){
                return $this->apiResponse('error', '422', [config('constants.ERROR.PASSWORD_SAME')]);
            }
            $data = array(
                'password' => app('hash')->make($request->password)
            );
            $record = User::where('id', $request->userid)->update($data);            
            return $this->apiResponse('success', '200',  'Password '.config('constants.SUCCESS.UPDATE_DONE'));
        
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
    
    /*
    Method Name:    updatePassword
    Developer:      Shine Dezign
    Created Date:   2021-02-23 (yyyy-mm-dd)
    Purpose:        Loutout particular device by token
    Params:         
    */

    public function logout(Request $request) {
        try {
            UserToken::where(['user_id' => $request->userid, 'access_token' => $request->access_token])->delete();
            return $this->apiResponse('success', '200', 'You has been Logout Successfully');
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }

    /*
    Method Name:    logoutAll
    Developer:      Shine Dezign
    Created Date:   2021-02-23 (yyyy-mm-dd)
    Purpose:        Logout all devices
    Params:         
    */    
    public function logoutAll(Request $request) {
        try {
            UserToken::where(['user_id' => $request->userid])->delete();
            return $this->apiResponse('success', '200', 'You has been Successfully Logout  from all active sessions');
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '400', config('constants.ERROR.TRY_AGAIN_ERROR'));
        }
    }
    
}
