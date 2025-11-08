<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/application',
        __DIR__ . '/modules',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/application/cache',
        __DIR__ . '/application/logs',
        __DIR__ . '/application/vendor',
        __DIR__ . '/modules/*/vendor',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80,
        SetList::TYPE_DECLARATION,
    ]);
};
