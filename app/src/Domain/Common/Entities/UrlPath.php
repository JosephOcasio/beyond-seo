<?php

declare(strict_types=1);

namespace App\Domain\Common\Entities;

use DDD\Domain\Base\Entities\ValueObject;

class UrlPath extends ValueObject
{
    public string $path = '';

    public function __construct($path = '')
    {
        $this->path = self::normalizePath($path);
    }

    public static function normalizePath($path): string
    {
        if ($path) {
            if ($path[0] != '/' && strpos($path, 'http') === false) {
                $path = '/' . $path;
            }
            if ($path[strlen($path) - 1] == '/') {
                $path = substr($path, 0, strlen($path) - 1);
            }
        }
        return $path;
    }

    public function __toString(): string
    {
        return $this->path;
    }

}