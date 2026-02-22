<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure this path is correct for your project structure
require __DIR__ . '/../vendor/autoload.php';

class EmailSender {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);

        try {
            // FIX: Set to 0 to stop printing random text on your screen
            $this->mail->SMTPDebug = 0; 
            
            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = 'sciencelabinventorysystem@gmail.com'; 
            $this->mail->Password   = 'moin uahp zilf omzy';
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = 587;

            // Sender settings
            $this->mail->setFrom('sciencelabinventorysystem@gmail.com', 'LIMS Notification');
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
            $this->mail->Subject = 'Your LIMS Verification Code';
            
            // Professional HTML Body
            $this->mail->Body = "
                <div style='font-family: Arial, sans-serif; border: 1px solid #ddd; padding: 20px; border-radius: 10px;'>
                    <h2 style='color: #2563eb;'>E-LIMS Identity Verification</h2>
                    <p>You are receiving this because you are registering for the Science Lab Inventory System.</p>
                    <p>Your verification code is:</p>
                    <h1 style='background: #f3f4f6; padding: 10px; text-align: center; letter-spacing: 5px; color: #1e40af;'>$otp</h1>
                    <p style='color: #6b7280; font-size: 12px;'>This code will expire in 10 minutes. If you did not request this, please ignore this email.</p>
                </div>
            ";

            return $this->mail->send();
        } catch (Exception $e) {
            // Log the error silently so you can debug it later without breaking the UI
            error_log("Email Sending Error: " . $this->mail->ErrorInfo);
            return false; 
        }
    }
}
?>