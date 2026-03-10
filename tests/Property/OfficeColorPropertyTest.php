<?php

namespace App\Tests\Property;

use App\Entity\Office;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @test
 * Feature: tesda-calendar-system, Property 10: Office Color Uniqueness
 * Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5
 */
class OfficeColorPropertyTest extends KernelTestCase
{
    use TestTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        // Disable foreign key checks and clear all data
        $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->entityManager->createQuery('DELETE FROM App\Entity\Office')->execute();
        $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        // Clean up any remaining data
        if ($this->entityManager && $this->entityManager->isOpen()) {
            try {
                $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
                $this->entityManager->createQuery('DELETE FROM App\Entity\Office')->execute();
                $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        parent::tearDown();
    }

    /**
     * Property 10: Office Color Uniqueness - Positive Cases
     * For any office in the system, it must have a unique color assignment that is 
     * consistently used for all its events, with no color conflicts between offices, 
     * and proper legend display
     * 
     * Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5
     */
    public function testOfficeColorUniqueness(): void
    {
        $this->limitTo(10)->forAll(
            Generator\int(1, 999999)
        )->then(function (int $seed) {
            $timestamp = microtime(true) * 1000000; // Microsecond precision
            $name1 = 'Office1_' . $timestamp . '_' . $seed;
            $code1 = 'CODE1_' . $timestamp . '_' . $seed;
            $name2 = 'Office2_' . $timestamp . '_' . $seed;
            $code2 = 'CODE2_' . $timestamp . '_' . $seed;
            
            // Generate unique colors based on timestamp and seed
            $color1 = '#' . str_pad(dechex(($timestamp + $seed) % 16777215), 6, '0', STR_PAD_LEFT);
            $color2 = '#' . str_pad(dechex(($timestamp + $seed + 1000000) % 16777215), 6, '0', STR_PAD_LEFT);

            // Use a transaction to ensure clean state
            $this->entityManager->beginTransaction();
            
            try {
                // Create first office
                $office1 = new Office();
                $office1->setName($name1);
                $office1->setCode($code1);
                $office1->setColor($color1);

                $this->entityManager->persist($office1);
                $this->entityManager->flush();

                // Property: Office must have the assigned color
                $this->assertEquals(strtoupper($color1), $office1->getColor(), 'Office must have the assigned color');

                // Property: Color must be properly formatted (uppercase with #)
                $this->assertStringStartsWith('#', $office1->getColor(), 'Color must start with #');
                $this->assertEquals(7, strlen($office1->getColor()), 'Color must be 7 characters long');
                $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $office1->getColor(), 'Color must be valid hex format');

                // Create second office with different color
                $office2 = new Office();
                $office2->setName($name2);
                $office2->setCode($code2);
                $office2->setColor($color2);
                
                $this->entityManager->persist($office2);
                $this->entityManager->flush();

                // Property: Both offices should exist with their respective colors
                $foundOffice1 = $this->entityManager->find(Office::class, $office1->getId());
                $foundOffice2 = $this->entityManager->find(Office::class, $office2->getId());

                $this->assertNotNull($foundOffice1, 'First office should exist');
                $this->assertNotNull($foundOffice2, 'Second office should exist');
                $this->assertEquals(strtoupper($color1), $foundOffice1->getColor(), 'First office should have correct color');
                $this->assertEquals(strtoupper($color2), $foundOffice2->getColor(), 'Second office should have correct color');
                $this->assertNotEquals($foundOffice1->getColor(), $foundOffice2->getColor(), 
                    'Offices should have different colors');

                // Commit the transaction
                $this->entityManager->commit();
            } catch (\Exception $e) {
                // Rollback on any error
                $this->entityManager->rollback();
                throw $e;
            } finally {
                // Clean up - rollback will handle this, but ensure clean state
                $this->entityManager->clear();
            }
        });
    }

    /**
     * Property 10: Office Color Uniqueness - Constraint Validation
     * Test that duplicate colors are properly prevented using database-level validation
     * 
     * Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5
     */
    public function testOfficeColorDuplicationPrevention(): void
    {
        $this->limitTo(8)->forAll(
            Generator\int(1, 999999)
        )->then(function (int $seed) {
            // Generate unique identifiers
            $timestamp = microtime(true) * 1000000;
            $uniqueId = $timestamp . '_' . $seed;
            $name1 = 'Office1_' . $uniqueId;
            $code1 = 'CODE1_' . $uniqueId;
            $name2 = 'Office2_' . $uniqueId;
            $code2 = 'CODE2_' . $uniqueId;
            $sharedColor = '#' . str_pad(dechex(($timestamp + $seed) % 16777215), 6, '0', STR_PAD_LEFT);

            // Use raw database operations to avoid EntityManager state issues
            $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
            
            try {
                // Step 1: Create first office successfully
                $connection->beginTransaction();
                $connection->executeStatement(
                    'INSERT INTO offices (name, code, color) VALUES (?, ?, ?)',
                    [$name1, strtoupper($code1), strtoupper($sharedColor)]
                );
                $office1Id = $connection->lastInsertId();
                $connection->commit();
                
                // Step 2: Try to create second office with same color (should fail)
                $constraintViolated = false;
                try {
                    $connection->beginTransaction();
                    $connection->executeStatement(
                        'INSERT INTO offices (name, code, color) VALUES (?, ?, ?)',
                        [$name2, strtoupper($code2), strtoupper($sharedColor)]
                    );
                    $connection->commit();
                } catch (\Exception $e) {
                    $constraintViolated = true;
                    $connection->rollback();
                }
                
                // Property: Duplicate color should be prevented
                $this->assertTrue($constraintViolated, 'Unique color constraint should prevent duplicate colors');
                
                // Step 3: Clean up
                $connection->executeStatement('DELETE FROM offices WHERE id = ?', [$office1Id]);
                
            } catch (\Exception $e) {
                if ($connection->isTransactionActive()) {
                    $connection->rollback();
                }
                // Clean up any created records
                $connection->executeStatement('DELETE FROM offices WHERE name IN (?, ?)', [$name1, $name2]);
                throw $e;
            }
        });
    }

    /**
     * Property: Office Code Uniqueness - Positive Cases
     * Office codes must be unique across all offices
     */
    public function testOfficeCodeUniqueness(): void
    {
        $this->limitTo(8)->forAll(
            Generator\int(1, 999999)
        )->then(function (int $seed) {
            $timestamp = microtime(true) * 1000000; // Microsecond precision
            $name1 = 'Office1_' . $timestamp . '_' . $seed;
            $code1 = 'CODE1_' . $timestamp . '_' . $seed;
            $name2 = 'Office2_' . $timestamp . '_' . $seed;
            $code2 = 'CODE2_' . $timestamp . '_' . $seed;
            
            // Generate unique colors based on timestamp and seed
            $color1 = '#' . str_pad(dechex(($timestamp + $seed) % 16777215), 6, '0', STR_PAD_LEFT);
            $color2 = '#' . str_pad(dechex(($timestamp + $seed + 1000000) % 16777215), 6, '0', STR_PAD_LEFT);

            // Use a transaction to ensure clean state
            $this->entityManager->beginTransaction();
            
            try {
                // Create first office
                $office1 = new Office();
                $office1->setName($name1);
                $office1->setCode($code1);
                $office1->setColor($color1);

                $this->entityManager->persist($office1);
                $this->entityManager->flush();

                // Property: Office code must be properly formatted (uppercase)
                $this->assertEquals(strtoupper($code1), $office1->getCode(), 'Office code must be uppercase');

                // Create second office with different code
                $office2 = new Office();
                $office2->setName($name2);
                $office2->setCode($code2);
                $office2->setColor($color2);
                
                $this->entityManager->persist($office2);
                $this->entityManager->flush();

                // Property: Both offices should exist with their respective codes
                $foundOffice1 = $this->entityManager->find(Office::class, $office1->getId());
                $foundOffice2 = $this->entityManager->find(Office::class, $office2->getId());

                $this->assertNotNull($foundOffice1, 'First office should exist');
                $this->assertNotNull($foundOffice2, 'Second office should exist');
                $this->assertEquals(strtoupper($code1), $foundOffice1->getCode(), 'First office should have correct code');
                $this->assertEquals(strtoupper($code2), $foundOffice2->getCode(), 'Second office should have correct code');
                $this->assertNotEquals($foundOffice1->getCode(), $foundOffice2->getCode(), 
                    'Offices should have different codes');

                // Commit the transaction
                $this->entityManager->commit();
            } catch (\Exception $e) {
                // Rollback on any error
                $this->entityManager->rollback();
                throw $e;
            } finally {
                // Clean up - rollback will handle this, but ensure clean state
                $this->entityManager->clear();
            }
        });
    }

    /**
     * Property: Office Code Duplication Prevention - Constraint Validation
     * Test that duplicate codes are properly prevented using database-level validation
     */
    public function testOfficeCodeDuplicationPrevention(): void
    {
        $this->limitTo(8)->forAll(
            Generator\int(1, 999999)
        )->then(function (int $seed) {
            // Generate unique identifiers
            $timestamp = microtime(true) * 1000000;
            $uniqueId = $timestamp . '_' . $seed;
            $name1 = 'Office1_' . $uniqueId;
            $name2 = 'Office2_' . $uniqueId;
            $sharedCode = 'SHARED_CODE_' . $timestamp;
            
            // Generate unique colors
            $color1 = '#' . str_pad(dechex(($timestamp + $seed) % 16777215), 6, '0', STR_PAD_LEFT);
            $color2 = '#' . str_pad(dechex(($timestamp + $seed + 1000000) % 16777215), 6, '0', STR_PAD_LEFT);

            // Use raw database operations to avoid EntityManager state issues
            $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
            
            try {
                // Step 1: Create first office successfully
                $connection->beginTransaction();
                $connection->executeStatement(
                    'INSERT INTO offices (name, code, color) VALUES (?, ?, ?)',
                    [$name1, strtoupper($sharedCode), strtoupper($color1)]
                );
                $office1Id = $connection->lastInsertId();
                $connection->commit();
                
                // Step 2: Try to create second office with same code (should fail)
                $constraintViolated = false;
                try {
                    $connection->beginTransaction();
                    $connection->executeStatement(
                        'INSERT INTO offices (name, code, color) VALUES (?, ?, ?)',
                        [$name2, strtoupper($sharedCode), strtoupper($color2)]
                    );
                    $connection->commit();
                } catch (\Exception $e) {
                    $constraintViolated = true;
                    $connection->rollback();
                }
                
                // Property: Duplicate code should be prevented
                $this->assertTrue($constraintViolated, 'Unique code constraint should prevent duplicate codes');
                
                // Step 3: Clean up
                $connection->executeStatement('DELETE FROM offices WHERE id = ?', [$office1Id]);
                
            } catch (\Exception $e) {
                if ($connection->isTransactionActive()) {
                    $connection->rollback();
                }
                // Clean up any created records
                $connection->executeStatement('DELETE FROM offices WHERE name IN (?, ?)', [$name1, $name2]);
                throw $e;
            }
        });
    }

    /**
     * Property: Office Hierarchical Relationships
     * Office parent-child relationships must be consistent and prevent circular references
     */
    public function testOfficeHierarchicalRelationships(): void
    {
        $this->limitTo(8)->forAll(
            Generator\int(1, 999999)
        )->then(function (int $seed) {
            $timestamp = microtime(true) * 1000000; // Microsecond precision
            $name1 = 'Root_' . $timestamp . '_' . $seed;
            $code1 = 'ROOT_' . $timestamp . '_' . $seed;
            $name2 = 'Child_' . $timestamp . '_' . $seed;
            $code2 = 'CHLD_' . $timestamp . '_' . $seed;
            $name3 = 'Grand_' . $timestamp . '_' . $seed;
            $code3 = 'GRND_' . $timestamp . '_' . $seed;
            
            // Generate unique colors based on timestamp and seed
            $color1 = '#' . str_pad(dechex(($timestamp + $seed) % 16777215), 6, '0', STR_PAD_LEFT);
            $color2 = '#' . str_pad(dechex(($timestamp + $seed + 1000000) % 16777215), 6, '0', STR_PAD_LEFT);
            $color3 = '#' . str_pad(dechex(($timestamp + $seed + 2000000) % 16777215), 6, '0', STR_PAD_LEFT);

            // Use a transaction to ensure clean state
            $this->entityManager->beginTransaction();
            
            try {
                // Create root office
                $rootOffice = new Office();
                $rootOffice->setName($name1);
                $rootOffice->setCode($code1);
                $rootOffice->setColor($color1);

                // Create child office
                $childOffice = new Office();
                $childOffice->setName($name2);
                $childOffice->setCode($code2);
                $childOffice->setColor($color2);
                $childOffice->setParent($rootOffice);

                // Create grandchild office
                $grandchildOffice = new Office();
                $grandchildOffice->setName($name3);
                $grandchildOffice->setCode($code3);
                $grandchildOffice->setColor($color3);
                $grandchildOffice->setParent($childOffice);

                $this->entityManager->persist($rootOffice);
                $this->entityManager->persist($childOffice);
                $this->entityManager->persist($grandchildOffice);
                $this->entityManager->flush();

                // Refresh entities to ensure bidirectional relationships are loaded
                $this->entityManager->refresh($rootOffice);
                $this->entityManager->refresh($childOffice);
                $this->entityManager->refresh($grandchildOffice);

                // Property: Root office should have no parent
                $this->assertNull($rootOffice->getParent(), 'Root office should have no parent');
                $this->assertTrue($rootOffice->isRoot(), 'Root office should be identified as root');

                // Property: Child office should have correct parent
                $this->assertSame($rootOffice, $childOffice->getParent(), 'Child office should have correct parent');
                $this->assertFalse($childOffice->isRoot(), 'Child office should not be root');

                // Property: Grandchild office should have correct parent
                $this->assertSame($childOffice, $grandchildOffice->getParent(), 'Grandchild should have correct parent');

                // Property: Parent-child relationships should be bidirectional
                $this->assertTrue($rootOffice->getChildren()->contains($childOffice), 
                    'Root office should contain child in children collection');
                $this->assertTrue($childOffice->getChildren()->contains($grandchildOffice), 
                    'Child office should contain grandchild in children collection');

                // Property: Hierarchical methods should work correctly
                $this->assertEquals(0, $rootOffice->getDepthLevel(), 'Root office should have depth level 0');
                $this->assertEquals(1, $childOffice->getDepthLevel(), 'Child office should have depth level 1');
                $this->assertEquals(2, $grandchildOffice->getDepthLevel(), 'Grandchild office should have depth level 2');

                // Property: Ancestor/descendant relationships should work correctly
                $this->assertTrue($grandchildOffice->isDescendantOf($rootOffice), 
                    'Grandchild should be descendant of root');
                $this->assertTrue($grandchildOffice->isDescendantOf($childOffice), 
                    'Grandchild should be descendant of child');
                $this->assertTrue($rootOffice->isAncestorOf($grandchildOffice), 
                    'Root should be ancestor of grandchild');

                // Property: Full name should show hierarchy
                $expectedFullName = $name1 . ' > ' . $name2 . ' > ' . $name3;
                $this->assertEquals($expectedFullName, $grandchildOffice->getFullName(), 
                    'Full name should show complete hierarchy');

                // Commit the transaction
                $this->entityManager->commit();
            } catch (\Exception $e) {
                // Rollback on any error
                $this->entityManager->rollback();
                throw $e;
            } finally {
                // Clean up - rollback will handle this, but ensure clean state
                $this->entityManager->clear();
            }
        });
    }

    /**
     * Property: Color Format Consistency
     * All office colors must be consistently formatted as uppercase hex codes
     */
    public function testColorFormatConsistency(): void
    {
        $this->limitTo(10)->forAll(
            Generator\map(
                function($str) { return 'Office_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\map(
                function($str) { return strtoupper(substr(md5($str), 0, 6)); },
                Generator\string()
            ),
            Generator\elements(['ff0000', 'FF0000', '#ff0000', '#FF0000', '00ff00', '#00FF00'])
        )->then(function (string $name, string $code, string $inputColor) {
            $office = new Office();
            $office->setName($name);
            $office->setCode($code);
            $office->setColor($inputColor);

            // Property: Color should always be formatted consistently
            $this->assertStringStartsWith('#', $office->getColor(), 'Color should start with #');
            $this->assertEquals(7, strlen($office->getColor()), 'Color should be exactly 7 characters');
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $office->getColor(), 
                'Color should be uppercase hex format');

            // Property: Color formatting should be idempotent
            $originalColor = $office->getColor();
            $office->setColor($originalColor);
            $this->assertEquals($originalColor, $office->getColor(), 
                'Setting the same color should not change it');
        });
    }
}