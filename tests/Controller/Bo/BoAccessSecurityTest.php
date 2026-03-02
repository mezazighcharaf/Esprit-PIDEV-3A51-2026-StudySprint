<?php

namespace App\Tests\Controller\Bo;

use App\Entity\User;
use App\Entity\Administrator;
use App\Entity\Student;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests that all BO routes are protected:
 * - Anonymous → redirect to login
 * - ROLE_USER → 403
 * - ROLE_ADMIN → 200
 */
class BoAccessSecurityTest extends WebTestCase
{
    private static ?int $adminId = null;
    private static ?int $userId = null;

    private function requireDatabase(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        try {
            $em->getConnection()->executeQuery('SELECT 1');
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
        return $em;
    }

    private function ensureTestUsers(): void
    {
        $container = static::getContainer();
        $em = $this->requireDatabase();
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $repo = $em->getRepository(User::class);

        $admin = $repo->findOneBy(['email' => 'test_bo_admin@studysprint.test']);
        if (!$admin) {
            $admin = new Administrator();
            $admin->setEmail('test_bo_admin@studysprint.test');
            $admin->setFullName('BO Admin');
            $admin->setRole('ROLE_ADMIN');
            $admin->setPassword($hasher->hashPassword($admin, 'admin123'));
            $em->persist($admin);
            $em->flush();
        }
        self::$adminId = $admin->getId();

        $user = $repo->findOneBy(['email' => 'test_bo_user@studysprint.test']);
        if (!$user) {
            $user = new Student();
            $user->setEmail('test_bo_user@studysprint.test');
            $user->setFullName('BO User');
            $user->setRole('ROLE_USER');
            $user->setPassword($hasher->hashPassword($user, 'user123'));
            $em->persist($user);
            $em->flush();
        }
        self::$userId = $user->getId();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function boRoutesProvider(): array
    {
        return [
            'users'          => ['/bo/users'],
            'user-profiles'  => ['/bo/user-profiles'],
            'subjects'       => ['/bo/subjects'],
            'chapters'       => ['/bo/chapters'],
            'quizzes'        => ['/bo/quizzes'],
            'decks'          => ['/bo/decks'],
            'plans'          => ['/bo/plans'],
            'tasks'          => ['/bo/tasks'],
            'groups'         => ['/bo/groups'],
            'posts'          => ['/bo/posts'],
            'certifications' => ['/bo/certifications'],
            'ai-monitoring'  => ['/bo/ai-monitoring'],
            'ai-logs'        => ['/bo/ai-monitoring/logs'],
        ];
    }

    /**
     * @dataProvider boRoutesProvider
     */
    public function testAnonymousRedirectedFromBo(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);
        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('login', $location, "Anonymous should be redirected to login for $url");
    }

    /**
     * @dataProvider boRoutesProvider
     */
    public function testRegularUserDeniedBo(string $url): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->find(self::$userId);

        $client->loginUser($user);
        $client->request('GET', $url);
        $this->assertResponseStatusCodeSame(403, "Regular user should get 403 for $url");
    }

    /**
     * @dataProvider boRoutesProvider
     */
    public function testAdminCanAccessBo(string $url): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->find(self::$adminId);

        $client->loginUser($admin);
        $client->request('GET', $url);
        $this->assertResponseIsSuccessful("Admin should get 200 for $url");
    }
}
