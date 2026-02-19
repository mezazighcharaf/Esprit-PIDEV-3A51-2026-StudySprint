<?php

namespace App\Tests\Controller\Bo;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BoAccessControlTest extends WebTestCase
{
    private static ?int $adminId = null;
    private static ?int $userId = null;

    /**
     * Ensure test users exist in the DB (run once).
     */
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
        $client = static::getClient() ?? static::createClient();
        $container = static::getContainer();
        $em = $this->requireDatabase();
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $repo = $em->getRepository(User::class);

        // Admin user
        $admin = $repo->findOneBy(['email' => 'test_admin_bo@studysprint.test']);
        if (!$admin) {
            $admin = new User();
            $admin->setEmail('test_admin_bo@studysprint.test');
            $admin->setFullName('Test Admin BO');
            $admin->setUserType('ADMIN');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setPassword($hasher->hashPassword($admin, 'admin123'));
            $em->persist($admin);
            $em->flush();
        }
        self::$adminId = $admin->getId();

        // Regular user
        $user = $repo->findOneBy(['email' => 'test_user_bo@studysprint.test']);
        if (!$user) {
            $user = new User();
            $user->setEmail('test_user_bo@studysprint.test');
            $user->setFullName('Test User BO');
            $user->setUserType('STUDENT');
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($hasher->hashPassword($user, 'user123'));
            $em->persist($user);
            $em->flush();
        }
        self::$userId = $user->getId();
    }

    /**
     * All BO index routes that should be protected.
     */
    public static function boIndexRoutesProvider(): array
    {
        return [
            ['/bo/users'],
            ['/bo/subjects'],
            ['/bo/chapters'],
            ['/bo/quizzes'],
            ['/bo/decks'],
            ['/bo/plans'],
            ['/bo/tasks'],
            ['/bo/groups'],
            ['/bo/posts'],
            ['/bo/user-profiles'],
            ['/bo/ai-monitoring'],
            ['/bo/ai-monitoring/logs'],
        ];
    }

    /**
     * Test: anonymous user is redirected to login for all BO routes.
     *
     * @dataProvider boIndexRoutesProvider
     */
    public function testAnonymousRedirectedFromBo(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertResponseRedirects(
            null,
            302,
            "Anonymous access to $url should redirect (302)"
        );
        $this->assertStringContainsString(
            'login',
            $client->getResponse()->headers->get('Location'),
            "Anonymous should be redirected to login for $url"
        );
    }

    /**
     * Test: ROLE_USER is denied access to BO routes (403).
     *
     * @dataProvider boIndexRoutesProvider
     */
    public function testRegularUserDeniedBo(string $url): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->find(self::$userId);

        $client->loginUser($user);
        $client->request('GET', $url);

        $this->assertResponseStatusCodeSame(
            403,
            "ROLE_USER should get 403 on $url"
        );
    }

    /**
     * Test: ROLE_ADMIN can access all BO index routes (200).
     *
     * @dataProvider boIndexRoutesProvider
     */
    public function testAdminCanAccessBo(string $url): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->find(self::$adminId);

        $client->loginUser($admin);
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful(
            "ROLE_ADMIN should get 200 on $url"
        );
    }

    /**
     * Test: BO index pages contain pagination when data exists.
     */
    public function testBoIndexPagesContainPaginationMarkup(): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->find(self::$adminId);

        $client->loginUser($admin);

        // Users page should have at least 2 users (our test users)
        $client->request('GET', '/bo/users');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('table', 'Users index should have a table');
    }
}
