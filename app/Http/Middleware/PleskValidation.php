<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Validator;
use App\Models\{UserToken,UserServer};

class PleskValidation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $messages = [
            'cpanel_server.required' => 'We need to know cpanel_server'
        ];
        $rules = [
            'cpanel_server' => 'required|string'
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        $serverPackage = UserServer::where(['user_id' => $request->userid, 'id' => jsdecode_userdata($request->cpanel_server)])->first();
        if(!$serverPackage){
            return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Connection error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
        }
        $response = $next($request);

        return $response;
    }
}
