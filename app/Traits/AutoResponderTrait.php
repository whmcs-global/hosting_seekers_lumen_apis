<?php
 
namespace App\Traits;

use Illuminate\Support\Facades\Mail;
use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;

use Illuminate\Support\Facades\Config;
use App\Models\{AutoResponder, SmtpInformation, EmailLog};
use Crypt;
 
trait AutoResponderTrait {
 
    public function get_template_by_name($name) {
 		$template = AutoResponder::where('template_name', $name)->first(['id', 'template_name','subject','template']);
 		return $template;
    }
 
    public function get_template_by_id($id) {
 		$template = AutoResponder::where('id', $id)->first(['id', 'template_name','subject','template']);
 		return $template;
    }

    public function get_smtp_info() {
 		$smtp = SmtpInformation::where('status', 1)->first(['host', 'port', 'from_email', 'username', 'from_name', 'password', 'encryption']);
 		return $smtp;
    }

    public function email_log_create($to, $template_id, $template_name, $token = 0) {
        $data = array(
            'to_email' => $to,
            'auto_responder_id' => $template_id,
            'template_name' => $template_name,
            'token' => $token
        );
        $record = EmailLog::create($data);
        return $record->id;
   }

   public function email_log_update($id) {
       $data = array(
           'status' => 1,
       );
       $record = EmailLog::where('id', $id)->update($data);
  }

  public function checkToken($token) {
      $token = explode('.', $token);
      $data = array(
          'is_read' => 1,
      );
      $record = EmailLog::where('token', $token[0])->update($data);
 }

    public function send_mail($to, $subject, $email_body){
        $to_cc = Config::get('constants.CC_EMAIL');
        $smtp = $this->get_smtp_info();
        // $password = Crypt::decrypt($smtp->password);
        $password = $smtp->password;
        // Create the Transport
        $transport = (new Swift_SmtpTransport($smtp->host, $smtp->port, $smtp->encryption ))
        ->setUsername($smtp->username)
        ->setPassword($password);
        
        // Create the Mailer using your created Transport
        $mailer = new Swift_Mailer($transport);
        
        // Create a message
        $message = (new Swift_Message($subject))
        // ->setFrom(['info@hostingseekers.com' => $smtp->from_name])
        ->setFrom([$smtp->from_email => $smtp->from_name])
        ->setTo($to)
        ->setBody($email_body, 'text/html');

        // Send the message
        $result = $mailer->send($message);
        return $result;

    } 
}