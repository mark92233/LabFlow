<?php
session_start();
require_once __DIR__ . '/dbRelated/operation.php';
if (file_exists('dbRelated/EmailSender.php')) {
    require_once 'dbRelated/EmailSender.php';
}

header('Content-Type: application/json');

$action = $_POST['action_type'] ?? null;
$response = ['status' => 'error', 'message' => 'Invalid action.'];

try {
    $db = new DataManager();

    switch ($action) {
        case 'verify_identity':
            $id_num = trim($_POST['id_number']);
            $record = $db->verifyIdentity($id_num);

            if ($record) {
                $existingUser = $db->checkExistingAccount($record['MasterID']);
                if ($existingUser) {
                    $_SESSION['login_id'] = $record['MasterID'];
                    $_SESSION['temp_name'] = $record['Full_Name'];
                    $response = [
                        'status' => 'success',
                        'next_step' => 2,
                        'data' => ['user_name' => $record['Full_Name']]
                    ];
                } else {
                    $_SESSION['temp_id'] = $record['MasterID'];
                    $_SESSION['temp_name'] = $record['Full_Name'];
                    $_SESSION['temp_role'] = $record['Role'];
                    $_SESSION['temp_email'] = $record['Official_Email'];
                    $response = [
                        'status' => 'success',
                        'next_step' => 3,
                        'data' => [
                            'user_name' => $record['Full_Name'],
                            'user_email' => $record['Official_Email']
                        ]
                    ];
                }
            } else {
                $response['message'] = "ID Number not found in school records.";
            }
            break;

        case 'verify_password':
            if (!isset($_SESSION['login_id'])) throw new Exception("Session expired. Please start over.");
            
            $pass = $_POST['password'];
            $user = $db->checkExistingAccount($_SESSION['login_id']);

            if ($user && password_verify($pass, $user['Password_Hash'])) {
                $_SESSION['user_id'] = $user['UserID'];
                $_SESSION['user_role'] = $user['Role'];
                $_SESSION['user_name'] = $user['Full_Name'];
                unset($_SESSION['login_id'], $_SESSION['temp_name']);
                $response = ['status' => 'success', 'redirect' => 'dashboard/router.php'];
            } else {
                $response['message'] = "Incorrect password. Please try again.";
            }
            break;

        case 'reg_send_otp':
            if (!isset($_SESSION['temp_email'])) {
                throw new Exception("Session expired or email not found. Please start over.");
            }
            $email = $_SESSION['temp_email'];

            // Ensure the email sending library is available
            if (!class_exists('EmailSender')) {
                $response['message'] = "Email system is offline. Please contact an administrator.";
                break; // Stop execution for this case
            }

            $otp = rand(100000, 999999);
            $mailer = new EmailSender();
            $sent = $mailer->sendOTP($email, $otp);

            if ($sent) {
                $_SESSION['confirmed_email'] = $email;
                $_SESSION['current_otp'] = $otp;
                $response = ['status' => 'success', 'next_step' => 4, 'message' => "Verification code sent to your registered email."];
            } else {
                $error_detail = $mailer->errorInfo ?: 'An unknown error occurred.';
                $response['message'] = "Mail Error: {$error_detail} Check credentials in dbRelated/EmailSender.php and ensure the Gmail account allows access.";
            }
            break;

        case 'reg_verify_otp':
            $input_otp = $_POST['otp_code'];
            if (isset($_SESSION['current_otp']) && $input_otp == $_SESSION['current_otp']) {
                $_SESSION['otp_verified'] = true;
                $response = ['status' => 'success', 'next_step' => 5];
            } else {
                $response['message'] = "Invalid code. Please check your email.";
            }
            break;

        case 'reg_finalize':
            if ($_POST['password'] === $_POST['confirm_password']) {
                $success = $db->finalizeRegistration($_SESSION['temp_id'], $_SESSION['confirmed_email'], $_POST['password']);
                if ($success) {
                    session_unset();
                    session_destroy();
                    $response = ['status' => 'success', 'next_step' => 1, 'message' => 'Account created! You may now log in.'];
                } else {
                    $response['message'] = "Database error. Account might already exist.";
                }
            } else {
                $response['message'] = "Passwords do not match.";
            }
            break;
        
        case 'reset':
            session_unset();
            session_destroy();
            $response = ['status' => 'success'];
            break;
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>