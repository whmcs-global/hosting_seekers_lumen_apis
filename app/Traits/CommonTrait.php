<?php
 
namespace App\Traits;
use Tymon\JWTAuth\Facades\JWTAuth;
Use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;
use hisorange\BrowserDetect\Parser;

use Illuminate\Support\Facades\Crypt;
trait CommonTrait {
    public function updateLastLogin($id = NULL) {
        $browser = new Parser(null, null, [
            'cache' => [
                'interval' => 86400 // This will overide the default configuration.
            ]
        ]);
        if($id){
            $lastLoginData = serialize([
                'ip_address' => request()->ip(),
                'Browser Name' => $browser->browserName(),
                'Operating System' => $browser->platformName(),
                'Agent' => $browser->userAgent(),
            ]);
            $services = User::where('id', $id)
            ->update(['last_login' =>  date('Y-m-d H:i:s'), 'last_login_data' => $lastLoginData]);
        }
    }
}