<?php

class Email {
    /**
     * Send email using SMTP or PHP mail function
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Email message (HTML)
     * @param string $from Sender email address (optional)
     * @return bool True if email was sent successfully
     */
    public static function send($to, $subject, $message, $from = null) {
        // Default sender
        if (!$from) {
            $from = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@trinitydistribution.com';
        }

        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Trinity Distribution';

        // Add logo and styling to message
        $styledMessage = self::getEmailTemplate($message);

        // Check if SMTP is enabled and configured
        if (defined('SMTP_ENABLED') && SMTP_ENABLED && defined('SMTP_USERNAME') && !empty(SMTP_USERNAME)) {
            return self::sendViaSMTP($to, $subject, $styledMessage, $from, $fromName);
        }

        // Fallback to PHP mail function
        return self::sendViaMail($to, $subject, $styledMessage, $from, $fromName);
    }

    /**
     * Send email via SMTP using fsockopen
     */
    private static function sendViaSMTP($to, $subject, $message, $from, $fromName) {
        try {
            $host = SMTP_HOST;
            $port = SMTP_PORT;
            $username = SMTP_USERNAME;
            $password = SMTP_PASSWORD;
            $secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';

            // Connect to SMTP server
            $socket = @fsockopen(($secure === 'ssl' ? 'ssl://' : '') . $host, $port, $errno, $errstr, 30);

            if (!$socket) {
                error_log("SMTP Connection failed: $errstr ($errno)");
                // Fallback to mail function
                return self::sendViaMail($to, $subject, $message, $from, $fromName);
            }

            // Read greeting
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '220') {
                error_log("SMTP Error: Unexpected greeting: $response");
                fclose($socket);
                return self::sendViaMail($to, $subject, $message, $from, $fromName);
            }

            // Send EHLO
            fputs($socket, "EHLO " . gethostname() . "\r\n");
            $response = self::getSmtpResponse($socket);

            // Start TLS if needed
            if ($secure === 'tls') {
                fputs($socket, "STARTTLS\r\n");
                $response = fgets($socket, 515);
                if (substr($response, 0, 3) != '220') {
                    error_log("SMTP Error: STARTTLS failed: $response");
                    fclose($socket);
                    return self::sendViaMail($to, $subject, $message, $from, $fromName);
                }

                // Enable crypto
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

                // Send EHLO again after STARTTLS
                fputs($socket, "EHLO " . gethostname() . "\r\n");
                $response = self::getSmtpResponse($socket);
            }

            // Authenticate
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 515);

            fputs($socket, base64_encode($username) . "\r\n");
            $response = fgets($socket, 515);

            fputs($socket, base64_encode($password) . "\r\n");
            $response = fgets($socket, 515);

            if (substr($response, 0, 3) != '235') {
                error_log("SMTP Auth failed: $response");
                fclose($socket);
                return self::sendViaMail($to, $subject, $message, $from, $fromName);
            }

            // Send email
            fputs($socket, "MAIL FROM:<$from>\r\n");
            $response = fgets($socket, 515);

            fputs($socket, "RCPT TO:<$to>\r\n");
            $response = fgets($socket, 515);

            fputs($socket, "DATA\r\n");
            $response = fgets($socket, 515);

            // Build email headers and body
            $headers = "From: $fromName <$from>\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "Subject: $subject\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "\r\n";

            fputs($socket, $headers . $message . "\r\n.\r\n");
            $response = fgets($socket, 515);

            // Quit
            fputs($socket, "QUIT\r\n");
            fclose($socket);

            if (substr($response, 0, 3) == '250') {
                error_log("Email sent successfully via SMTP to: $to");
                return true;
            } else {
                error_log("SMTP send failed: $response");
                return false;
            }

        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return self::sendViaMail($to, $subject, $message, $from, $fromName);
        }
    }

    /**
     * Get SMTP multi-line response
     */
    private static function getSmtpResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    }

    /**
     * Send email via PHP mail function (fallback)
     */
    private static function sendViaMail($to, $subject, $message, $from, $fromName) {
        // Email headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . phpversion()
        ];

        // Send email
        $result = @mail($to, $subject, $message, implode("\r\n", $headers));

        // Log email sending
        if ($result) {
            error_log("Email sent successfully via mail() to: $to");
        } else {
            error_log("Failed to send email via mail() to: $to - mail() function may not be configured");
        }

        return $result;
    }

    /**
     * Get email template with Trinity Distribution branding
     */
    private static function getEmailTemplate($content) {
        // Use BASE_URL for logo if defined, otherwise fallback
        $logoUrl = defined('BASE_URL') ? BASE_URL . '/assets/images/logo.png' : 'https://trinity.futurewebhost.com.ng/assets/images/logo.png';

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .email-header {
            background-color: #dc3545;
            padding: 30px 20px;
            text-align: center;
        }
        .email-header img {
            max-width: 200px;
            height: auto;
        }
        .email-body {
            padding: 30px 20px;
        }
        .email-footer {
            background-color: #000000;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        a {
            color: #dc3545;
            text-decoration: none;
        }
        .btn {
            display: inline-block;
            background-color: #dc3545;
            color: white !important;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: 500;
        }
        .btn:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <img src="' . $logoUrl . '" alt="Trinity Distribution" style="filter: brightness(0) invert(1);">
        </div>
        <div class="email-body">
            ' . $content . '
        </div>
        <div class="email-footer">
            <p>&copy; ' . date('Y') . ' Trinity Distribution. All rights reserved.</p>
            <p>Developed and maintained by Trinity Distribution</p>
        </div>
    </div>
</body>
</html>
        ';
    }
}

