<?php

/**
 * Created by PhpStorm.
 * User: 剑正泣一
 * Date: 2018/3/31
 * Time: 14:30
 */
namespace app\index\service;

use PHPMailer\PHPMailer\PHPMailer;
use think\Exception;

class Mail
{
    public function __construct( $host, $username, $pwd, $to_user, $subject = '', $body = '', $alt_body = '' )
    {
        dump($subject);
        dump($to_user);
        dump($pwd);
        dump($subject);
        dump($body);
        dump($alt_body);
        $mail = new PHPMailer(true);                              // Passing `true` enables exceptions
        $mail->CharSet = 'utf-8';
        try {
            //Server settings
            $mail->SMTPDebug = 2;                                 // Enable verbose debug output
            $mail->isSMTP();                                      // Set mailer to use SMTP
            $mail->Host = $host;  // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Username = $username;                 // SMTP username
            $mail->Password = $pwd;                           // SMTP password
            $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 25;                                    // TCP port to connect to

            //Recipients
            $mail->setFrom($username, 'masaichi');

            $mail->addAddress($to_user, $to_user);     // Add a recipient
//            $mail->addAddress('ellen@example.com');               // Name is optional
//            $mail->addReplyTo('info@example.com', 'Information');

//            $mail->addCC('cc@example.com');
//            $mail->addBCC('bcc@example.com');

            //Attachments
//            $mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
//            $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $alt_body;
            $mail->send();
            return true;
        } catch (\think\Exception $e) {
            echo $e->getMessage();
            echo $mail->ErrorInfo;
            return false;
        }
    }
}