<?php

declare(strict_types=1);

namespace App\Dto\Health;

class HealthResponse
{
    private ?string $message = null;

    public static function withMessage(string $message): self
    {
        $dto = new self();
        $dto->setMessage($message);

        return $dto;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
