<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Opsiyonel e-posta (spec §27). SMTP_HOST boşsa e-posta kapalıdır ve uygulama çalışmaya devam eder
 * (davet bağlantıları ekranda gösterilir). Kimlik bilgileri yalnızca .env'den gelir, loglanmaz.
 */
class MailService
{
    /**
     * @param array{host: string, port: int, username: string, password: string, encryption: string, from_address: string, from_name: string, timeout: int} $config
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly array $config,
        private readonly Logger $logger,
    ) {
    }

    public function enabled(): bool
    {
        return $this->config['host'] !== '' && filter_var($this->config['from_address'], FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @return bool Gönderildi mi (hata kullanıcı akışını durdurmaz; log'a yazılır)
     */
    public function send(string $to, string $subject, string $textBody): bool
    {
        if (!$this->enabled() || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->Port = $this->config['port'];
            $mail->Timeout = $this->config['timeout'];
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            if ($this->config['username'] !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $this->config['username'];
                $mail->Password = $this->config['password'];
            }
            $mail->SMTPSecure = match ($this->config['encryption']) {
                'ssl' => PHPMailer::ENCRYPTION_SMTPS,
                'none' => '',
                default => PHPMailer::ENCRYPTION_STARTTLS,
            };
            $mail->SMTPAutoTLS = $this->config['encryption'] !== 'none';
            $mail->setFrom($this->config['from_address'], $this->config['from_name']);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $textBody;
            $mail->isHTML(false);
            $mail->send();

            return true;
        } catch (MailException $e) {
            // SMTP hata mesajı kimlik bilgisi içermez; yine de yalnızca kısa özet loglanır
            $this->logger->warning('Mail sending failed', ['error_type' => 'mail', 'detail' => mb_substr($mail->ErrorInfo, 0, 200)]);

            return false;
        }
    }
}
