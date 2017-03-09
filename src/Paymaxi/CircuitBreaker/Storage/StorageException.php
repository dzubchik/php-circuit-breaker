<?php
declare(strict_types = 1);


namespace Paymaxi\Component\CircuitBreaker\Storage;

/**
 * Thrown when storage handler class can not be used any more.
 * Means there is a serious error like handler can not connect to
 * the storage or out of space or underlying PHP extension is missing etc.
 * 
 * @package Paymaxi\Component\CircuitBreaker\Components
 */
class StorageException extends \Exception {
    
}