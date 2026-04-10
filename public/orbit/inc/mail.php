<?php 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function send_mail($to_email, $subject, $message, $cc_email = null){   
	if(!empty($to_email) && !empty($from_email) && !empty($from_name) && !empty($subject) && !empty($message) && !empty($smtp['host']) && !empty($smtp['username']) && !empty($smtp['password'])  && !empty($smtp['port'])){		
        return false;   
	}
    global $smtp;
    global $from_email;
    global $from_name;
    global $test_email;
    
    if(!empty($test_email)){
        $to_email = $test_email;
    }

    $mail = new PHPMailer(true);
    try {
        //Server settings    
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host       = $smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['username'];
        $mail->Password   = $smtp['password'];
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;        
        
        //Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to_email);
        //$mail->addReplyTo($from_email, $from_name);
        if ($cc_email) {
            $mail->addcc($cc_email);
        }
        //Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        $_POST = array(); 
        return true;            			
    }
    catch (Exception $e) {
        return false;   
        // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"; exit;
    }
}  
?>
