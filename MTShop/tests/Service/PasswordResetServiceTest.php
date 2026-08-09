<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\PasswordResetService;
use PHPUnit\Framework\TestCase;

class PasswordResetServiceTest extends TestCase
{
    public function testGenerateTokenAndApprovalWorkflow(): void
    {
        $service = new PasswordResetService();
        $user = new User();
        $user->setEmail('user@example.com');

        $token = $service->createResetToken($user);

        $this->assertNotEmpty($token);
        $this->assertFalse($service->isApproved($user, $token));

        $service->approveReset($user, $token);

        $this->assertTrue($service->isApproved($user, $token));
    }
}
