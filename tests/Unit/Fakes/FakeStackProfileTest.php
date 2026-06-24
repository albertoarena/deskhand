<?php

declare(strict_types=1);

use Deskhand\Tests\Fakes\FakeStackProfile;

it('records lifecycle calls and returns a configurable verify result', function () {
    $profile = new FakeStackProfile;
    $profile->verifyResult = false;

    $profile->generateAppKey('/wt');
    $profile->provisionStorage('/wt');
    $profile->migrate('/wt', 'acme_wt_feature-billing_test_1');
    $profile->seed('/wt');

    expect($profile->name())->toBe('fake')
        ->and($profile->appKeyGenerated)->toBeTrue()
        ->and($profile->storageProvisioned)->toBeTrue()
        ->and($profile->seeded)->toBeTrue()
        ->and($profile->migrated)->toBe(['acme_wt_feature-billing_test_1'])
        ->and($profile->verify('/wt'))->toBeFalse();
});
