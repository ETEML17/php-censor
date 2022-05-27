<?php

declare(strict_types=1);

namespace Tests\PHPCensor\Security\Authentication\UserProvider;

use PHPCensor\Model\User;
use PHPCensor\Security\Authentication\UserProvider\Internal;
use PHPCensor\StoreRegistry;
use PHPUnit\Framework\TestCase;
use PHPCensor\Common\Application\ConfigurationInterface;

class InternalTest extends TestCase
{
    private Internal $provider;
    private StoreRegistry $storeRegistry;

    protected function setUp(): void
    {
        $configuration   = $this->getMockBuilder(ConfigurationInterface::class)->getMock();
        $databaseManager = $this
            ->getMockBuilder('PHPCensor\DatabaseManager')
            ->setConstructorArgs([$configuration])
            ->getMock();
        $this->storeRegistry = $this
            ->getMockBuilder('PHPCensor\StoreRegistry')
            ->setConstructorArgs([$databaseManager])
            ->getMock();

        $this->provider = new Internal($this->storeRegistry, 'internal', [
            'type' => 'internal',
        ]);
    }

    public function testVerifyPassword(): void
    {
        $user = new User($this->storeRegistry);
        $password = 'bla';
        $user->setHash(password_hash($password, PASSWORD_DEFAULT));

        self::assertTrue($this->provider->verifyPassword($user, $password));
    }

    public function testVerifyInvalidPassword(): void
    {
        $user = new User($this->storeRegistry);
        $password = 'foo';
        $user->setHash(\password_hash($password, PASSWORD_DEFAULT));

        self::assertFalse($this->provider->verifyPassword($user, 'bar'));
    }

    public function testProvisionUser(): void
    {
        self::assertNull($this->provider->provisionUser('john@doe.com'));
    }
}
