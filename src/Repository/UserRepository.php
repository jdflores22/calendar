<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserProviderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Symfony calls this method if you use features like switch_user
     * or remember_me. If you're not using these features, you do not
     * need to implement this method.
     *
     * @throws UserNotFoundException if the user is not found
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->findByEmail($identifier);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User with email "%s" not found.', $identifier));
        }

        return $user;
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called to give you a chance to refresh the user (e.g. if you want
     * to make sure the user isn't deleted).
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Find user by verification token
     */
    public function findByVerificationToken(string $token): ?User
    {
        return $this->findOneBy(['verificationToken' => $token]);
    }

    /**
     * Find user by password reset token
     */
    public function findByPasswordResetToken(string $token): ?User
    {
        return $this->findOneBy(['passwordResetToken' => $token]);
    }

    /**
     * Find users with incomplete profiles
     */
    public function findUsersWithIncompleteProfiles(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->where('p.isComplete = :incomplete OR p.id IS NULL')
            ->setParameter('incomplete', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users by role
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', json_encode($role))
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users by office
     */
    public function findByOffice(int $officeId): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.office = :officeId')
            ->setParameter('officeId', $officeId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total users
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count verified users
     */
    public function countVerified(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find users by search criteria
     */
    public function findBySearchCriteria(?string $search = null, ?string $role = null, ?int $officeId = null, ?bool $isVerified = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->leftJoin('u.office', 'o');

        if ($search) {
            $qb->andWhere('u.email LIKE :search OR p.firstName LIKE :search OR p.lastName LIKE :search OR o.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($role) {
            $qb->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
               ->setParameter('role', json_encode($role));
        }

        if ($officeId) {
            $qb->andWhere('u.office = :officeId')
               ->setParameter('officeId', $officeId);
        }

        if ($isVerified !== null) {
            $qb->andWhere('u.isVerified = :isVerified')
               ->setParameter('isVerified', $isVerified);
        }

        return $qb->orderBy('u.email', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}