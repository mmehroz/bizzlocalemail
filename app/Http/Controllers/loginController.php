<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;
use Image;
use DB;
use Input;
use App\Item;
use Session;
use Response;
use Validator;

class loginController extends Controller
{
	public function login(Request $request){
	    $validate = Validator::make($request->all(), [ 
		      'email' 		=> 'required',
		      'password'	=> 'required',
		    ]);
	     	if ($validate->fails()) {    
				return response()->json("Enter Credentials To Signin", 400);
			}
		    $getprofileinfo = DB::table('elsemployees')
			->select('elsemployees_empid as user_id','elsemployees_name as emailuser_name','elsemployees_emailaddress as emailuser_email','elsemployees_image as emailuser_picture','elsemployees_coverimage as emailuser_themepicture','elsemployees_allowoutsideemail','elsemployees_roleid as role_id','elsemployees_emailhost as emailuser_emailhost')
			->where('elsemployees_email','=',$request->email)
			->where('elsemployees_password','=',$request->password)
			->where('elsemployees_status','=',2)
			->first();
			if ($getprofileinfo) {
				return response()->json(['data' => $getprofileinfo, 'message' => 'Login Successfully'],200);
			}else{
				return response()->json('Invalid Email Or Password', 400);
			}
	}
	public function logout(Request $request){
		return response()->json(['message' => 'Logout Successfully'],200);
	}
}