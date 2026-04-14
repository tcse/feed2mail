<?php
// includes/mailer.php

function sendMail($to, $subject, $htmlBody, $plainBody = null) {
    global $config;
    
    if (!$plainBody) {
        $plainBody = strip_tags(str_replace(['<br>', '</p>', '</div>'], "\n", $htmlBody));
        $plainBody = preg_replace('/\n\s*\n/', "\n", $plainBody);
    }
    
    if ($config['mail_method'] === 'php_mail') {
        return sendMailNative($to, $subject, $htmlBody, $plainBody);
    } else {
        return sendMailSMTP($to, $subject, $htmlBody, $plainBody);
    }
}

// Метод 1: родная PHP mail()
function sendMailNative($to, $subject, $htmlBody, $plainBody) {
    global $config;
    
    $boundary = md5(time());
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: {$config['from']['name']} <{$config['from']['email']}>\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    
    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
    $body .= $plainBody . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
    $body .= $htmlBody . "\r\n";
    $body .= "--$boundary--";
    
    $headers .= 'Reply-To: ' . $config['from']['email'] . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();
    
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

// Метод 2: SMTP через PHPMailer (нужна установка)
function sendMailSMTP($to, $subject, $htmlBody, $plainBody) {
    global $config;
    
    // Если PHPMailer не установлен — пробуем native как fallback
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer not found, falling back to native mail()");
        return sendMailNative($to, $subject, $htmlBody, $plainBody);
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['smtp']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp']['username'];
        $mail->Password = $config['smtp']['password'];
        $mail->SMTPSecure = $config['smtp']['encryption'];
        $mail->Port = $config['smtp']['port'];
        
        $mail->setFrom($config['from']['email'], $config['from']['name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("SMTP Error: " . $mail->ErrorInfo);
        return false;
    }
}