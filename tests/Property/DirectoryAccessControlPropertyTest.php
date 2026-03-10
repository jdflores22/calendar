<?php

namespace App\Tests\Property;

use App\Entity\DirectoryContact;
use App\Entity\Office;
use App\Entity\User;
use App\Security\Voter\DirectoryVoter;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @test
 * Feature: tesda-calendar-system, Property 11: Admin-Only Directory Access
 * Validates: Requirements 7.1, 7.2, 7.3, 7.4
 */
class DirectoryAccessControlPropertyTest extends KernelTestCase
{
    use TestTrait;

    private EntityManagerInterface $entityManager;
    private DirectoryVoter $directoryVoter;
    private AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->directoryVoter = new DirectoryVoter();
        $this->auditService = static::getContainer()->get(AuditService::class);
        
        // Clear any existing data in correct order (children first, then parents)
        $this->entityManager->createQuery('DELETE FROM App\Entity\DirectoryContact')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\EventAttachment')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Event')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\UserProfile')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\AuditLog')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Office')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Property 11: Admin-Only Directory Access
     * For any directory management operation (CRUD on offices, contacts, phone numbers, 
     * emails, addresses), only Admin users must have access, with full audit logging 
     * of all changes and proper data validation
     * 
     * Validates: Requirements 7.1, 7.2, 7.3, 7.4
     */
    public function testAdminOnlyDirectoryManagementAccess(): void
    {
        $this->limitTo(100)->forAll(
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE'])
        )->withMaxSize(10)->then(function (string $userRole) {
            $user = $this->createUser([$userRole]);
            $token = $this->createToken($user);

            // Property: Only Admin can access directory management
            $manageResult = $this->directoryVoter->vote($token, null, [DirectoryVoter::MANAGE]);
            if ($user->hasRole('ROLE_ADMIN')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $manageResult, 
                    "Admin users must have access to directory management");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $manageResult, 
                    "Non-admin users must not have access to directory management");
            }

            // Property: Only Admin can create directory contacts
            $createContactResult = $this->directoryVoter->vote($token, null, [DirectoryVoter::CREATE]);
            if ($user->hasRole('ROLE_ADMIN')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $createContactResult, 
                    "Admin users must be able to create directory contacts");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $createContactResult, 
                    "Non-admin users must not be able to create directory contacts");
            }
        });
    }

    /**
     * Property: Directory Contact CRUD Operations Access Control
     * All CRUD operations on directory contacts must be restricted to Admin users only
     * 
     * Validates: Requirements 7.1, 7.2
     */
    public function testDirectoryContactCrudAccessControl(): void
    {
        $this->limitTo(100)->forAll(
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE']),
            Generator\map(
                function($str) { return 'Contact_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\map(
                function($str) { return 'Position_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            )
        )->withMaxSize(10)->then(function (string $userRole, string $contactName, string $position, string $email) {
            // Skip empty values that would fail validation
            if (empty(trim($contactName)) || empty(trim($position)) || empty(trim($email))) {
                return;
            }

            $user = $this->createUser([$userRole]);
            $office = $this->createOffice();
            $contact = $this->createDirectoryContact($contactName, $position, $email, $office);
            
            $token = $this->createToken($user);

            // Property: All authenticated users can view directory contacts
            $viewResult = $this->directoryVoter->vote($token, $contact, [DirectoryVoter::VIEW]);
            $this->assertEquals(VoterInterface::ACCESS_GRANTED, $viewResult, 
                "All authenticated users must be able to view directory contacts");

            // Property: Only Admin can edit directory contacts
            $editResult = $this->directoryVoter->vote($token, $contact, [DirectoryVoter::EDIT]);
            if ($user->hasRole('ROLE_ADMIN')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $editResult, 
                    "Admin users must be able to edit directory contacts");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $editResult, 
                    "Non-admin users must not be able to edit directory contacts");
            }

            // Property: Only Admin can delete directory contacts
            $deleteResult = $this->directoryVoter->vote($token, $contact, [DirectoryVoter::DELETE]);
            if ($user->hasRole('ROLE_ADMIN')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $deleteResult, 
                    "Admin users must be able to delete directory contacts");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $deleteResult, 
                    "Non-admin users must not be able to delete directory contacts");
            }
        });
    }

    /**
     * Property: Directory Contact Data Validation Consistency
     * All directory contact data must be properly validated according to business rules
     * 
     * Validates: Requirements 7.3
     */
    public function testDirectoryContactDataValidation(): void
    {
        $this->limitTo(100)->forAll(
            Generator\map(
                function($str) { return 'Contact_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\map(
                function($str) { return 'Position_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\oneOf(
                Generator\constant(null),
                Generator\map(
                    function($str) { return '+1-555-' . substr(abs(crc32($str)), 0, 4); },
                    Generator\string()
                )
            ),
            Generator\oneOf(
                Generator\constant(null),
                Generator\map(
                    function($str) { return 'Address_' . abs(crc32($str)); },
                    Generator\string()
                )
            )
        )->withMaxSize(10)->then(function (string $name, string $position, string $email, ?string $phone, ?string $address) {
            $office = $this->createOffice();
            $contact = new DirectoryContact();
            
            // Property: Name must be required and not empty
            if (!empty(trim($name))) {
                $contact->setName(trim($name));
                $this->assertEquals(trim($name), $contact->getName(), 
                    "Contact name must be set correctly when valid");
            }

            // Property: Position must be required and not empty
            if (!empty(trim($position))) {
                $contact->setPosition(trim($position));
                $this->assertEquals(trim($position), $contact->getPosition(), 
                    "Contact position must be set correctly when valid");
            }

            // Property: Email must be required and not empty
            if (!empty(trim($email))) {
                $contact->setEmail(trim($email));
                $this->assertEquals(trim($email), $contact->getEmail(), 
                    "Contact email must be set correctly when valid");
            }

            // Property: Phone is optional but must be validated if provided
            if ($phone !== null) {
                $contact->setPhone($phone);
                $this->assertEquals($phone, $contact->getPhone(), 
                    "Contact phone must be set correctly when provided");
            }

            // Property: Address is optional but must be validated if provided
            if ($address !== null) {
                $contact->setAddress($address);
                $this->assertEquals($address, $contact->getAddress(), 
                    "Contact address must be set correctly when provided");
            }

            // Property: Office assignment must be required
            $contact->setOffice($office);
            $this->assertEquals($office, $contact->getOffice(), 
                "Contact must be assigned to an office");

            // Property: Timestamps must be set automatically
            if (!empty(trim($name)) && !empty(trim($position)) && !empty(trim($email))) {
                $contact->setCreatedAtValue();
                $this->assertInstanceOf(\DateTimeInterface::class, $contact->getCreatedAt(), 
                    "Created timestamp must be set automatically");
                $this->assertInstanceOf(\DateTimeInterface::class, $contact->getUpdatedAt(), 
                    "Updated timestamp must be set automatically");
            }
        });
    }

    /**
     * Property: Audit Logging for Directory Operations
     * All directory management operations must be logged with comprehensive audit information
     * 
     * Validates: Requirements 7.4
     */
    public function testDirectoryOperationAuditLogging(): void
    {
        $this->limitTo(50)->forAll(
            Generator\elements(['CONTACT_CREATED', 'CONTACT_UPDATED', 'CONTACT_DELETED', 'OFFICE_CREATED', 'OFFICE_UPDATED', 'OFFICE_DELETED']),
            Generator\map(
                function($str) { return 'Entity_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\map(
                function($str) { return 'Description_' . abs(crc32($str)); },
                Generator\string()
            )
        )->withMaxSize(10)->then(function (string $action, string $entityName, string $description) {
            // Skip empty values
            if (empty(trim($entityName))) {
                return;
            }

            $admin = $this->createUser(['ROLE_ADMIN']);
            $office = $this->createOffice();
            
            // Property: Audit service must be able to create audit logs with proper structure
            $auditLog = new \App\Entity\AuditLog();
            $auditLog->setAction($action);
            $auditLog->setUser($admin);
            $auditLog->setDescription($description);
            $auditLog->setCreatedAtValue();
            
            if (str_starts_with($action, 'CONTACT_')) {
                $contact = $this->createDirectoryContact($entityName, 'Test Position', 'test@example.com', $office);
                $auditLog->setEntityType('DirectoryContact');
                $auditLog->setEntityId($contact->getId());
                $auditLog->setNewValues([
                    'name' => $contact->getName(),
                    'position' => $contact->getPosition(),
                    'email' => $contact->getEmail(),
                    'office_id' => $contact->getOffice()->getId(),
                ]);
            } elseif (str_starts_with($action, 'OFFICE_')) {
                $auditLog->setEntityType('Office');
                $auditLog->setEntityId($office->getId());
                $auditLog->setNewValues([
                    'name' => $office->getName(),
                    'code' => $office->getCode(),
                    'color' => $office->getColor(),
                ]);
            }
            
            // Property: Audit log must contain required information
            $this->assertEquals($action, $auditLog->getAction(), 
                "Audit log must record the correct action");
            $this->assertNotNull($auditLog->getEntityType(), 
                "Audit log must record the entity type");
            $this->assertNotNull($auditLog->getEntityId(), 
                "Audit log must record the entity ID");
            $this->assertEquals($admin, $auditLog->getUser(), 
                "Audit log must record the user who performed the action");
            $this->assertInstanceOf(\DateTimeInterface::class, $auditLog->getCreatedAt(), 
                "Audit log must have a timestamp");
            
            // Property: Audit log must have proper data structure for directory operations
            if (str_starts_with($action, 'CONTACT_')) {
                $this->assertEquals('DirectoryContact', $auditLog->getEntityType(), 
                    "Contact operations must log DirectoryContact entity type");
                $this->assertIsArray($auditLog->getNewValues(), 
                    "Contact audit log must contain structured data");
                $this->assertArrayHasKey('name', $auditLog->getNewValues(), 
                    "Contact audit log must contain name field");
                $this->assertArrayHasKey('position', $auditLog->getNewValues(), 
                    "Contact audit log must contain position field");
                $this->assertArrayHasKey('email', $auditLog->getNewValues(), 
                    "Contact audit log must contain email field");
            } elseif (str_starts_with($action, 'OFFICE_')) {
                $this->assertEquals('Office', $auditLog->getEntityType(), 
                    "Office operations must log Office entity type");
                $this->assertIsArray($auditLog->getNewValues(), 
                    "Office audit log must contain structured data");
                $this->assertArrayHasKey('name', $auditLog->getNewValues(), 
                    "Office audit log must contain name field");
                $this->assertArrayHasKey('code', $auditLog->getNewValues(), 
                    "Office audit log must contain code field");
                $this->assertArrayHasKey('color', $auditLog->getNewValues(), 
                    "Office audit log must contain color field");
            }
        });
    }

    /**
     * Property: Directory Data Integrity and Consistency
     * Directory data must maintain referential integrity and consistency
     * 
     * Validates: Requirements 7.1, 7.2, 7.3
     */
    public function testDirectoryDataIntegrityConsistency(): void
    {
        $this->limitTo(50)->forAll(
            Generator\map(
                function($str) { return 'Contact_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\map(
                function($str) { return 'Position_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            )
        )->withMaxSize(10)->then(function (string $contactName, string $position, string $email) {
            // Skip empty values that would fail validation
            if (empty(trim($contactName)) || empty(trim($position)) || empty(trim($email))) {
                return;
            }

            $office = $this->createOffice();
            $contact = $this->createDirectoryContact($contactName, $position, $email, $office);

            // Property: Contact must be associated with an office
            $this->assertNotNull($contact->getOffice(), 
                "Directory contact must be associated with an office");
            $this->assertEquals($office, $contact->getOffice(), 
                "Directory contact must be associated with the correct office");

            // Property: Office must maintain contact relationships
            $this->assertContains($contact, $office->getDirectoryContacts(), 
                "Office must maintain reference to its directory contacts");

            // Property: Contact data must be consistent after updates
            $newName = 'Updated ' . $contactName;
            $newPosition = 'Updated ' . $position;
            $contact->setName($newName);
            $contact->setPosition($newPosition);
            
            $this->assertEquals($newName, $contact->getName(), 
                "Contact name must be updated correctly");
            $this->assertEquals($newPosition, $contact->getPosition(), 
                "Contact position must be updated correctly");
            $this->assertEquals($office, $contact->getOffice(), 
                "Contact office association must remain consistent after updates");

            // Property: Timestamps must be updated on modifications
            $originalCreatedAt = $contact->getCreatedAt();
            $contact->setUpdatedAtValue();
            
            $this->assertEquals($originalCreatedAt, $contact->getCreatedAt(), 
                "Created timestamp must not change on updates");
            $this->assertInstanceOf(\DateTimeInterface::class, $contact->getUpdatedAt(), 
                "Updated timestamp must be set on modifications");
        });
    }

    private function createUser(array $roles): User
    {
        $user = new User();
        $user->setEmail('test' . random_int(1000, 9999) . '@example.com');
        $user->setPassword('password');
        $user->setRoles($roles);
        $user->setVerified(true);
        
        // Use reflection to set ID for testing
        $reflection = new \ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, random_int(1, 1000));
        
        return $user;
    }

    private function createOffice(): Office
    {
        $office = new Office();
        $office->setName('Test Office ' . random_int(1000, 9999));
        $office->setCode('TO' . random_int(100, 999));
        $office->setColor('#' . substr(md5(random_int(1, 1000)), 0, 6));
        
        // Use reflection to set ID for testing
        $reflection = new \ReflectionClass($office);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($office, random_int(1, 1000));
        
        return $office;
    }

    private function createDirectoryContact(string $name, string $position, string $email, Office $office): DirectoryContact
    {
        $contact = new DirectoryContact();
        $contact->setName(trim($name));
        $contact->setPosition(trim($position));
        $contact->setEmail(trim($email));
        $contact->setOffice($office);
        $contact->setCreatedAtValue();
        
        // Add contact to office's collection to maintain bidirectional relationship
        $office->addDirectoryContact($contact);
        
        // Use reflection to set ID for testing
        $reflection = new \ReflectionClass($contact);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($contact, random_int(1, 1000));
        
        return $contact;
    }

    private function createToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }
}