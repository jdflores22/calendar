<?php

namespace App\Tests\Property;

use App\Entity\Form;
use App\Entity\FormField;
use App\Entity\User;
use App\Entity\Office;
use App\Service\FormFieldTypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Property 12: Form Builder Schema Consistency
 * Feature: tesda-calendar-system
 * Validates: Requirements 8.2, 8.3, 8.4, 8.5, 8.6
 */
class FormBuilderSchemaPropertyTest extends KernelTestCase
{
    use TestTrait;

    private EntityManagerInterface $entityManager;
    private FormFieldTypeRegistry $fieldTypeRegistry;
    private User $testUser;
    private Office $testOffice;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->fieldTypeRegistry = static::getContainer()->get(FormFieldTypeRegistry::class);
        
        $this->entityManager->createQuery('DELETE FROM App\Entity\Form')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\FormField')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Office')->execute();

        $this->testOffice = new Office();
        $this->testOffice->setName('Test Office')
                         ->setCode('TEST')
                         ->setColor('#FF0000');
        $this->entityManager->persist($this->testOffice);

        $this->testUser = new User();
        $this->testUser->setEmail('test@example.com')
                       ->setPassword('password')
                       ->setRoles(['ROLE_ADMIN'])
                       ->setVerified(true)
                       ->setOffice($this->testOffice);
        $this->entityManager->persist($this->testUser);

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager && $this->entityManager->isOpen()) {
            try {
                $this->entityManager->createQuery('DELETE FROM App\Entity\Form')->execute();
                $this->entityManager->createQuery('DELETE FROM App\Entity\FormField')->execute();
                $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
                $this->entityManager->createQuery('DELETE FROM App\Entity\Office')->execute();
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        parent::tearDown();
    }

    /**
     * @test
     */
    public function testFormBuilderSupportsAllSpecifiedFieldTypes(): void
    {
        $requiredFieldTypes = [
            FormField::TYPE_TEXT,
            FormField::TYPE_TEXTAREA,
            FormField::TYPE_EMAIL,
            FormField::TYPE_SELECT,
            FormField::TYPE_CHECKBOX,
            FormField::TYPE_RADIO,
            FormField::TYPE_FILE,
        ];

        $this->limitTo(5)->forAll(
            Generator\elements($requiredFieldTypes)
        )->then(function ($fieldType) {
            $this->assertTrue(
                $this->fieldTypeRegistry->hasType($fieldType),
                "Field type '{$fieldType}' must be supported by the registry"
            );
            
            $form = new Form();
            $form->setName('Test Form ' . rand(1000, 9999))
                 ->setSlug('test-form-' . rand(1000, 9999))
                 ->setCreator($this->testUser)
                 ->setActive(true);
            
            $field = new FormField();
            $field->setName('field_' . strtolower($fieldType) . '_' . rand(1000, 9999))
                  ->setLabel(ucfirst($fieldType) . ' Field')
                  ->setType($fieldType)
                  ->setForm($form);
            
            $this->entityManager->persist($form);
            $this->entityManager->persist($field);
            $this->entityManager->flush();
            
            $this->assertNotNull($field->getId(), "Field of type '{$fieldType}' must be persistable");
            $this->assertEquals($fieldType, $field->getType(), "Field type must be preserved");
            
            $this->entityManager->remove($field);
            $this->entityManager->remove($form);
            $this->entityManager->flush();
        });
    }

    /**
     * @test
     */
    public function testFormSchemaIsValidJson(): void
    {
        $this->limitTo(5)->forAll(
            Generator\choose(1, 3)
        )->then(function ($fieldCount) {
            $form = new Form();
            $form->setName('Test Form ' . rand(1000, 9999))
                 ->setSlug('test-form-' . rand(1000, 9999))
                 ->setCreator($this->testUser)
                 ->setActive(true);
            
            $schema = ['version' => '1.0', 'fields' => []];
            
            for ($i = 0; $i < $fieldCount; $i++) {
                $schema['fields'][] = [
                    'name' => 'field_' . $i,
                    'type' => FormField::TYPE_TEXT,
                    'label' => 'Field ' . $i,
                ];
            }
            
            $form->setSchema($schema);
            $this->entityManager->persist($form);
            $this->entityManager->flush();
            
            $this->assertTrue($form->isValidSchema(), "Form schema must be valid");
            
            $jsonSchema = json_encode($form->getSchema());
            $this->assertNotFalse($jsonSchema, "Schema must be JSON encodable");
            
            $decodedSchema = json_decode($jsonSchema, true);
            $this->assertNotNull($decodedSchema, "Schema must be JSON decodable");
            
            $this->entityManager->remove($form);
            $this->entityManager->flush();
        });
    }

    /**
     * @test
     */
    public function testFormSupportsTaggingAndAssignment(): void
    {
        $this->limitTo(5)->forAll(
            Generator\seq(Generator\string())
        )->then(function ($tags) {
            $form = new Form();
            $form->setName('Test Form ' . rand(1000, 9999))
                 ->setSlug('test-form-' . rand(1000, 9999))
                 ->setCreator($this->testUser)
                 ->setActive(true);
            
            foreach (array_slice($tags, 0, 3) as $tag) {
                if (!empty(trim($tag))) {
                    $form->addTag(trim($tag));
                }
            }
            
            $form->setAssignedTo('test-module');
            
            $this->entityManager->persist($form);
            $this->entityManager->flush();
            
            $this->assertIsArray($form->getTags(), "Form must support tags as array");
            $this->assertEquals('test-module', $form->getAssignedTo(), "Form must support assignment");
            
            $this->entityManager->remove($form);
            $this->entityManager->flush();
        });
    }
}