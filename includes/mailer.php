<?php

declare(strict_types=1);

/**
 * Email Mailer Wrapper
 *
 * Provides email sending functionality. All sends route through the canonical
 * mailprovider path (mailprovider -> mailserviceclient -> mail-service) with
 * the external/local switch intact; the original PHPMailer SMTP delivery is
 * retained as the local-mode fallback via sendMailInternal().
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Canonical mailprovider helpers (mail_provider_send_mail etc.).
require_once __DIR__ . '/../vendor/avisitor/mailprovider/src/Support/AppMailProviderHelpers.php';

/**
 * Local-mode delivery used by mail_provider_send_mail's local callback when
 * USE_EXTERNAL_MAIL is not enabled. Keeps the original PHPMailer SMTP path
 * available as the local-dev fallback (mailprovider's external/local switch
 * intact).
 *
 * @param array $mailparams mailparams with a single 'to' recipient array
 *                          (['email' => ..., 'name' => ...]).
 * @return bool
 */
function sendMailInternal( $mailparams ) {
    $to = $mailparams['to'] ?? '';
    if (is_array($to)) {
        $toemail = $to['email'] ?? '';
        $toname  = $to['name'] ?? '';
    } else {
        $toemail = $to;
        $toname  = $mailparams['toname'] ?? '';
    }
    $subject = $mailparams['subject'] ?? '';
    $message = $mailparams['message'] ?? '';
    $from    = $mailparams['from'] ?? '';
    $fromName = $mailparams['fromname'] ?? '';

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USER');
        $mail->Password = env('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) env('SMTP_PORT', 587);

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom(
            $from ?: env('SMTP_FROM_EMAIL', env('SMTP_USER')),
            $fromName ?: env('SMTP_FROM_NAME', 'Maui Garden Tour')
        );

        $mail->addAddress($toemail, $toname);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email send failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send an email using the canonical mailprovider path.
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string|null $textBody Plain text alternative (optional)
 * @return bool True on success
 * @throws Exception on failure
 */
function sendEmail(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
{
    $mailparams = [
        'to' => [ [ 'email' => $to, 'name' => '' ] ],
        'subject' => $subject,
        'message' => $htmlBody,
        'from' => (string) env('SMTP_FROM_EMAIL', (string) env('SMTP_USER', '')),
        'fromname' => (string) env('SMTP_FROM_NAME', 'Maui Garden Tour'),
    ];
    $result = \MailProvider\Support\mail_provider_send_mail($mailparams);
    if (empty($result['success'])) {
        error_log("Email send failed: " . ($result['error'] ?? 'Unknown error'));
        throw new Exception('Email send failed: ' . ($result['error'] ?? 'Unknown error'));
    }
    return true;
}

/**
 * Send confirmation email for a pending submission
 *
 * @param string $email Recipient email
 * @param string $token Confirmation token
 * @param array $submissionData Additional data for the email
 * @return bool
 */
function sendConfirmationEmail(string $email, string $token, array $submissionData = []): bool
{
    $siteUrl = rtrim(env('SITE_URL', 'http://localhost'), '/');
    $siteDomain = parse_url($siteUrl, PHP_URL_HOST) ?: 'mauigardentour.com';
    $confirmUrl = $siteUrl . '/confirm.php?token=' . urlencode($token);
    $adminEmail = env('ADMIN_EMAIL', '');
    $expiryHours = env('TOKEN_EXPIRY_HOURS', 24);

    $lat = $submissionData['latitude'] ?? 'N/A';
    $lng = $submissionData['longitude'] ?? 'N/A';
    
    // Subject without spammy words, clear sender intent
    $subject = "Please confirm your garden location submission";

    // Plain text version (important for spam filters!)
    $textBody = <<<TEXT
Maui Garden Tour - Confirm Your Submission

Aloha!

Please confirm your submission to the Maui Garden Tour Map by visiting the link below:

{$confirmUrl}

Submission Details:
- Location: {$lat}, {$lng}
- Email: {$email}

This link expires in {$expiryHours} hours.

If you didn't submit a location to the Maui Garden Tour Map, you can safely ignore this email.

TEXT;

    if ($adminEmail) {
        $textBody .= "Questions? Contact us at {$adminEmail}\n\n";
    }
    
    $textBody .= "Mahalo!\nMaui Garden Tour\n{$siteUrl}";

    // HTML version - clean, no excessive styling or spam triggers
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm your submission</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="text-align: center; padding-bottom: 20px;">
                <h1 style="color: #2e7d32; margin-bottom: 10px; font-size: 24px;">Maui Garden Tour</h1>
            </td>
        </tr>
        <tr>
            <td>
                <p>Aloha!</p>
                
                <p>Please confirm your submission to the Maui Garden Tour Map by clicking the button below:</p>
                
                <table width="100%" cellpadding="0" cellspacing="0" style="background: #f5f5f5; border-radius: 8px; margin: 20px 0;">
                    <tr>
                        <td style="padding: 15px;">
                            <p style="margin: 5px 0;"><strong>Location:</strong> {$lat}, {$lng}</p>
                            <p style="margin: 5px 0;"><strong>Email:</strong> {$email}</p>
                        </td>
                    </tr>
                </table>
                
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="text-align: center; padding: 20px 0;">
                            <a href="{$confirmUrl}" 
                               style="display: inline-block; background: #2e7d32; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                                Confirm My Submission
                            </a>
                        </td>
                    </tr>
                </table>
                
                <p style="color: #666666; font-size: 14px;">
                    Or copy this link into your browser:<br>
                    <a href="{$confirmUrl}" style="color: #2e7d32; word-break: break-all;">{$confirmUrl}</a>
                </p>
                
                <p style="color: #999999; font-size: 13px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eeeeee;">
                    This link expires in {$expiryHours} hours.<br><br>
                    If you didn't submit a location to the Maui Garden Tour Map, you can safely ignore this email.
                </p>
HTML;

    if ($adminEmail) {
        $htmlBody .= <<<HTML
                
                <p style="color: #999999; font-size: 13px;">
                    Questions? Contact us at <a href="mailto:{$adminEmail}" style="color: #2e7d32;">{$adminEmail}</a>
                </p>
HTML;
    }

    $htmlBody .= <<<HTML
                
                <p style="color: #999999; font-size: 13px; margin-top: 30px; text-align: center;">
                    Mahalo!<br>
                    <a href="{$siteUrl}" style="color: #2e7d32;">Maui Garden Tour</a>
                </p>
            </td>
        </tr>
    </table>
    
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 20px; border-top: 1px solid #eeeeee;">
        <tr>
            <td style="padding-top: 15px; text-align: center; color: #999999; font-size: 11px;">
                <p>
                    This email was sent to {$email} because this address was used to submit a location on the Maui Garden Tour Map.<br>
                    {$siteDomain}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    return sendEmail($email, $subject, $htmlBody, $textBody);
}
