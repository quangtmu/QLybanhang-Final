<?php

declare(strict_types=1);

class EmailService
{
    public static function send(string $to, string $subject, string $body): array
    {
        $to = trim($to);
        $subject = trim($subject);
        $fromEmail = (string) env('MAIL_FROM_EMAIL', 'no-reply@example.com');
        $fromName = (string) env('MAIL_FROM_NAME', APP_NAME);
        $mailEnabled = filter_var(env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);

        $logDir = BASE_PATH . '/storage/mail_logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $logPath = $logDir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.txt';
        $content = "To: {$to}\n";
        $content .= "From: {$fromName} <{$fromEmail}>\n";
        $content .= "Subject: {$subject}\n";
        $content .= "Date: " . date('c') . "\n\n";
        $content .= $body . "\n";
        file_put_contents($logPath, $content);

        $sent = false;

        if ($mailEnabled && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $headers = [
                'From: ' . $fromName . ' <' . $fromEmail . '>',
                'Content-Type: text/plain; charset=UTF-8',
            ];
            $sent = mail($to, $subject, $body, implode("\n", $headers));
        }

        return [
            'sent' => $sent,
            'logged_path' => $logPath,
        ];
    }
}
