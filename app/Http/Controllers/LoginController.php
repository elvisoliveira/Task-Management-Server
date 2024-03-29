<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use App\User;
use Illuminate\Validation\ValidationException;
use Firebase\JWT\JWT;

class LoginController extends Controller
{
    public function __construct()
    {
    }

    protected function jwt(User $user)
    {
        $payload = [
            'iss' => "welcome",
            'usr' => $user->id,
            'typ' => 'login',
            'iat' => time(),
            'exp' => time() + env('EXPIRATION_TIME')
        ];
        
        return JWT::encode($payload, env('JWT_SECRET'));
    }

    public function login(Request $request)
    {
        $rules = [
            'email' => 'bail|required|email|max:255',
            'password' => ['bail',
                'required',
                'max:255',
            ]
        ];

        $customMessages = [
            'required' => 'Please fill attribute :attribute',
            'regex' => 'Password should have at least one lowercase letter, one uppercase letter, one digit and one special character',
        ];

        $this->validate($request, $rules, $customMessages);
        $email = $request->input('email');

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['message'=>'The user does not exist.'], 401);
        }
        if ($user->deleted_at) {
            return response()->json(['message'=>'User has been deleted'], 401);
        }
        if (!Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message'=>'Email or Password did not match.'], 401);
        }
        $token = $this->jwt($user);
        $cookie1 = new Cookie('token', $token, strtotime('now + 60 minutes'), '/', '', false, false);
        $cookie2 = new Cookie('user_id', $user->id, strtotime('now + 60 minutes'), '/', '', false, false);
        $cookie3 = new Cookie('role', $user->role, strtotime('now + 60 minutes'), '/', '', false, false);
        $cookie4 = new Cookie('loggedIn', 'true', strtotime('now + 60 minutes'), '/', '', false, false);
        return response()->json(['message' => 'Successful Login!'], 200)->cookie($cookie1)->cookie($cookie2)->cookie($cookie3)->cookie($cookie4);
    }
    public function logout(Request $request)
    {
        $cookie1 = new Cookie('token', '', strtotime('now + 60 minutes'), '/', '', false, false);
        $cookie2 = new Cookie('user_id', '', strtotime('now + 60 minutes'), '/', '', false, false);
        $cookie3 = new Cookie('role', '', strtotime('now + 60 minutes'), '/', '', false, false);
        $cookie4 = new Cookie('loggedIn', 'false', strtotime('now + 60 minutes'), '/', '', false, false);
        return response()->json(['message' => 'Successful Logout!'], 200)->cookie($cookie1)->cookie($cookie2)->cookie($cookie3)->cookie($cookie4);
    }
    public function test()
    {
        $user = new User();
        $user->name = "admin";
        $user->email = "admin@admin.com";
        $user->password = Hash::make("admin");
        $user->role = 'admin';
        $user->save();
        $user->created_by = $user->id;
        $user->save();
        return "success";
    }
}
