<?php

use App\Domain\Identity\Totp;

it('accepts a valid RFC 6238 time based code', function (): void {
    $totp = new Totp;
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    expect($totp->verify($secret, '287082', 59))->toBeTrue()
        ->and($totp->verify($secret, '287083', 59))->toBeFalse();
});

it('creates a Base32 secret and provisioning URI', function (): void {
    $totp = new Totp;
    $secret = $totp->secret();

    expect($secret)->toMatch('/^[A-Z2-7]+$/')
        ->and($totp->provisioningUri($secret, 'owner@example.test'))->toContain('Lumenix', 'owner%40example.test');
});
