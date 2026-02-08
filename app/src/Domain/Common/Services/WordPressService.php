<?php
declare(strict_types=1);

namespace App\Domain\Common\Services;

/**
 * Class WordPressLoaderService
 * @package App\Domain\Common\Services
 */
class WordPressService
{
    /**
     * Load WordPress Core functions
     */
    public static function init(): void
    {
        if(defined(ABSPATH)) {
            require_once ABSPATH . 'wp-load.php';
            require_once ABSPATH . 'wp-includes/functions.php';
            require_once ABSPATH . 'wp-includes/pluggable.php';
        }
    }
}