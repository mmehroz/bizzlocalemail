<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Client;
use DB;
use Artisan;
class IncomingMail extends Controller
{
    public function receiveemail(Request $request)
    {
         $getemailcredentials = DB::table('elsemployees')
        ->select('elsemployees_emailaddress','elsemployees_emailpassword','elsemployees_emailhost')
        ->where('elsemployees_empid','=',$request->user_id)
        ->first();
        if (empty($getemailcredentials)) {
            return response()->json(['message' => 'Email Credentials Required'],400);
        }
        $_ENV['IMAP_HOST'] = $getemailcredentials->elsemployees_emailhost;
        $_ENV['IMAP_USERNAME'] = $getemailcredentials->elsemployees_emailaddress;
        $_ENV['IMAP_PASSWORD'] = $getemailcredentials->elsemployees_emailpassword;
        Artisan::call("config:clear");
        Artisan::call("config:cache");
        Artisan::call("optimize");
        // dd(env('IMAP_USERNAME'));
        $oclient = \Webklex\IMAP\Facades\Client::account('default');
        $oClient = new \Client([
        'host'          => $getemailcredentials->elsemployees_emailhost,
        'port'          => 993,
        'encryption'    => 'ssl',
        'validate_cert' => true,
        'username'      => $getemailcredentials->elsemployees_emailaddress,
        'password'      => $getemailcredentials->elsemployees_emailpassword,
        'protocol'      => 'imap'
        ]);
        $oclient->connect();
        $aFolder = $oclient->getFolders();
        $sortemaildata = array();
        $sortemailmasterdata = array();
        $index=0;
        foreach($aFolder as $oFolder){
            // $aMessage = $oFolder->query()->from('avidhaus.huzaifa@gmail.com')->get();
            $aMessage = $oFolder->search()->unseen()->get();
            if (!empty($aMessage)) {
            foreach($aMessage as $oMessage){
                $getsub = sprintf($oMessage->getSubject());
                $getfrom = sprintf($oMessage->getFrom());
                $getto = sprintf($oMessage->getTo());
                $getcc = sprintf($oMessage->getCc());
                $getemailid = sprintf($oMessage->getMessageId());
                $getemailuid = sprintf($oMessage->getUid());
                $oMessage->getAttachments()->each(function ($oAttachment) use ($oMessage) {
                $attachment_token = mt_rand(100000, 999999);
                    $getAttachmentName = $attachment_token . $oAttachment->name;
                    $fp = fopen(public_path('emailattachments/') . $getAttachmentName,"wb");
                    file_put_contents(public_path('emailattachments/'. $getAttachmentName), $oAttachment->content);
                    fclose($fp);
                    session()->put([
                        'attachmentname'.$attachment_token => $getAttachmentName,
                    ]);
                });
                // echo 'Attachments: '.$oMessage->getAttachments()->count().'<br />';
                // echo $oMessage->getHTMLBody(true);
                $getnameandemail = explode("<", $getfrom);
                if (isset($getnameandemail[1])) {
                    $getformatedemail = explode(">", $getnameandemail[1]);
                    $getformatedname = trim($getnameandemail[0]);
                }else{
                    $getformatedemail = $getfrom;
                    $getformatedname =  $getfrom;
                }
                $gettonameandemail = explode(",", $getto);
                if (isset($gettonameandemail)) {
                    foreach ($gettonameandemail as $gettonameandemails) {
                    $gettofurthernameandemail = explode("<", $gettonameandemails);
                    if (isset($gettofurthernameandemail[1])) {
                        $getformatedtoemails = explode(">", $gettofurthernameandemail[1]);
                        $getformatedtoemail[] = $getformatedtoemails[0];
                    }else{
                        $getformatedtoemail[] = $gettonameandemails;
                    }
                }}else{
                    $gettofurthernameandemail = explode("<", $getto);
                    if (isset($gettofurthernameandemail[1])) {
                        $getformatedtoemail = explode(">", $gettofurthernameandemail[1]);
                    }else{
                        $getformatedtoemail = $getto;
                    }
                }
                $getccnameandemail = explode(",", $getcc);
                if (isset($getccnameandemail)) {
                    foreach ($getccnameandemail as $getccnameandemails) {
                    $getccfurthernameandemail = explode("<", $getccnameandemails);
                    if (isset($getccfurthernameandemail[1])) {
                        $getformatedccemails = explode(">", $getccfurthernameandemail[1]);
                        $getformatedccemail[] = $getformatedccemails[0];
                    }else{
                        $getformatedccemail[] = $getccnameandemails;
                    }
                }}else{
                    $getccfurthernameandemail = explode("<", $getcc);
                    if (isset($getccfurthernameandemail[1])) {
                        $getformatedccemail = explode(">", $getccfurthernameandemail[1]);
                    }else{
                        $getformatedccemail = $getcc;
                    }
                }
                $allto = implode(",",  $getformatedtoemail);
                $allcc = implode(",",  $getformatedccemail);
                $allattachmentname = implode(",",  session()->all());
                $stripped_subject = strip_tags($getsub);
                $formatted_subject = preg_replace('/\s+|body {.*}/', ' ', $stripped_subject);
                $fullyformated_subject = trim($formatted_subject);
                // $stripped_body = strip_tags($oMessage->getTextBody());
                // $formatted_body = preg_replace('/\s+|body {.*}/', ' ', $stripped_body);
                // $fullyformated_body = trim($formatted_body);
                $fullyformated_body = $oMessage->getHTMLBody();
                $sortemaildata[$index]['receiveemail_subbject'] = $fullyformated_subject;
                $sortemaildata[$index]['emailmaster_id'] = $getemailid;
                $sortemaildata[$index]['receiveemail_uid'] = $getemailuid;
                $sortemaildata[$index]['receiveemail_fromemail'] = $getformatedemail[0];
                $sortemaildata[$index]['receiveemail_fromname'] = $getformatedname;
                $sortemaildata[$index]['receiveemail_body'] = $fullyformated_body;
                $sortemaildata[$index]['receiveemail_attachment'] = $allattachmentname;
                $sortemaildata[$index]['receiveemail_to'] = $allto;
                $sortemaildata[$index]['receiveemail_cc'] = $allcc;
                session()->flush();
                $index++;
                $oMessage->setFlag(['Seen']);
            }
            }
        }
        if (!empty($sortemaildata)) {
        foreach($sortemaildata as $sortemaildatas){
            $saveemail[] = array(
            'emailmaster_subject'           => $sortemaildatas['receiveemail_subbject'],
            'emailmaster_date'              => date('Y-m-d'),
            'emailmaster_token'             => $sortemaildatas['emailmaster_id'],
            'emailmaster_isreceiveorsent'   => 1,
            'status_id'                     => 1,
            'created_by'                    => $request->user_id,
            'created_at'                    => date('Y-m-d h:i:s'),
            );
        }
        $getthread = DB::table('emailmaster')
        ->select('emailmaster_id')
        ->where('emailmaster_token','=',$sortemaildatas['emailmaster_id'])
        ->where('status_id','=',1)
        ->first();
        if(isset($getthread)){
        $emailmaster_id = $getthread->emailmaster_id;
        }else{
        
        DB::table('emailmaster')->insert($saveemail);
        $emailmaster_id = DB::getPdo()->lastInsertId();
        }
        foreach($sortemaildata as $sortemaildatas){
            $saveemaildetail[] = array(
            'emailldetail_sendby'           => $sortemaildatas['receiveemail_fromemail'],
            'emailldetail_sendbyname'       => $sortemaildatas['receiveemail_fromname'],
            'emailldetail_uid'              => $sortemaildatas['receiveemail_uid'],
            'emailldetail_body'             => $sortemaildatas['receiveemail_body'],
            'emailldetail_isreceiveorsent'  => 1,
            'emailldetail_markas'           => "Inbox",
            'emailsentordraft_id'           => 1,
            'emailmaster_id'                => $emailmaster_id,
            'emailldetail_senddate'         => date('Y-m-d'),
            'status_id'                     => 1,
            'created_by'                    => $request->user_id,
            'created_at'                    => date('Y-m-d h:i:s'),
            );
        }
        DB::table('emailldetail')->insert($saveemaildetail);
        $emailldetail_id  = DB::getPdo()->lastInsertId();
        foreach($sortemaildata as $sortemaildatas){
             $saveattachment[] = array(
            'emailattachment_name'      => $sortemaildatas['receiveemail_attachment'],
            'emailattachment_orgname'   => $sortemaildatas['receiveemail_attachment'],
            'emailattachment_type'      => 0,
            'emailldetail_id'           => $emailldetail_id,
            'status_id'                 => 1,
            'created_by'                => $request->user_id,
            'created_at'                => date('Y-m-d h:i:s'),
            );
        }
        if (!empty($saveattachment)) {
        DB::table('emailattachment')->insert($saveattachment);
        }
        foreach($sortemaildata as $sortemaildatas){
            $to = explode(',', $sortemaildatas['receiveemail_to']);
            foreach ($to as $tos) {
            $addsentto = array(
            'emailsendto_email'             => $tos,
            'emailsendto_type'              => "To",
            'emailldetail_id'               => $emailldetail_id,
            'status_id'                     => 1,
            'created_by'                    => $request->user_id,
            'created_at'                    => date('Y-m-d h:i:s'),
            );
            DB::table('emailsendto')->insert($addsentto);
            }
        }
        foreach($sortemaildata as $sortemaildatas){
            $cc = explode(',', $sortemaildatas['receiveemail_cc']);
            foreach ($cc as $ccs) {
            $addsentcc = array(
            'emailsendto_email'             => $ccs,
            'emailsendto_type'              => "Cc",
            'emailldetail_id'               => $emailldetail_id,
            'status_id'                     => 1,
            'created_by'                    => $request->user_id,
            'created_at'                    => date('Y-m-d h:i:s'),
            );
            DB::table('emailsendto')->insert($addsentcc);
            }
        }
            return response()->json(['message' => 'Email Received'],200);
        }else{
            return response()->json(['message' => 'No New Email'],200);
        }
    }
}