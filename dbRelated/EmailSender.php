<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure this path is correct for your project structure
require __DIR__ . '/../vendor/autoload.php';

class EmailSender {
    private $mail;
    public $errorInfo = '';

    public function __construct() {
        $this->mail = new PHPMailer(true);

        try {
            // Enable verbose debug output to the PHP error log for diagnostics
            $this->mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            $this->mail->Debugoutput = 'error_log';

            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = 'andomark922@gmail.com'; 
            $this->mail->Password   = 'viuq wegk qhur yogb';
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = 587;

            // Sender settings
            $this->mail->setFrom('andomark922@gmail.com', 'LabFlow Notification');
        } catch (Exception $e) {
            // Log error silently to a file instead of crashing the page
            error_log("Mailer Setup Error: " . $e->getMessage());
        }
    }

    /**
     * Sends a 6-digit OTP to the user's email.
     * @param string $recipientEmail
     * @param int $otp
     * @return bool
     */
    public function sendOTP($recipientEmail, $otp) {
        if (empty($recipientEmail)) {
            return false;
        }

        try {
            // Content
            $this->mail->clearAddresses(); // Clear previous recipients if any
            $this->mail->addAddress($recipientEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Your LabFlow Verification Code';
            
            // Professional HTML Body
            $this->mail->Body = "
                <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
                    <div style='background-color: #f97316; padding: 20px; text-align: center;'>
                        <h1 style='color: white; font-size: 24px; margin: 0;'>LabFlow Verification</h1>
                    </div>
                    <div style='padding: 30px 25px; background-color: #ffffff; line-height: 1.6;'>
                        <h2 style='color: #1e293b; font-size: 20px; margin-top: 0;'>Confirm Your Identity</h2>
                        <p style='color: #475569; font-size: 16px;'>
                            You are receiving this because you are registering for the WMSU CSM LabFlow System.
                        </p>
                        <p style='color: #475569; font-size: 16px;'>
                            Please use the following verification code to complete your account setup:
                        </p>
                        <div style='background-color: #fff7ed; border: 2px dashed #fb923c; color: #c2410c; text-align: center; padding: 20px; margin: 25px 0; border-radius: 8px;'>
                            <p style='font-size: 14px; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 1px; color: #ea580c;'>Your One-Time Password</p>
                            <p style='font-size: 42px; font-weight: 700; margin: 0; letter-spacing: 8px; line-height: 1;'>
                                $otp
                            </p>
                        </div>
                        <p style='color: #475569; font-size: 16px;'>
                            This code will expire in 10 minutes. If you did not request this, please ignore this email.
                        </p>
                    </div>
                    <div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'>
                        <p style='color: #94a3b8; font-size: 12px; margin: 0;'>
                            &copy; 2026 LabFlow System | WMSU College of Science and Mathematics
                        </p>
                    </div>
                </div>
            ";

            return $this->mail->send();
        } catch (Exception $e) {
            // Store the error message to be accessed from outside
            $this->errorInfo = $this->mail->ErrorInfo;
            error_log("Email Sending Error: " . $this->errorInfo);
            return false; 
        }
    }
}
?>