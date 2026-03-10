<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Event;
use App\Entity\Office;
use App\Entity\User;
use App\Security\Voter\EventVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class EventVoterTest extends TestCase
{
    private EventVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new EventVoter();
    }

    public function testSupportsEventAttributes(): void
    {
        $event = new Event();
        $user = $this->createUser(['ROLE_USER']);
        $token = $this->createToken($user);
        
        // Test supported attributes return ACCESS_GRANTED or ACCESS_DENIED (not ACCESS_ABSTAIN)
        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, 
            $this->voter->vote($token, $event, [EventVoter::VIEW]));
        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, 
            $this->voter->vote($token, null, [EventVoter::CREATE]));
        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, 
            $this->voter->vote($token, $event, [EventVoter::EDIT]));
        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, 
            $this->voter->vote($token, $event, [EventVoter::DELETE]));
        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, 
            $this->voter->vote($token, null, [EventVoter::OVERRIDE_CONFLICT]));
        
        // Test unsupported attributes return ACCESS_ABSTAIN
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, 
            $this->voter->vote($token, $event, ['INVALID_ATTRIBUTE']));
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, 
            $this->voter->vote($token, new \stdClass(), [EventVoter::VIEW]));
    }

    public function testAllUsersCanViewEvents(): void
    {
        $user = $this->createUser(['ROLE_PROVINCE']);
        $event = new Event();
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $event, [EventVoter::VIEW]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAllUsersCanCreateEvents(): void
    {
        $user = $this->createUser(['ROLE_PROVINCE']);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EventVoter::CREATE]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAdminCanEditAllEvents(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $event = $this->createEvent($this->createUser(['ROLE_PROVINCE']));
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $event, [EventVoter::EDIT]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testOsecCanEditAllEvents(): void
    {
        $osec = $this->createUser(['ROLE_OSEC']);
        $event = $this->createEvent($this->createUser(['ROLE_PROVINCE']));
        $token = $this->createToken($osec);

        $result = $this->voter->vote($token, $event, [EventVoter::EDIT]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testProvinceCanOnlyEditOwnEvents(): void
    {
        $province = $this->createUser(['ROLE_PROVINCE']);
        $otherUser = $this->createUser(['ROLE_PROVINCE']);
        
        // Can edit own event
        $ownEvent = $this->createEvent($province);
        $token = $this->createToken($province);
        $result = $this->voter->vote($token, $ownEvent, [EventVoter::EDIT]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);

        // Cannot edit other's event
        $otherEvent = $this->createEvent($otherUser);
        $result = $this->voter->vote($token, $otherEvent, [EventVoter::EDIT]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testOnlyAdminAndOsecCanOverrideConflicts(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $osec = $this->createUser(['ROLE_OSEC']);
        $eo = $this->createUser(['ROLE_EO']);
        $province = $this->createUser(['ROLE_PROVINCE']);

        // Admin can override
        $token = $this->createToken($admin);
        $result = $this->voter->vote($token, null, [EventVoter::OVERRIDE_CONFLICT]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);

        // OSEC can override
        $token = $this->createToken($osec);
        $result = $this->voter->vote($token, null, [EventVoter::OVERRIDE_CONFLICT]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);

        // EO cannot override
        $token = $this->createToken($eo);
        $result = $this->voter->vote($token, null, [EventVoter::OVERRIDE_CONFLICT]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);

        // Province cannot override
        $token = $this->createToken($province);
        $result = $this->voter->vote($token, null, [EventVoter::OVERRIDE_CONFLICT]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    private function createUser(array $roles): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setRoles($roles);
        
        // Use reflection to set ID for testing
        $reflection = new \ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, random_int(1, 1000));
        
        return $user;
    }

    private function createEvent(User $creator): Event
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setStartTime(new \DateTime());
        $event->setEndTime(new \DateTime('+1 hour'));
        $event->setCreator($creator);
        
        return $event;
    }

    private function createToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }
}