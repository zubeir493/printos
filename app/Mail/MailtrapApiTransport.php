<?php

namespace App\Mail;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MailtrapApiTransport extends AbstractTransport
{
    private HttpClientInterface $client;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://send.api.mailtrap.io/api/send',
        ?HttpClientInterface $client = null,
    ) {
        parent::__construct();

        if (trim($this->apiKey) === '') {
            throw new TransportException('MAILTRAP_API_KEY is not configured.');
        }

        $this->client = $client ?? HttpClient::create();
    }

    protected function doSend(SentMessage $message): void
    {
        $originalMessage = $message->getOriginalMessage();

        if (! $originalMessage instanceof Email) {
            throw new TransportException('Mailtrap API transport can only send Symfony Email messages.');
        }

        $response = $this->client->request('POST', $this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => $this->payloadFromEmail($originalMessage),
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getContent(false);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new TransportException("Mailtrap API returned HTTP {$statusCode}: {$body}");
        }

        $decoded = json_decode($body, true);
        $messageId = $decoded['message_ids'][0] ?? $decoded['message_id'] ?? null;

        if (is_string($messageId) && $messageId !== '') {
            $message->setMessageId($messageId);
        }
    }

    private function payloadFromEmail(Email $email): array
    {
        $payload = [
            'from' => $this->address($email->getFrom()[0] ?? null),
            'to' => $this->addresses($email->getTo()),
            'subject' => $email->getSubject() ?? '',
        ];

        if ($cc = $this->addresses($email->getCc())) {
            $payload['cc'] = $cc;
        }

        if ($bcc = $this->addresses($email->getBcc())) {
            $payload['bcc'] = $bcc;
        }

        if ($replyTo = $this->addresses($email->getReplyTo())) {
            $payload['reply_to'] = $replyTo[0];
        }

        if ($email->getHtmlBody()) {
            $payload['html'] = $email->getHtmlBody();
        }

        if ($email->getTextBody()) {
            $payload['text'] = $email->getTextBody();
        }

        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                'content' => base64_encode($attachment->getBody()),
                'filename' => $attachment->getFilename() ?? 'attachment',
                'type' => $attachment->getContentType(),
                'disposition' => 'attachment',
            ];
        }

        if ($attachments) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    /**
     * @param  array<int, Address>  $addresses
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_filter(array_map(fn (Address $address) => $this->address($address), $addresses)));
    }

    private function address(?Address $address): ?array
    {
        if (! $address) {
            return null;
        }

        $mailtrapAddress = ['email' => $address->getAddress()];

        if ($address->getName() !== '') {
            $mailtrapAddress['name'] = $address->getName();
        }

        return $mailtrapAddress;
    }

    public function __toString(): string
    {
        return 'mailtrap-api';
    }
}
