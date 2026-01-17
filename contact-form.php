<?php
// Disable error reporting to output to ensure valid JSON
ini_set('display_errors', 0);
error_reporting(0);

// Log errors to a file for debugging
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/debug_log.txt');

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$isWP = false;


// Log immediately to verify script access
error_log("Script accessed. Request method: " . $_SERVER['REQUEST_METHOD']);

if (file_exists("../../../../../wp-load.php")) {
    include("../../../../../wp-load.php");
    $isWP = true;
}

$emailTo       = '';
$sender_email = 'muba4shir@gmail.com';
$subject = 'You received a new message';

$errors = array();
$data   = array();
$body    = '';
$email = '';
$name = '';
$domain = '';
if (isset($_POST['email'])) $domain = $_POST['domain'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Contact form POST received");
    $arr = $_POST['values'];
    $sender_email = 'contacts@' . $domain;
    $email = 'no-replay@' . $domain;
    $error = "Error. Messagge not sent.";

    if (isset($_POST['email']) && strlen($_POST['email']) > 0)  $emailTo = $_POST['email'];
    if (isset($_POST['subject_email']) && strlen($_POST['subject_email']) > 0) $subject = $_POST['subject_email'];
    else $subject = '[' . $domain . '] New message';

    foreach ($arr as $key => $value ) {
        $val =  stripslashes(trim($value[0]));
        if (!empty($val)) {
            $body .= ucfirst($key) . ': ' . $val . PHP_EOL . PHP_EOL;
            if ($key == "email"||$key == "Email"||$key == "E-mail"||$key == "e-mail"||strpos($key, "mail") > -1) $email = $val;
            if ($key == "name"||$key == "nome"||$key == "Nome") $name = $val;
        }
    }
    $body .= "-------------------------------------------------------------------------------------------" . PHP_EOL . PHP_EOL;
    $body .= "New message from " . $domain;

    if ($name == '') $name = $subject;
    if (!empty($errors)) {
        $data['success'] = false;
        $data['errors']  = $errors;
    } else {
        $headers  = "From: " . $email . "\r\n";
        $headers .= "Reply-To: " . $email . "\r\n";
        $result;
        $config;
        if ((isset($_POST['engine']) && $_POST['engine'] == "smtp") || ($isWP && hc_get_setting("smtp-host") != "")) {
            require 'themekit/scripts/contact-form/phpmailer/PHPMailerAutoload.php';
            if ($isWP) {
                $config = array("host" => hc_get_setting("smtp-host"),"username" => hc_get_setting("smtp-username"),"password" => hc_get_setting("smtp-psw"),"port" => hc_get_setting("smtp-port"),"email_from" => hc_get_setting("smtp-email"));
            } else {
                require 'themekit/scripts/contact-form/phpmailer/config.php';
                $config = $smtp_config;
            }
            $mail = new PHPMailer;
            $message = nl2br($body);
            $mail->isSMTP();
            $mail->Host = $config["host"];
            $mail->SMTPAuth = true;
            $mail->Username = $config["username"];
            $mail->Password = $config["password"];
            $mail->SMTPSecure = 'ssl';
            $mail->Port = $config["port"];
            $mail->setFrom($config["email_from"]);
            if (strpos($emailTo,",") > 0) {
                $arr = explode(",",$emailTo);
                for ($i = 0; $i < count($arr); $i++) {
                    $mail->addAddress($arr[$i]);
                }
            } else {
                $mail->addAddress($emailTo);
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = $message;
            $result = $mail->send();
            if (!$result) $error = $mail->ErrorInfo;
        } else {
            // Fix: Use a valid From address based on the server domain, not the user's email (to avoid SPF failure)
            $server_domain = $_SERVER['SERVER_NAME'];
            if ($server_domain == 'localhost' || $server_domain == '127.0.0.1') {
                $server_domain = 'hydrotechglobal.com'; // Fallback for local testing
            }
            $sender_email = "noreply@" . $server_domain;
            
            $headers  = "From: Contact Form <" . $sender_email . ">\r\n";
            $headers .= "Reply-To: " . $email . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            if ($isWP) {
                try {
                    $result = wp_mail($emailTo, $subject, $body, $headers);
                }  catch (Exception $exception) {
                    $result = mail($emailTo, $subject, $body, $headers);
                }
            } else {
                // On Windows/XAMPP specifically, sendmail_from might be needed
                ini_set("sendmail_from", $sender_email); 
                $result = mail($emailTo, $subject, $body, $headers);
            }
        }
        if ($result) {
            $data['success'] = true;
            $data['message'] = 'Congratulations. Your message has been sent successfully.';
            error_log("Mail successfully accepted for delivery to: " . $emailTo);
        } else {
            $data['success'] = false;
            $data['message'] = $error;
            error_log("Mail failed to send. Error: " . $error . " | Headers: " . print_r($headers, true));
        }
    }
    echo json_encode($data);
}
