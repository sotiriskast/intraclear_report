<?php

namespace App\Enums;

/**
 * Enum representing all possible transaction statuses in the payment system
 */
enum TransactionStatusEnum: string
{
    /**
     * Approved - transaction was successfully completed
     */
    case APPROVED = 'APPROVED';

    /**
     * Declined - transaction was rejected
     */
    case DECLINED = 'DECLINED';

    /**
     * Processing - transaction is currently in progress
     */
    case PROCESSING = 'PROCESSING';

    /**
     * Cancelled - transaction was cancelled before completion
     */
    case CANCELLED = 'CANCELLED';

    /**
     * Pending - transaction is awaiting further action
     */
    case PENDING = 'PENDING';

    /**
     * Error - transaction encountered an error during processing
     */
    case ERROR = 'ERROR';

    /**
     * Check if the status is final (not subject to further changes)
     *
     * @return bool True if status is final
     */
    public function isFinal(): bool
    {
        return match($this) {
            self::APPROVED, self::DECLINED, self::CANCELLED, self::ERROR => true,
            self::PROCESSING, self::PENDING => false
        };
    }

    /**
     * Check if the status is successful
     *
     * @return bool True if status indicates success
     */
    public function isSuccessful(): bool
    {
        return $this === self::APPROVED;
    }
}
