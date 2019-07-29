<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Validation\ValidationException;
use Auth;
use Illuminate\Validation\Rule; // from documentation
use DateTime;

use App\Mail\CreateUserMail;
use App\Jobs\SendMail;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin', ['only'=>[
            'delete','role_change','create', 'view'
        ]]);
        // u can use 'except' if there are many functions.
    }

    public function list(Request $request)
    {
        $current_user = Auth::user();
        $admin = ['users.id','users.name','users.role','users.email','users.created_at','users.updated_at','u.name as created_by'];
        $normal = ['users.id','users.name','users.role'];
        $filter_name = $request->input('filter_name');
        $filter_id = $request->input('filter_id');
        $filter_role = $request->input('filter_role');
        $filter_email = $request->input('filter_email');
        $filter_created_by = $request->input('filter_created_by');
        $sort = 'id';
        $order = 'asc';
        // ask how to check roles before sorting
        $query = User::Join('users as u', 'users.created_by', '=', 'u.id')
                    ->where('users.deleted_at', null)
                    ->where('users.id', '!=', $current_user->id)
                    ->where('users.name', 'like', '%'.$filter_name.'%')
                    ->where('users.id', 'like', '%'.$filter_id.'%')
                    ->where('users.role', 'like', '%'.$filter_role.'%');


        if ($current_user->role === 'normal') {
            // see https://laravel.com/docs/5.8/queries
            $query = $query
                        ->select($normal);
            // below checks for preventing any kind of tempering by user on front end.
            if ($request->has('sort')) {
                $rules = [
                    'sort' => ['bail','required',
                    'max:255',
                        Rule::in($normal)
                    ],
                    'order' => ['bail',
                        'required',
                        'max:255',
                        Rule::in(['asc','desc'])
                    ]
                ];
                $this->validate($request, $rules);
                $sort = $request->input('sort');
                $order = $request->input('order');
            }
        } elseif ($current_user->role === 'admin') {
            $query = $query
                        ->select($admin)
                        ->where('users.email', 'like', '%'.$filter_email.'%')
                        ->where('u.name', 'like', '%'.$filter_created_by.'%');
            if ($request->has('sort')) {
                $rules = [
                    'sort' => ['bail','required',
                    'max:255',
                        Rule::in($admin)
                    ],
                    'order' => ['bail',
                        'required',
                        'max:255',
                        Rule::in(['asc','desc'])
                    ]
                ];
                $this->validate($request, $rules);
                $sort = $request->input('sort');
                $order = $request->input('order');
            }
            // $query = $query
            //             ->Join('users as u','users.created_by','=','u.id')
            //             ->where('u.name', 'like', '%'.$filter_created_by.'%');
        }

        $query = $this->getQueryData($query, $sort, $order);

        
        
        // ->get();
        return response()->json(['table' => $query], 200);
    }

    private function getQueryData($query, $sort, $order)
    {
        return $query
                    ->orderBy($sort, $order)
                    ->paginate(5);
    }

    public function delete(Request $request)
    {
        $current_user = Auth::user();
        $rules = [
            'id' => 'bail|required|integer',
        ];
        // $customMessages = [
        //     'required': 'attribute required :attribute',
        // ]
        $this->validate($request, $rules);

        if ($current_user->id === $request->input('id')) {
            return response()->json(['message'=> 'cannot delete yourself'], 403);
        }
        $duser = User::where('id', $request->input('id'))->first();
        if (!$duser) {
            return response()->json(['message'=> 'user not found'], 404);
        }
        if ($duser->role === 'admin') {
            return response()->json(['message'=> 'cannot delete the user'], 403);
        }
        if ($duser->deleted_at) {
            return response()->json(['message'=> 'user not found'], 404);
        }
        $duser->deleted_at = new DateTime();
        ;
        $duser->deleted_by = $current_user->id;
        $duser->save();
        return response()->json(['message'=> 'user deleted'], 201);
    }

    public function roleChange(Request $request)
    {
        $current_user = Auth::user();
        $rules = [
            'id' => 'bail|required|integer',
            'role' => [
                'bail',
                'required',
                Rule::in(['admin'])
            ]
        ];

        $this->validate($request, $rules);

        $changed_user = User::where('id', $request->input('id'))->first();
        if (!$changed_user || $changed_user->deleted_at) {
            return response()->json(['message'=> 'user does not exist'], 404);
        }
        if ($changed_user->role === 'admin') {
            return response()->json(['message'=> 'cannot change the permission of other admins'], 403);
        }
        $changed_user->role = 'admin';
        $changed_user->updated_by = $current_user->id;
        $changed_user->save();
        return response()->json(['message'=> 'role changed'], 201);
    }

    public function create(Request $request)
    {
        $current_user = Auth::user();
        $rules = [
            'name' => 'bail|required|max:255',
            'email' => 'bail|required|email|unique:users|max:255'
        ];
        $customMessages = ['required'=>'Please fill attribute :attribute'];
        $this->validate($request, $rules, $customMessages);
        $user = new User;
        $user->email = $request->input('email');
        $user->name = $request->input('name');
        $passwd = str_random(10);
        $user->password = Hash::make($passwd);
        $user->created_by = $current_user->id;
        $user->save();

        $mail = new CreateUserMail($passwd); 
        dispatch(new SendMail($user->email, $mail));
        return response()->json(['message'=>'Account Creation Successful. Password has been sent to the email id of user','password'=>$passwd], 201);
    }

}
