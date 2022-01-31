<?php

namespace App\Providers;

use App\Models\{User, DelegateAccount, UserToken, UserActivePlan};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        $this->app['auth']->viaRequest('api', function ($request) {

            if ($request->header('Authorization')) {
                $key = explode(' ',$request->header('Authorization'));
                $user = UserToken::where('access_token', $key[1])->first();
                if(!empty($user)){
                    if( $request->is('v1/delegate-access/*') && $request->header('delegate-token')) {
                        $key_acc = jsdecode_userdata($request->header('delegate-token'));
                        $user_acc = DelegateAccount::where(['id' => $key_acc, 'delegate_user_id' => $user->user_id])->first();
                        if(!empty($user_acc)){
                            $request->request->add(['userid' => $user_acc->user_id]);
                            $request->request->add(['delegate_user_id' => $user->user_id]);
                            $request->request->add(['delegate_account_id' => $key_acc]);
                        }
                        else
                        $request->request->add(['userid' => $user->user_id]);
                    }
                    else{
                        $request->request->add(['userid' => $user->user_id]);
                    }
                    $request->request->add(['access_token' => $key[1]]);
                }
                return $user;
            }
        });
    }
}
