<?php
namespace PHPMailer\PHPMailer;

/**
 * Minimal PHPMailer replacement for Gmail SMTP.
 */
class PHPMailer
{
    public string $Host = 'smtp.gmail.com';
    public int $Port = 587;
    public ?string $Username = null;
    public ?string $Password = null;
    public string $SMTPSecure = 'tls';
    public bool $SMTPAuth = true;
    public string $From = '';
    public string $FromName = '';
    public string $Subject = '';
    public string $Body = '';
    public string $AltBody = '';
    public string $CharSet = 'UTF-8';
    public int $SMTPDebug = 0;

    protected array $recipients = [];
    protected bool $isHTML = false;
    protected array $errors = [];

    public function isSMTP()
    {
        // no-op for backwards compatibility
        return $this;
    }

    public function setFrom(string $address, string $name = ''): void
    {
        $this->From = $address;
        $this->FromName = $name;
    }

    public function addAddress(string $address, string $name = ''): void
    {
        $this->recipients[] = ['address' => $address, 'name' => $name];
    }

    public function isHTML(bool $isHTML = true): void
    {
        $this->isHTML = $isHTML;
    }

    public function send(): bool
    {
        if (!$this->From || empty($this->recipients)) {
            $this->errors[] = 'Missing From or recipient';
            return false;
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $transport = strtolower($this->SMTPSecure) === 'ssl' ? 'ssl' : 'tcp';
        $socket = stream_socket_client(
            "{$transport}://{$this->Host}:{$this->Port}",
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket || !is_resource($socket)) {
            $this->errors[] = "Socket error {$errno}: {$errstr}";
            return false;
        }

        /** @var resource $socket */

        try {
            $this->expectResponse($socket, 220);
            $this->sendCommand($socket, "EHLO " . $this->getHostname(), 250);

            if (strtolower($this->SMTPSecure) === 'tls') {
                $this->sendCommand($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Unable to enable TLS');
                }
                $this->sendCommand($socket, "EHLO " . $this->getHostname(), 250);
            }

            if ($this->SMTPAuth) {
                $this->sendCommand($socket, 'AUTH LOGIN', 334);
                $this->sendCommand($socket, base64_encode($this->Username), 334);
                $this->sendCommand($socket, base64_encode($this->Password), 235);
            }

            $this->sendCommand($socket, "MAIL FROM:<{$this->From}>", 250);
            foreach ($this->recipients as $recipient) {
                $this->sendCommand($socket, "RCPT TO:<{$recipient['address']}>", 250);
            }

            $this->sendCommand($socket, 'DATA', 354);
            $headers = $this->buildHeaders();
            $body = $this->Body ?: $this->AltBody;
            fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
            $this->expectResponse($socket, 250);
            $this->sendCommand($socket, 'QUIT', 221);
        } catch (\RuntimeException $e) {
            $this->errors[] = $e->getMessage();
            fclose($socket);
            return false;
        }

        fclose($socket);
        return true;
    }

    protected function buildHeaders(): string
    {
        $toList = array_map(
            fn($recipient) => $this->formatAddress($recipient['address'], $recipient['name']),
            $this->recipients
        );

        $headers = [];
        $headers[] = "From: {$this->formatAddress($this->From, $this->FromName)}";
        $headers[] = 'To: ' . implode(', ', $toList);
        $headers[] = "Subject: {$this->encodeHeader($this->Subject)}";
        $headers[] = "MIME-Version: 1.0";
        if ($this->isHTML) {
            $headers[] = "Content-Type: text/html; charset=\"{$this->CharSet}\"";
        } else {
            $headers[] = "Content-Type: text/plain; charset=\"{$this->CharSet}\"";
        }
        $headers[] = "Content-Transfer-Encoding: 8bit";
        $headers[] = "X-Mailer: PHPMailerLite/1.0";
        return implode("\r\n", $headers);
    }

    protected function formatAddress(string $address, string $name): string
    {
        $name = $name ? $this->encodeHeader($name) . ' ' : '';
        return "{$name}<{$address}>";
    }

    protected function encodeHeader(string $text): string
    {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    /**
     * @param resource $socket
     * @param int|array $expected
     * @return string
     * @throws \RuntimeException
     */
    protected function sendCommand($socket, string $command, int|array $expected): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expectResponse($socket, $expected);
    }

    /**
     * @param resource $socket
     * @param int|array $expectedCode
     * @return string
     * @throws \RuntimeException
     */
    protected function expectResponse($socket, int|array $expectedCode): string
    {
        $expected = (array)($expectedCode);
        $response = '';
        while (true) {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new \RuntimeException('SMTP connection closed unexpectedly');
            }
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int)substr(trim($response), 0, 3);
        if (!in_array($code, $expected, true)) {
            $this->errors[] = trim($response);
            throw new \RuntimeException("SMTP error ({$code}): " . trim($response));
        }
        return $response;
    }

    protected function getHostname(): string
    {
        $hostname = gethostname();
        if ($hostname === false) {
            $hostname = 'localhost';
        }
        return $hostname;
    }
}

