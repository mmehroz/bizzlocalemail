<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use DB;

class SendEmail extends Mailable
{
    use Queueable, SerializesModels;
    public $details;
    /**
     * Create a new message instance.
     *
     * @return void
     */
      public function __construct($details)
    {
        $this->details = $details;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if ($this->details['ccemail'] != "") {
            $too = explode(',', $this->details['toemail']);
            $ccc = explode(',', $this->details['ccemail']);
            return $this
            ->to($too)
            ->cc($ccc)
            ->from($this->details['fromemail'],$this->details['fromname'])
            ->subject($this->details['subject'])
            ->view('email.bizzemail')
            ->with('details', $this->details)
            ->withSwiftMessage(function ($swiftmessage) use (&$message_id){
               if ($this->details['isreply'] == "No") {
               $message_id = $swiftmessage->getId();
                DB::table('emailmaster')
                ->where('emailmaster_id','=',$this->details['emailmaster_id'])
                ->update([
                'emailmaster_token'    => $message_id,
                ]);
               }
            });
        }else{
            $too = explode(',', $this->details['toemail']);
            return $this
            ->to($too)
            ->from($this->details['fromemail'],$this->details['fromname'])
            ->subject($this->details['subject'])
            ->view('email.bizzemail')
            ->with('details', $this->details)
            ->withSwiftMessage(function ($swiftmessage) use (&$message_id){
                if ($this->details['isreply'] == "No") {
               $message_id = $swiftmessage->getId();
                DB::table('emailmaster')
                ->where('emailmaster_id','=',$this->details['emailmaster_id'])
                ->update([
                'emailmaster_token'    => $message_id,
                ]);
                }
            });
        }
    }
}
