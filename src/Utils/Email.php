<?php

// file: Utils/Email.php
declare(strict_types=1);

namespace App\Utils;

use App\Core\Config;
use App\Services\SecurityService;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Email
{
    private PHPMailer $mail;

    /**
     * Constructor to initialize PHPMailer.
     */
    public function __construct()
    {
        // Initialize PHPMailer
        $this->mail = new PHPMailer();

        // Configure SMTP settings using Config class
        $this->mail->isSMTP();
        $this->mail->Host = Config::getSmtpHost();
        $this->mail->SMTPAuth = true;
        $this->mail->Username = Config::getSmtpUsername();
        $this->mail->Password = Config::getSmtpPassword();
        $this->mail->Port = Config::getSmtpPort();
        $this->mail->SMTPDebug = Config::getSmtpDebug();

        // Set default sender
        $this->mail->setFrom(Config::getEmailFrom(), Config::getEmailFromName());
    }

    /**
     * Send a plain text email.
     *
     * @param string $to The recipient's email address.
     * @param string $subject The email subject.
     * @param string $body The email body (plain text).
     * @return bool Returns true if the email was sent successfully, false otherwise.
     */
    public function sendPlainText(string $to, string $subject, string $body): bool
    {
        try {
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->isHTML(false); // Plain text email
            $this->mail->send();
            $this->clear();

            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $this->mail->ErrorInfo);

            return false;
        }
    }

    /**
     * Send an HTML email.
     *
     * @param string $to The recipient's email address.
     * @param string $subject The email subject.
     * @param string $htmlBody The email body (HTML).
     * @return bool Returns true if the email was sent successfully, false otherwise.
     */
    public function sendHtml(string $to, string $subject, string $htmlBody): bool
    {
        try {
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $htmlBody;
            $this->mail->isHTML(true); // HTML email
            $this->mail->AltBody = strip_tags($htmlBody); // Fallback plain text for non-HTML clients
            $this->mail->send();
            $this->clear();

            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $this->mail->ErrorInfo);

            return false;
        }
    }

    /**
     * Add an attachment to the email.
     *
     * @param string $filePath The path to the file to attach.
     * @return void
     */
    public function addAttachment(string $filePath): void
    {
        $this->mail->addAttachment($filePath);
    }

    /**
     * Clear all recipients and attachments from the email.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->mail->clearAddresses();
        $this->mail->clearAttachments();
    }

    /**
     * Send an account activation email.
     *
     * @param string $email The user's email
     * @param string $activationToken The activation token
     * @return bool Returns true if the email was sent successfully, false otherwise.
     */
    public static function sendActivationEmail(string $email, string $activationToken): bool
    {
        $emailInstance = new self(); // Create an instance of the Email class

        $domain = Config::get('domain');
        $scheme = Config::get('scheme');

        // Sanitize domain and scheme to prevent header injection
        $securityService = SecurityService::getInstance();
        $domain = $securityService->sanitizeHtml($domain);
        $scheme = $securityService->sanitizeHtml($scheme);
        $activationToken = $securityService->sanitizeHtml($activationToken);

        $activationLink = "$scheme://$domain/activate/{$activationToken}";

        $subject = "Activate Your Account";

        $body = "<h1>Welcome!</h1>
            <p>Please click the link below to activate your account:</p>
            <a href='" . htmlspecialchars($activationLink, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($activationLink, ENT_QUOTES, 'UTF-8') . "</a>";

        return $emailInstance->sendHtml($email, $subject, $body);
    }

    /**
     * Send a password reset email.
     *
     * @param string $email The user's email
     * @param string $resetToken The password reset token
     * @return bool Returns true if the email was sent successfully, false otherwise.
     */
    public static function sendPasswordResetEmail(string $email, string $resetToken): bool
    {
        $emailInstance = new self(); // Create an instance of the Email class

        $domain = Config::get('domain');
        $scheme = Config::get('scheme');

        // Sanitize domain and scheme to prevent header injection
        $securityService = SecurityService::getInstance();
        $domain = $securityService->sanitizeHtml($domain);
        $scheme = $securityService->sanitizeHtml($scheme);
        $resetToken = $securityService->sanitizeHtml($resetToken);

        $resetLink = "$scheme://$domain/reset-password/{$resetToken}";

        $subject = "Password Reset Request";

        $body = "<h1>Password Reset</h1>
            <p>You requested a password reset. Please click the link below to reset your password:</p>
            <a href='" . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . "</a>";

        return $emailInstance->sendHtml($email, $subject, $body);
    }
}
