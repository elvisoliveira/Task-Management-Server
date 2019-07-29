<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Validation\ValidationException;

use App\Mail\RegisterMail;
use App\Jobs\SendMail;

class RegisterController extends Controller
{
    public function __construct()
    {
        //
    }

    public function signup(Request $request)
    {
        $rules = [
            'name' => 'bail|required|max:255',
            'email' => 'bail|required|email|unique:users|max:255',
            'password' => ['bail',
                'required',
                'confirmed',
                'min:8',
                'max:255',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ]

        ];
        $customMessages = [
             'required' => 'Please fill attribute :attribute',
             'password.regex' => 'Password should have at least one lowercase letter, one uppercase letter, one digit and one special character'
        ];

        $this->validate($request, $rules, $customMessages);
        $user = new User;
        $user->email = $request->input('email');
        $user->name = $request->input('name');
        $user->password = Hash::make($request->input('password'));
        $user->save();
        $user->created_by = $user->id;
        $user->save();

        $mail = new RegisterMail(); 
        dispatch(new SendMail($user->email, $mail)); 
        return response()->json(['message'=>'Registration Successful'], 201);
    }
}
