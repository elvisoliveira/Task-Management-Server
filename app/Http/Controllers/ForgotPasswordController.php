<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
// use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Validation\ValidationException;
use Firebase\JWT\JWT;
use App\Mail\ForgotPasswordMail;
use App\Jobs\SendMail;

class ForgotPasswordController extends Controller
{
    public function forgotPassword(Request $request)
    {
        if (!$request->has('token')) {
            return response()->json(['message'=>'invalid link'], 400);
        }
        $token = $request->input('token');
        try {
            $credentials = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
        } catch (\Firebase\JWT\SignatureInvalidException $ex1) {
            return response()->json(['message'=>'invalid link'], 400);
        } catch (\Firebase\JWT\ExpiredException $ex2) {
            return response()->json(['message' => 'Sorry the link has expired.'], 400);
        }
        if ($credentials->typ!=='forgot') {
            return response()->json(['message'=>'invalid link'], 400);
        }
        $user = User::find($credentials->usr);
        if (!$user) {
            return response()->json(['message'=>'User does not exist'], 404);
        }
        if ($user->deleted_at) {
            return response()->json(['message'=>'user has been deleted'], 404);
        }
        $rules = [
            'newpassword' => ['bail','required',
                'min:8',
                'max:255',
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:/[@$!%*#?&]/', // must contain a special character
                'confirmed',
            ]
        ];
        $customMessages = [
            'required' => 'Please fill attribute :attribute',
            'regex' => 'Password should have at least one lowercase letter, one uppercase letter, one digit and one special character',
        ];

        $this->validate($request, $rules, $customMessages);
        $newpassword = $request->input('newpassword');
        $user->password = Hash::make($newpassword);
        $user->save();
        return response()->json(['message'=>'Password changed successfully'], 201);
    }


    public function forgotPasswordRequest(Request $request)
    {
        $rules=[
            'email' => 'bail|required|email'
        ];
        $customMessages = [
            'required' => 'Please fill attribute :attribute'
        ];
        $this->validate($request, $rules, $customMessages);
        $email = $request->input('email');
        $user = User::where('email', $email)->first();
        if (!$user || $user->deleted_at) {
            return response()->json(['message'=>'Sorry, this user does not exist'], 404);
        }
        $token = $this->jwt($user);
        $link = 'http://localhost:3000/forgot_password_token/'.$token;

        $mail = new ForgotPasswordMail($link);
        dispatch(new SendMail($email, $mail));

        return response()->json(['message'=>'The reset password link has been sent to your email id'], 200);
    }

    protected function jwt(User $user)
    {
        $payload = [
            'iss' => "welcome",         // Issuer of the token
            'usr' => $user->id,         // Subject of the token
            'typ' => 'forgot',          // Type of the token
            'iat' => time(),            // Time when JWT was issued.
            'exp' => time() + env('EXPIRATION_TIME')     // Expiration time
        ];
        
        return JWT::encode($payload, env('JWT_SECRET'));
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
}
