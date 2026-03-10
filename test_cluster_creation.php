<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();

// Test creating a cluster
try {
    $cluster = new \App\Entity\OfficeCluster();
    $cluster->setName('Test Cluster');
    $cluster->setCode('TEST-01');
    $cluster->setDescription('Test description');
    $cluster->setColor('#FF5733');
    $cluster->setDisplayOrder(0);
    $cluster->setActive(true);
    
    echo "Cluster object created successfully\n";
    echo "Name: " . $cluster->getName() . "\n";
    echo "Code: " . $cluster->getCode() . "\n";
    echo "Color: " . $cluster->getColor() . "\n";
    
    // Try to persist
    $entityManager->persist($cluster);
    $entityManager->flush();
    
    echo "\n✓ Cluster saved to database successfully!\n";
    echo "ID: " . $cluster->getId() . "\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
