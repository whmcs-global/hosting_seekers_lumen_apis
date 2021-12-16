<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
Use Illuminate\Support\Str;
use  App\Models\{User, User_detail, UserToken, PasswordReset};
use Illuminate\Support\Facades\{Validator, Hash, Auth, mail, Password};
use Symfony\Component\HttpFoundation\Response;
use App\Traits\{AutoResponderTrait, SendResponseTrait};
use Carbon\Carbon;
use DB;
use hisorange\BrowserDetect\Parser;

class AuthController extends Controller
{
    use SendResponseTrait, AutoResponderTrait;
    /*
    Method Name:    login
    Developer:      Shine Dezign
    Created Date:   2021-11-02 (yyyy-mm-dd)
    Purpose:        To login into server on the basis of email and password
    Params:         [email, password]
    */
    public function login(Request $request)
    {
        //validate incoming request 
        //Validation check
        $messages = [
			'email.required' => 'We need to know your email address',
			'email.email' => 'Provide a an valid email address',
			'password.required' => 'You can not left password empty.',
			'password.string' => 'password field must be a string.'
		];
        $rules = [
            'email' => 'required|email',
            'password' => 'required|string',
        ];
		$validator = Validator::make($request->all(), $rules, $messages);
		if ($validator->fails()) { 
            return $this->apiResponse('error', '422', $validator->errors()->all());
        }

        try {
            $email = $request->email;
            if('gmail.com' == explode("@",$email)['1']){
                $preEmail = str_replace('.', '', explode("@",$email)['0']);
                $email = $preEmail.'@gmail.com';
            }
            $user = User::where('email', $email)->first();
            if(!Hash::check($request->input('password'), $user->password))
                return $this->apiResponse('error', '404', 'No records found');
            if($user->status != '1' || $user->verified != '1') 
                return $this->apiResponse('error', '404', config('constants.ERROR.ACCOUNT_ISSUE'));

            $access_token = $this->createToken();
            
            $browse_detail = $this->getUserBrowseDetail();
            $user_id = $user->id;
            $user_email = $user->email;

            $browser = new Parser(null, null, [
                'cache' => [
                    'interval' => 86400 // This will overide the default configuration.
                ]
            ]);
    
            $insert = new UserToken;
            $insert->user_id = $user_id;
            $insert->access_token = $access_token;
            $insert->device_id = request()->ip() ? :'postman';
            $insert->device_name = $browser->platformFamily() ? :'postman';
            $insert->status = 1;
            $insert->save();
            $user_details = User_detail::where('user_id',$user_id)->first();

            $user_data = array(
                'user_id' => jsencode_userdata($user_id),
                'email'=> $user_email,
                'mobile' => $user_details->mobile,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'address' => $user_details->address,
                'access_token' => $access_token
            );
            return $this->apiResponse('success', '200', 'Data fetched', $user_data);
        }
        catch(\Exception $e){
            DB::rollback();
            return $this->apiResponse('error', '404', 'Not Found!');
        }
    }
    /* End Method login */

    // register
    public function register(Request $request)
    {
        $rules = [
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required_with:password_confirmation|string|confirmed',
            'mobile' => 'required|numeric|min:10',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'address' => 'required|string',
            'social_id' => 'string'
        ];

        $messages = [
            'password.required' => 'Password is required',
            'password.confirmed' => 'Confirmed Password not matched with password'
        ];

		$validator = Validator::make($request->all(), $rules, $messages);
		if ($validator->fails()) { 
            return $this->apiResponse('error', '422', $validator->errors()->all());
        }

        DB::beginTransaction();
        try
        {
            $social_id = '';
            if($request->has('social_id')){
                $social_id = $request->social_id;
            }
            
            $data = array(
                'email' => $request->email,
                'password' => app('hash')->make($request->password),
                'mobile' => $request->mobile,
                'login_by' => 'website',
                'social_id' => $social_id,
                'status' => 1
            );
            
            $record = User::create($data);

            $browser = new Parser(null, null, [
                'cache' => [
                    'interval' => 86400 // This will overide the default configuration.
                ]
            ]);
           
            if ($record)
            {
                $rowData = serialize([
                    'created_date' => time(),
                    'ip_address' => request()->ip() ? :'postman',
                    'Browser Name' => $browser->browserName() ? :'postman',
                    'Operating System' => $browser->platformName() ? :'postman',
                    'last_login' => '',
                    'total_login_count' => 0
                ]);

                $user_data = array(
                    'user_id' => $record->id,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'address' => $request->address,
                    'row_data' => $rowData,
                );
                
                User_detail::create($user_data);
                
                DB::commit();
                return $this->apiResponse('success', '200', 'User Register successufully');
            }
            else
            {
                DB::rollback();
                return $this->apiResponse('unauthorize', '500', 'unauthorize');
            }
        }
        catch(\Exception $e)
        {
            DB::rollback();
            return $this->apiResponse('error', '404', $e->getMessage());
        }
    }
    // end of register


    // Start of forgot password
    public function password_reset_link(Request $request){
        $this->validate($request, [
        'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();
        $template = $this->get_template_by_name('FORGOT_PASSWORD');
        
        if (!$user)
            return $this->apiResponse('error', '404', 'User not found');

        $passwordReset = PasswordReset::updateOrCreate(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Str::random(12)
            ]
        );
        
        $logtoken = Str::random(12);
        $link = 'http://localhost:4000/api/v1/tokencheck/'.$passwordReset->token;
        $string_to_replace = array('{{$name}}','{{$token}}','{{$logToken}}');
        $string_replace_with = array('Admin',$link,$logtoken);
        $newval = str_replace($string_to_replace, $string_replace_with, $template->template);
        
        $logId = $this->email_log_create($user->email, $template->id, 'FORGOT_PASSWORD', $logtoken);
        
        $result = $this->send_mail($user->email, $template->subject, $newval);

        if($result)
        {
            $this->email_log_update($logId);
            return $this->apiResponse('success', '200', 'mail sent successfully');
        }
        else
        {
            return $this->apiResponse('error', '404', 'error');
        }
    }

    public function password_reset_token_check($token)
    {
        $passwordReset = PasswordReset::where('token', $token)->first();
        if(!$passwordReset)
            return $this->apiResponse('error', '404', 'Invalid forgot password token');
        
        if (Carbon::parse($passwordReset->updated_at)->addMinutes(240)->isPast()) {
            $passwordReset->delete();
            return $this->apiResponse('error', '404', 'Token expired');
        }
        $dataArray = ['refinedData' => $passwordReset];
        return $this->apiResponse('success', '200', 'Data fetched', $dataArray);
    }

    //end of forgot password

    public function update_new_password(Request $request){
        
        $rules = [
            'email' => 'required|email',
            'password' => 'required_with:password_confirmation|string|confirmed',
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
            $data = array(
                'password' => app('hash')->make($request->password),
                'modified' => date('Y-m-d H:i:s')
            );
            $record = User::where('email', $request->email)->update($data);
            PasswordReset::where('email', $request->email)->delete();
        
            return $this->apiResponse('success', '200', 'Password Change successfully');
        
        } catch ( \Exception $e ) {
            return $this->apiResponse('error', '404', $e->getMessage());
        }
        
        }


}