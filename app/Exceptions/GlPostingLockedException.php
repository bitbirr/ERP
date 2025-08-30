<?php

namespace App\Exceptions;

use Exception;

class GlPostingLockedException extends Exception
{
    protected $journalId;
    protected $lockTimeout;

    public function __construct(string $message = '', string $journalId = '', int $lockTimeout = 0, int $code = 0, ?Throwable $previous = null)
    {
        $defaultMessage = $message ?: "Journal {$journalId} is currently locked for posting. Please try again later.";
        parent::__construct($defaultMessage, $code, $previous);
        $this->journalId = $journalId;
        $this->lockTimeout = $lockTimeout;
    }

    public function getJournalId(): string
    {
        return $this->journalId;
    }

    public function getLockTimeout(): int
    {
        return $this->lockTimeout;
    }
}