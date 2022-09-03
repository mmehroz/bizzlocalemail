<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Jobs\SendEmailJob;
use Image;
use DB;
use Input;
use App\Item;
use Session;
use Response;
use Validator;

class emailController extends Controller
{
	public function sendemail(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailsentordraft_id'	=> 'required',
	      'emailsendto_email'	=> 'required',
	    ]);
     	if ($validate->fails()) {    
			return response()->json("Fields Required", 400);
		}
		$getuseremail = DB::table('elsemployees')
        ->select('elsemployees_emailaddress','elsemployees_emailname')
        ->where('elsemployees_empid','=',$request->user_id)
        ->where('elsemployees_status','=',2)
        ->first();
		if ($request->emailmaster_id){
		$isreply = "Yes";
		$emailmaster_id = $request->emailmaster_id;
		}else{
		$isreply = "No";
		$validate = Validator::make($request->all(), [ 
	      'emailmaster_subject'		=> 'required',
	    ]);
     	if ($validate->fails()) {    
			return response()->json("Fields Required", 400);
		}
		$addmaster = array(
		'emailmaster_subject' 			=> $request->emailmaster_subject,
		'emailmaster_date'	 			=> date('Y-m-d'),
		'emailmaster_isreceiveorsent'   => 0,
		'status_id'		 				=> 1,
		'created_by'	 				=> $request->user_id,
		'created_at'	 				=> date('Y-m-d h:i:s'),
		);
		DB::table('emailmaster')->insert($addmaster);
		$emailmaster_id = DB::getPdo()->lastInsertId();
		}
		$adddetail = array(
		'emailldetail_body' 			=> $request->emailldetail_body,
		'emailldetail_sendby' 			=> $getuseremail->elsemployees_emailaddress,
		'emailldetail_sendbyname'		=> $getuseremail->elsemployees_emailname,
		'emailldetail_markas' 			=> "Inbox",
		'emailsentordraft_id' 			=> $request->emailsentordraft_id,
		'emailmaster_id' 				=> $emailmaster_id,
		'emailldetail_isreceiveorsent'  => 0,
		'emailldetail_senddate'			=> date('Y-m-d'),
		'status_id'		 				=> 1,
		'created_by'	 				=> $request->user_id,
		'created_at'	 				=> date('Y-m-d h:i:s'),
		);
		$save = DB::table('emailldetail')->insert($adddetail);
		$emailldetail_id  = DB::getPdo()->lastInsertId();
		$sentto = $request->sentto;
		$emailsendto_email = explode(',', $request->emailsendto_email);
		$emailsendcc_email = explode(',', $request->emailsendcc_email);
		foreach($emailsendto_email as $emailsendto_emails){
		$addsentto[] = array(
		'emailsendto_email' 			=> $emailsendto_emails,
		'emailsendto_type'	 			=> "To",
		'emailldetail_id'	 			=> $emailldetail_id,
		'status_id'		 				=> 1,
		'created_by'	 				=> $request->user_id,
		'created_at'	 				=> date('Y-m-d h:i:s'),
		);
		}
		DB::table('emailsendto')->insert($addsentto);
		if (isset($request->emailsendcc_email)) {
		foreach($emailsendcc_email as $emailsendcc_emails){
		$addsentcc[] = array(
		'emailsendto_email' 			=> $emailsendcc_emails,
		'emailsendto_type'	 			=> "Cc",
		'emailldetail_id'	 			=> $emailldetail_id,
		'status_id'		 				=> 1,
		'created_by'	 				=> $request->user_id,
		'created_at'	 				=> date('Y-m-d h:i:s'),
		);
		}
		DB::table('emailsendto')->insert($addsentcc);
		}
		if (!empty($request->emailattachment)) {
    		$images = $request->emailattachment;
        		foreach($images as $ima){
            		  	$saveattachment[] = array(
						'emailattachment_name'		=> $ima,
						'emailattachment_orgname'	=> $ima,
						'emailattachment_type'		=> "",
						'emailldetail_id'	 		=> $emailldetail_id,
						'status_id' 				=> 1,
						'created_by'				=> $request->user_id,
						'created_at'				=> date('Y-m-d h:i:s'),
						);
				}
            	DB::table('emailattachment')->insert($saveattachment);
        }
        if ($request->emailsentordraft_id == 1) {
		$getsenttoemail = DB::table('emailsendto')
		->select('emailsendto_email')
		->where('emailsendto_type','=',"To")
		->where('emailldetail_id','=',$emailldetail_id)
		->where('status_id','=',1)
		->get();
		$setsenttoemail = array();
		foreach ($getsenttoemail as $getsenttoemails) {
			$setsenttoemail[] = $getsenttoemails->emailsendto_email;
		}
		$mergesenttoemail = implode(",", $setsenttoemail);
		$getsentccemail = DB::table('emailsendto')
		->select('emailsendto_email')
		->where('emailsendto_type','=',"Cc")
		->where('emailldetail_id','=',$emailldetail_id)
		->where('status_id','=',1)
		->get();
		$setsentccemail = array();
		foreach ($getsentccemail as $getsentccemails) {
			$setsentccemail[] = $getsentccemails->emailsendto_email;
		}
		$mergesentccemail = implode(",", $setsentccemail);
		$emailattachment = DB::table('emailattachment')
		->select('emailattachment_name')
		->where('emailldetail_id','=',$emailldetail_id)
		->where('status_id','=',1)
		->get();
		$setemailattachment = array();
		foreach ($emailattachment as $emailattachments) {
			$setemailattachment[] = "http://45.61.48.123/dropattachment/public/emailattachment/".$emailattachments->emailattachment_name;
		}
		$mergeemailattachment = implode(",", $setemailattachment);
		$details['toemail'] = $mergesenttoemail;
		$details['ccemail'] = $mergesentccemail;
		$details['subject'] = $request->emailmaster_subject;
		$details['fromname'] = $getuseremail->elsemployees_emailname;
		$details['fromemail'] = $getuseremail->elsemployees_emailaddress;
		$details['body'] = $request->emailldetail_body;
		$details['emailldetail_id'] = $emailldetail_id;
		$details['attachment'] = $mergeemailattachment;
		$details['emailmaster_id'] = $emailmaster_id;
		$details['isreply'] = $isreply;
		// dd($details);
		dispatch(new SendEmailJob($details));
		}
  		if($save){
			return response()->json(['message' => 'Email Sent Successfully'],200);
		}else{
			return response()->json("Oops! Something Went Wrong", 400);
		}
	}
	public function inboxemaillist(Request $request){
		$getspamuser = DB::table('emailtype')
		->select('emailtype_addfor')
		->where('emailtype_addfor','!=',"-")
		->where('created_by','=',$request->user_id)
		->where('status_id','=',1)
		->get();
		$sortspamuser = array();
		foreach ($getspamuser as $getspamusers) {
			$sortspamuser[] = $getspamusers->emailtype_addfor;
		}
		$getemail = DB::table('elsemployees')
		->select('elsemployees_emailaddress')
		->where('elsemployees_empid','=',$request->user_id)
		->where('elsemployees_status','=',2)
		->first();
		$getemailid = DB::table('emailsendto')
		->select('emailldetail_id')
		->where('emailsendto_email','=',$getemail->elsemployees_emailaddress)
		->where('status_id','=',1)
		->get()->toArray();
		$sortemailids = array();
		foreach ($getemailid as $getemailids) {
			$sortemailids[] = $getemailids->emailldetail_id;
		}
		$getspamemailid = DB::table('emailldetail')
		->select('emailldetail_id')
		->whereIn('emailldetail_sendby',$sortspamuser)
		->where('status_id','=',1)
		->get()->toArray();
		$sortspamemailids = array();
		foreach ($getspamemailid as $getspamemailids) {
			$sortspamemailids[] = $getspamemailids->emailldetail_id;
		}
		$getinboxlist = DB::table('emaillist')
		->select('*')
		->whereIn('emailldetail_id',$sortemailids)
		->whereNotIn('emailldetail_id',$sortspamemailids)
		->where('emailldetail_markas','=',$request->emailldetail_markas)
		->where('emailldetail_isreceiveorsent','=',1)
		->where('emailsentordraft_id','=',1)
		->where('status_id','=',1)
		->orderBy('emailmaster_id','DESC')
		->groupBy('emailmaster_id')
		->get();
		$getinboxlist = $this->paginate($getinboxlist);
		if($getinboxlist){
			return response()->json(['data' => $getinboxlist,'message' => 'Inbox Email List'],200);
		}else{
			$emptyarray = array();
			return response()->json(['data' => $emptyarray,'message' => 'No Email'],200);
		}
	}
	public function sentemaillist(Request $request){
		$getemail = DB::table('elsemployees')
		->select('elsemployees_emailaddress')
		->where('elsemployees_empid','=',$request->user_id)
		->where('elsemployees_status','=',2)
		->first();
		$getinboxlist = DB::table('sentemaillist')
		->select('*')
		->where('senderemail','=',$getemail->elsemployees_emailaddress)
		->where('emailmaster_isreceiveorsent','=',0)
		->where('emailsentordraft_id','=',1)
		->where('status_id','=',1)
		->orderBy('emailmaster_id','DESC')
		->groupBy('emailmaster_id')
		->get();
		$getinboxlist = $this->paginate($getinboxlist);
		if($getinboxlist){
			return response()->json(['data' => $getinboxlist,'message' => 'Sent Email List'],200);
		}else{
			$emptyarray = array();
			return response()->json(['data' => $emptyarray,'message' => 'No Email'],200);
		}
	}
	public function emaildetail(Request $request){
		$getemail = DB::table('emaillist')
		->select('*')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->where('detailedcreated_by','=',$request->user_id)
		->where('status_id','=',1)
		->orderBy('emailldetail_id','DESC')
		->get();
		$setemail = array();
		$index=0;
		foreach ($getemail as $getemails) {
		$read = DB::table('emailldetail')
		->where('emailldetail_id','=',$getemails->emailldetail_id)
		->where('emailldetail_readstatus','=',0)
		->update([
		'emailldetail_readstatus' 	=> 1,
		]);
		$getsentto = DB::table('emailsendto')
			->select('emailsendto_email')
			->where('emailldetail_id','=',$getemails->emailldetail_id)
			->where('emailsendto_type','=',"To")
			->where('status_id','=',1)
			->get();
		$sortsentto = array();
		foreach ($getsentto as $getsenttos) {
			$sortsentto[] = $getsenttos->emailsendto_email;
		}
		$getemails->to = implode(',', $sortsentto);
		$getsentcc = DB::table('emailsendto')
			->select('emailsendto_email')
			->where('emailldetail_id','=',$getemails->emailldetail_id)
			->where('emailsendto_type','=',"Cc")
			->where('status_id','=',1)
			->get();
		$sortsentcc = array();
		foreach ($getsentcc as $getsentccs) {
			$sortsentcc[] = $getsentccs->emailsendto_email;
		}
		$getemails->cc = implode(',', $sortsentcc);
		$getsentattachment = DB::table('emailattachment')
			->select('emailattachment_name')
			->where('emailldetail_id','=',$getemails->emailldetail_id)
			->where('status_id','=',1)
			->get();
		$sortsentattachment = array();
		foreach ($getsentattachment as $getsentattachments) {
			$sortsentattachment[] = $getsentattachments->emailattachment_name;
		}
		$getemails->attachment = implode(',', $sortsentattachment);
		$getsenderdetail = DB::table('emailldetail')
			->select('emailldetail_sendby','emailldetail_sendbyname')
			->where('emailldetail_id','=',$getemails->emailldetail_id)
			->where('status_id','=',1)
			->first();
		$getsenderdpicture = DB::table('elsemployees')
			->select('elsemployees_image')
			->where('elsemployees_emailaddress','=',$getsenderdetail->emailldetail_sendby)
			->first();
		if (isset($getsenderdpicture)) {
			$senderpicture = $getsenderdpicture->elsemployees_image;
		}else{
			$senderpicture = "no-image.png";
		}
		$getemails->senderemail = $getsenderdetail->emailldetail_sendby;
		$getemails->sendername = $getsenderdetail->emailldetail_sendbyname;
		$getemails->emailuser_picture = $senderpicture;
		$setemail[$index] = $getemails;
		$index++;
		}
		if($setemail){
			return response()->json(['data' => $setemail,'message' => 'Email Details'],200);
		}else{
			$emptyarray = array();
			return response()->json(['data' => $emptyarray,'message' => 'No Email'],200);
		}
	}
	public function spamemaillist(Request $request){
		$getspamuser = DB::table('emailtype')
		->select('emailtype_addfor')
		->where('created_by','=',$request->user_id)
		->where('emailtype_name','=',"Spam")
		->where('status_id','=',1)
		->get();
		$sortspamuser = array();
		foreach ($getspamuser as $getspamusers) {
			$sortspamuser[] = $getspamusers->emailtype_addfor;
		}
		$getemail = DB::table('elsemployees')
		->select('elsemployees_email')
		->where('elsemployees_empid','=',$request->user_id)
		->where('elsemployees_status','=',2)
		->first();
		$getemailid = DB::table('emailsendto')
		->select('emailldetail_id')
		->where('emailsendto_email','=',$getemail->elsemployees_email)
		->where('status_id','=',1)
		->get()->toArray();
		$sortemailids = array();
		foreach ($getemailid as $getemailids) {
			$sortemailids[] = $getemailids->emailldetail_id;
		}
		$getspamemailid = DB::table('emailldetail')
		->select('emailldetail_id')
		->whereIn('emailldetail_sendby',$sortspamuser)
		->whereIn('emailldetail_id',$sortemailids)
		->where('status_id','=',1)
		->get()->toArray();
		$sortspamemailids = array();
		foreach ($getspamemailid as $getspamemailids) {
			$sortspamemailids[] = $getspamemailids->emailldetail_id;
		}
		$getspamlist = DB::table('emaillist')
		->select('*')
		->whereIn('emailldetail_id',$sortspamemailids)
		->where('emailsentordraft_id','=',1)
		->where('status_id','=',1)
		->orderBy('emailmaster_id','DESC')
		->groupBy('emailmaster_id')
		->get();
		$getspamlist = $this->paginate($getspamlist);
		if($getspamlist){
			return response()->json(['data' => $getspamlist,'message' => 'Spam Email List'],200);
		}else{
			$emptyarray = array();
			return response()->json(['data' => $emptyarray,'message' => 'No Email'],200);
		}
	}
	public function draftemaillist(Request $request){
		$getdraftlist = DB::table('emaillist')
		->select('*')
		->where('created_by','=',$request->user_id)
		->where('emailsentordraft_id','=',2)
		->where('status_id','=',1)
		->orderBy('emailmaster_id','DESC')
		->groupBy('emailmaster_id')
		->get();
		$getdraftlist = $this->paginate($getdraftlist);
		if($getdraftlist){
			return response()->json(['data' => $getdraftlist,'message' => 'Draft Email List'],200);
		}else{
			$emptyarray = array();
			return response()->json(['data' => $emptyarray,'message' => 'No Email'],200);
		}
	}
	public function createlabel(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailtype_name'		=> 'required',
	      'emailtype_addfor'	=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		// $getuseremail = DB::table('emailtype')
		// ->select('emailtype_addfor')
		// ->where('emailtype_addfor','=',$request->emailtype_addfor)
		// ->where('created_by','=',$request->user_id)
		// ->where('status_id','=',1)
		// ->first();
		// if (isset($getuseremail)) {
		// 	return response()->json("Email Already Exist", 400);
		// }
		$add[] = array(
		'emailtype_name' 	=> $request->emailtype_name,
		'emailtype_addfor'	=> $request->emailtype_addfor,
		'status_id'		 	=> 1,
		'created_by'	 	=> $request->user_id,
		'created_at'	 	=> date('Y-m-d h:i:s'),
		);
		$save = DB::table('emailtype')->insert($add);
		if($save){
			return response()->json(['data' => $add,'message' => 'Successfully Created'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function labellist(Request $request){
		$getlist = DB::table('emailtype')
		->select('*')
		->where('created_by','=',$request->user_id)
		->where('emailtype_name','!=',"Spam")
		->where('status_id','=',1)
		->get();
		if($getlist){
			return response()->json(['data' => $getlist,'message' => 'Label List'],200);
		}else{
			$emptyarray = array();
			return response()->json(['data' => $emptyarray,'message' => 'Label Not Found'],200);
		}
	}
	public function labelemaillist(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailtype_addfor'		=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		$getemail = DB::table('elsemployees')
		->select('elsemployees_email')
		->where('elsemployees_empid','=',$request->user_id)
		->where('elsemployees_status','=',2)
		->first();
		$getemailid = DB::table('emailsendto')
		->select('emailldetail_id')
		->where('emailsendto_email','=',$getemail->elsemployees_email)
		->where('status_id','=',1)
		->get()->toArray();
		$sortemailids = array();
		foreach ($getemailid as $getemailids) {
			$sortemailids[] = $getemailids->emailldetail_id;
		}
		$getlabelemailid = DB::table('emailldetail')
		->select('emailldetail_id')
		->where('emailldetail_sendby','=',$request->emailtype_addfor)
		->whereIn('emailldetail_id',$sortemailids)
		->where('status_id','=',1)
		->get()->toArray();
		$sortlabelemailids = array();
		foreach ($getlabelemailid as $getlabelemailids) {
			$sortlabelemailids[] = $getlabelemailids->emailldetail_id;
		}
		$getlabelemaillist = DB::table('emaillist')
		->select('*')
		->whereIn('emailldetail_id',$sortlabelemailids)
		->where('emailsentordraft_id','=',1)
		->where('status_id','=',1)
		->orderBy('emailmaster_id','DESC')
		->groupBy('emailmaster_id')
		->get();
		if($getlabelemaillist){
			return response()->json(['data' => $getlabelemaillist,'message' => 'Label Email List'],200);
		}else{
			$emptyarray = array();
			return response()->json(['data' => $emptyarray,'message' => 'No Email'],200);
		}
	}
	public function movetolabel(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailldetail_id'		=> 'required',
	      'emailldetail_markas'	=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		$update = DB::table('emailldetail')
		->where('emailldetail_id','=',$request->emailldetail_id)
		->update([
		'emailldetail_markas' 	=> $request->emailldetail_markas,
		]);
		if($update){
			return response()->json(['message' => 'Email Move Successfully'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function readunread(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailldetail_id'			=> 'required',
	      'emailldetail_readstatus'	=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		$readstatus = DB::table('emailldetail')
		->where('emailldetail_id','=',$request->emailldetail_id)
		->update([
		'emailldetail_readstatus' 	=> $request->emailldetail_readstatus,
		]);
		if($readstatus){
			return response()->json(['message' => 'Email Mark Successfully'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function deleteemail(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailmaster_id'			=> 'required',
	      'emailldetail_id'			=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		$update = DB::table('emailldetail')
		->where('emailldetail_id','=',$request->emailldetail_id)
		->update([
		'status_id' 	=> 2,	
		'deleted_by' 	=> $request->user_id,
		]);
		$getemailcount = DB::table('emailldetail')
		->select('emailmaster_id')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->where('status_id','=',1)
		->count();
		if ($getemailcount == 0) {
		DB::table('emaillmaster')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->update([
		'status_id' 	=> 2,	
		'deleted_by' 	=> $request->user_id,
		]);
		}
		if($update){
			return response()->json(['message' => 'Delete Successfully'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}	
	public function restoreemail(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailmaster_id'			=> 'required',
	      'emailldetail_id'			=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		$update = DB::table('emailldetail')
		->where('emailldetail_id','=',$request->emailldetail_id)
		->update([
		'status_id' 	=> 1,
		'deleted_by' 	=> null,
		]);
		$gettotalemailcount = DB::table('emailldetail')
		->select('emailmaster_id')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->count();
		$getemailcount = DB::table('emailldetail')
		->select('emailmaster_id')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->where('status_id','!=',1)
		->count();
		$chekemailcount = $gettotalemailcount-$getemailcount;
		if ($chekemailcount == 0) {
		DB::table('emaillmaster')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->update([
		'status_id' 	=> 1,	
		'deleted_by' 	=> null,
		]);
		}
		if($update){
			return response()->json(['message' => 'Restore Successfully'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function deletetrashemail(Request $request){
		$validate = Validator::make($request->all(), [ 
		  'emailmaster_id'			=> 'required',
	      'emailldetail_id'			=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		$update = DB::table('emailldetail')
		->where('emailldetail_id','=',$request->emailldetail_id)
		->update([
		'status_id' 	=> 3,	
		]);
		$gettotalemailcount = DB::table('emailldetail')
		->select('emailmaster_id')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->count();
		$getemailcount = DB::table('emailldetail')
		->select('emailmaster_id')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->where('status_id','!=',1)
		->count();
		$chekemailcount = $gettotalemailcount-$getemailcount;
		if ($chekemailcount == 0) {
		DB::table('emaillmaster')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->update([
		'status_id' 	=> 3,	
		]);
		}
		if($update){
			return response()->json(['message' => 'Permanatly Deleted Successfully'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function deletemasteremail(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emaillmaster_id'			=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		$update = DB::table('emailmaster')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->update([ 
		'status_id' 	=> 2,	
		'deleted_by' 	=> $request->user_id,
		]);
		DB::table('emailldetail')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->update([ 
		'status_id' 	=> 2,	
		'deleted_by' 	=> $request->user_id,
		]);
		if($update){
			return response()->json(['message' => 'Delete Successfully'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function restoremasteremail(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailmaster_id'			=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		$update = DB::table('emailmaster')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->update([
		'status_id' 	=> 1,
		'deleted_by' 	=> null,
		]);
		DB::table('emailldetail')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->update([
		'status_id' 	=> 1,
		'deleted_by' 	=> null,
		]);
		if($update){
			return response()->json(['message' => 'Restore Successfully'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function deletetrashmasteremail(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailmaster_id'			=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		$update = DB::table('emailmaster')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->update([
		'status_id' 	=> 3,	
		]);
		DB::table('emailldetail')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->update([
		'status_id' 	=> 3,	
		]);
		if($update){
			return response()->json(['message' => 'Permanatly Deleted Successfully'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function trashemaillist(Request $request){
		$getmasterlist = DB::table('emaillist')
		->select('*')
		->where('status_id','=',2)
		->where('deleted_by','=',$request->user_id)
		->groupBy('emailmaster_id')
		->get()->toArray();
		$getdetaillistemailids = DB::table('emaillist')
		->select('emailmaster_id')
		->where('status_id','=',1)
		->where('detailstatus_id','=',2)
		->where('detaildeleted_by','=',$request->user_id)
		->get();
		// dd($getdetaillistemailids);
		$emailids = array();
		if (isset($getdetaillistemailids->emailmaster_id)) {
		foreach ($getdetaillistemailids as $getdetaillistemailidss) {
			$emailids[] = $getdetaillistemailidss->emailmaster_id;
		}
		$getdetaillist = DB::table('emaillist')
		->select('*')
		->whereIn('emailmaster_id',[$emailids])
		->get()->toArray();
		}
		if (!empty($getmasterlist) && !empty($getdetaillist)) {
		$getlist = array_merge($getmasterlist,$getdetaillist);
		// dd($getlist);
		}elseif (!empty($getmasterlist)){
		$getlist = $getmasterlist;
		}elseif (!empty($getdetaillist)) {
		$getlist = $getdetaillist;
		}else{
		$getlist = array();
		}
		$getlist = $this->paginate($getlist);
		if($getlist){
			return response()->json(['data' => $getlist,'message' => 'Trash Email List'],200);
		}else{
			$emptyarray = array();
			return response()->json(['data' => $emptyarray,'message' => 'No Email'],200);
		}
	}
	public function trashemaildetail(Request $request){
		$getemail = DB::table('emaillist')
		->select('*')
		->where('emailmaster_id','=',$request->emailmaster_id)
		->where('detailstatus_id','!=',1)
		->orderBy('emailldetail_id','DESC')
		->get();
		$setemail = array();
		$index=0;
		foreach ($getemail as $getemails) {
		$getsentto = DB::table('emailsendto')
			->select('emailsendto_email')
			->where('emailldetail_id','=',$getemails->emailldetail_id)
			->where('emailsendto_type','=',"To")
			->where('status_id','=',1)
			->get();
		$sortsentto = array();
		foreach ($getsentto as $getsenttos) {
			$sortsentto[] = $getsenttos->emailsendto_email;
		}
		$getemails->to = implode(',', $sortsentto);
		$getsentcc = DB::table('emailsendto')
			->select('emailsendto_email')
			->where('emailldetail_id','=',$getemails->emailldetail_id)
			->where('emailsendto_type','=',"Cc")
			->where('status_id','=',1)
			->get();
		$sortsentcc = array();
		foreach ($getsentcc as $getsentccs) {
			$sortsentcc[] = $getsentccs->emailsendto_email;
		}
		$getemails->cc = implode(',', $sortsentcc);
		$getsentattachment = DB::table('emailattachment')
			->select('emailattachment_name')
			->where('emailldetail_id','=',$getemails->emailldetail_id)
			->where('status_id','=',1)
			->get();
		$sortsentattachment = array();
		foreach ($getsentattachment as $getsentattachments) {
			$sortsentattachment[] = $getsentattachments->emailattachment_name;
		}
		$getemails->attachment = implode(',', $sortsentattachment);
		$setemail[$index] = $getemails;
		$index++;
		}
		if($setemail){
			return response()->json(['data' => $setemail,'message' => 'Email Details'],200);
		}else{
			$emptyarray = array();
			return response()->json(['data' => $emptyarray,'message' => 'No Email'],200);
		}
	}
	public function savetemplate(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailtemplate_body'			=> 'required',
	    ]);
	    if ($validate->fails()) {    
				return response()->json("Fields Required", 400);
		}
		$add[] = array(
		'emailtemplate_body' 			=> $request->emailtemplate_body,
		'status_id'		 				=> 1,
		'created_by'	 				=> $request->user_id,
		'created_at'	 				=> date('Y-m-d h:i:s'),
		);
		$save = DB::table('emailtemplate')->insert($add);
		if($save){
			return response()->json(['message' => 'Email Template Saved Successfully'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function deletettemplate(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'emailtemplate_id'			=> 'required',
	    ]);
	    if ($validate->fails()) {    
			return response()->json("Fields Required", 400);
		}
		$update = DB::table('emailtemplate')
		->where('emailtemplate_id','=',$request->emailtemplate_id)
		->update([
		'status_id' 	=> 2,	
		]);
		if($update){
			return response()->json(['message' => 'Template Deleted Successfully'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function templatelist(Request $request){
		$getlist = DB::table('emailtemplate')
		->select('emailtemplate_id','emailtemplate_body')
		->where('status_id','=',1)
		->where('created_by','=',$request->user_id)
		->get();
		if($getlist){
			return response()->json(['data' => $getlist, 'message' => 'Email Template List'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function searchemailbysubject(Request $request)
    {
    	$validate = Validator::make($request->all(), [ 
	      'emailmaster_subject'			=> 'required',
	    ]);
	    if ($validate->fails()) {    
			return response()->json("Fields Required", 400);
		}
       $getemail = DB::table('elsemployees')
		->select('elsemployees_emailaddress')
		->where('elsemployees_empid','=',$request->user_id)
		->where('elsemployees_status','=',2)
		->first();
       $getemailid = DB::table('emailsendto')
		->select('emailldetail_id')
		->where('emailsendto_email','=',$getemail->elsemployees_emailaddress)
		->where('status_id','=',1)
		->get()->toArray();
		$sortemailids = array();
		foreach ($getemailid as $getemailids) {
			$sortemailids[] = $getemailids->emailldetail_id;
		}
		$getemaillist = DB::table('emaillist')
		->select('*')
		->where('emailmaster_subject', 'LIKE', "%{$request->emailmaster_subject}%")
		->whereIn('emailldetail_id',$sortemailids)
		->where('emailsentordraft_id','=',1)
		->where('status_id','=',1)
		->orderBy('emailmaster_id','DESC')
		->groupBy('emailmaster_id')
		->get();
		if($getemaillist){
			return response()->json(['data' => $getemaillist, 'message' => 'Email List'],200);
		}else{
			$emptyarray = array();
			return response()->json(['data' => $emptyarray,'message' => 'No Email Found'],200);
		}
    }
    public function userdrive(Request $request){
		$getuploads = DB::table('emailattachment')
		->select('emailattachment_name')
		->where('status_id','=',1)
		->where('created_by','=',$request->user_id)
		->get();
		$getuploads = $this->paginate($getuploads);
		if($getuploads){
			return response()->json(['data' => $getuploads, 'message' => 'User Drive'],200);
		}else{
			return response()->json(['message' => 'Oops! Something went wrong'],200);
		}
	}
	public function searchuser(Request $request){
		$validate = Validator::make($request->all(), [ 
	      'search'	=> 'required',
	    ]);
	    if ($validate->fails()) {    
			return response()->json("Fields Required", 400);
		}
		$getuserlist = DB::table('elsemployees')
		->select('elsemployees_emailaddress')
		->orWhere('elsemployees_name', 'LIKE', "%".$request->search."%")
        ->orWhere('elsemployees_email', 'LIKE', "%".$request->search."%")
		->where('elsemployees_status','=',2)
		->get();
		if($getuserlist){
			return response()->json(['userlist' => $getuserlist],200);
		}else{
			return response()->json("Oops! Something Went Wrong", 400);
		}
	}
	public function clearcache(){
		Artisan::call("optimize");
		return response()->json("Cache Clear", 200);
	}
	public function paginate($items, $perPage = 30, $page = null, $options = []){
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return  new  LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}