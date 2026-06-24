<?php

declare(strict_types=1);

use Deskhand\Console\Application;

it('boots with the deskhand name and a version', function () {
    $app = new Application;

    expect($app->getName())->toBe('deskhand')
        ->and($app->getVersion())->not->toBe('');
});
