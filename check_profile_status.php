<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();

// Get all users with their profiles
$users = $entityManager->getRepository('App\Entity\User')->findAll();

echo "=== USER PROFILE STATUS ===\n\n";

foreach ($users as $user) {
    echo "User: " . $user->getEmail() . "\n";
    echo "ID: " . $user->getId() . "\n";
    
    $profile = $user->getProfile();
    
    if ($profile) {
        echo "Profile exists: YES\n";
        echo "First Name: '" . $profile->getFirstName() . "'\n";
        echo "Last Name: '" . $profile->getLastName() . "'\n";
        echo "Phone: '" . ($profile->getPhone() ?? 'NULL') . "'\n";
        echo "Avatar: '" . ($profile->getAvatar() ?? 'NULL') . "'\n";
        echo "Office: " . ($user->getOffice() ? $user->getOffice()->getName() : 'NULL') . "\n";
        echo "Is Complete: " . ($profile->isComplete() ? 'YES' : 'NO') . "\n";
    } else {
        echo "Profile exists: NO\n";
    }
    
    echo "\n" . str_repeat('-', 50) . "\n\n";
}
