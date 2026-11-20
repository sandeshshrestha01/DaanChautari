<?php
/**
 * Dan Chautari - Mailer Helper
 * Sends emails using PHPMailer via Gmail SMTP.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHP mailer/Exception.php';
require_once __DIR__ . '/PHP mailer/PHPMailer.php';
require_once __DIR__ . '/PHP mailer/SMTP.php';

/**
 * Send a 6-digit OTP code to a recipient email for password reset.
 *
 * @param string $recipient_email
 * @param string $recipient_name
 * @param string $otp
 * @return bool True on success, false on failure
 */
function send_otp_email(string $recipient_email, string $recipient_name, string $otp): bool {
    $mail = new PHPMailer(true);

    try {
        // Server configuration
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sandeshcrest7@gmail.com';
        $mail->Password   = 'qmfd wgaw sovb praj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom('sandeshcrest7@gmail.com', 'Daan Chautari');
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->addReplyTo('sandeshcrest7@gmail.com', 'Daan Chautari');

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Your Password Reset OTP - Daan Chautari";

        $mail->Body = "
        <div style=\"font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 550px; margin: 0 auto; padding: 25px; border: 1px solid #e0e0e0; border-radius: 12px; background-color: #ffffff;\">
            <div style=\"text-align: center; margin-bottom: 20px;\">
                <h2 style=\"color: #2e7d32; margin: 0; font-size: 26px;\">Daan Chautari</h2>
                <p style=\"color: #666; font-size: 14px; margin-top: 4px;\">Sahayogko Chautari, Aashako Yatra</p>
            </div>
            <div style=\"padding: 20px 0; border-top: 2px solid #2e7d32; border-bottom: 1px solid #eeeeee;\">
                <p style=\"font-size: 16px; color: #333; margin-bottom: 15px;\">Namaste <strong>" . htmlspecialchars($recipient_name) . "</strong>,</p>
                <p style=\"font-size: 15px; color: #555; line-height: 1.5;\">We received a request to reset your password for your Daan Chautari account. Use the 6-digit OTP code below to proceed:</p>
                <div style=\"text-align: center; margin: 30px 0;\">
                    <span style=\"font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #1b5e20; background: #e8f5e9; padding: 12px 28px; border-radius: 8px; border: 1px dashed #2e7d32; display: inline-block;\">" . htmlspecialchars($otp) . "</span>
                </div>
                <p style=\"font-size: 15px; color: #555; line-height: 1.5;\">Please Do Not Share This OTP With Anyone. It is confidential and should be used only by you.</p>
                <p style=\"font-size: 14px; color: #d32f2f; margin-bottom: 10px;\">This OTP code is valid for <strong>2 minutes</strong>.</p>
                <p style=\"font-size: 13px; color: #777;\">If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
            </div>
            <div style=\"text-align: center; margin-top: 20px; font-size: 12px; color: #888;\">
                <p style=\"margin: 0;\">&copy; " . date('Y') . " Daan Chautari. All rights reserved.</p>
            </div>
        </div>";

        $mail->AltBody = "Namaste {$recipient_name},\n\nYour 6-digit password reset OTP is: {$otp}\n\nThis OTP is valid for 2 minutes.\n\nIf you did not request this, please ignore this message.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/*  email for volunteer    */
function send_volunteer_message(string $email, string $name, string $subject, string $message): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sandeshcrest7@gmail.com';
        $mail->Password   = 'qmfd wgaw sovb praj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('sandeshcrest7@gmail.com', 'Daan Chautari');
        $mail->addAddress($email, $name);
        $mail->addReplyTo('sandeshcrest7@gmail.com', 'Daan Chautari');

        $mail->isHTML(true);
        $mail->Subject = $subject;

        $safeMsg = nl2br(htmlspecialchars($message));

        $mail->Body = "
        <div style=\"font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; max-width:550px; margin:0 auto; padding:25px; border:1px solid #e0e0e0; border-radius:12px; background:#ffffff;\">
            <div style=\"text-align:center; margin-bottom:20px;\">
                
                <h2 style=\"color:#2e7d32; margin:0; font-size:26px;\">Daan Chautari</h2>
                <p style=\"color:#666; font-size:14px; margin-top:4px;\">Sahayogko Chautari, Aashako Yatra</p>
            </div>
            <div style=\"padding:20px 0; border-top:2px solid #2e7d32; border-bottom:1px solid #eeeeee;\">
                <p style=\"font-size:16px; color:#333;\">Namaste <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <div style=\"font-size:15px; color:#555; line-height:1.6; margin:15px 0;\">{$safeMsg}</div>
                <p style=\"font-size:13px; color:#777;\">You are receiving this because you applied as a volunteer with Daan Chautari.</p>
            </div>
            <div style=\"text-align:center; margin-top:20px; font-size:12px; color:#888;\">
                <p style=\"margin:0;\">&copy; " . date('Y') . " Daan Chautari. All rights reserved.</p>
            </div>
        </div>";

        $mail->AltBody = "Namaste {$name},\n\n{$message}\n\n- Daan Chautari";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}