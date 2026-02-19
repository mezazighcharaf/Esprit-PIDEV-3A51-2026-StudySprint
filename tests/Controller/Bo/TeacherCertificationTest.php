<?php

namespace App\Tests\Controller\Bo;

use App\Entity\TeacherCertificationRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TeacherCertificationTest extends WebTestCase
{
    private static ?int $adminId = null;
    private static ?int $studentId = null;

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

        $admin = $repo->findOneBy(['email' => 'test_cert_admin@studysprint.test']);
        if (!$admin) {
            $admin = new User();
            $admin->setEmail('test_cert_admin@studysprint.test');
            $admin->setFullName('Cert Admin');
            $admin->setUserType('ADMIN');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setPassword($hasher->hashPassword($admin, 'admin123'));
            $em->persist($admin);
            $em->flush();
        }
        self::$adminId = $admin->getId();

        $student = $repo->findOneBy(['email' => 'test_cert_student@studysprint.test']);
        if (!$student) {
            $student = new User();
            $student->setEmail('test_cert_student@studysprint.test');
            $student->setFullName('Cert Student');
            $student->setUserType('STUDENT');
            $student->setRoles(['ROLE_USER']);
            $student->setPassword($hasher->hashPassword($student, 'user123'));
            $em->persist($student);
            $em->flush();
        }
        self::$studentId = $student->getId();

        // Clean up any existing certification requests for this student
        $certRepo = $em->getRepository(TeacherCertificationRequest::class);
        $existing = $certRepo->findBy(['user' => $student]);
        foreach ($existing as $req) {
            $em->remove($req);
        }
        $em->flush();
    }

    // ─── Security Tests ───

    public function testAnonymousRedirectedFromBoCertifications(): void
    {
        $client = static::createClient();
        $client->request('GET', '/bo/certifications');
        $this->assertResponseRedirects();
        $this->assertStringContainsString('login', $client->getResponse()->headers->get('Location'));
    }

    public function testRegularUserDeniedBoCertifications(): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $student = $em->getRepository(User::class)->find(self::$studentId);

        $client->loginUser($student);
        $client->request('GET', '/bo/certifications');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessBoCertifications(): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->find(self::$adminId);

        $client->loginUser($admin);
        $client->request('GET', '/bo/certifications');
        $this->assertResponseIsSuccessful();
    }

    // ─── Helper: extract CSRF token from page ───

    private function getCsrfToken($client, string $url, string $tokenFieldName = '_token'): string
    {
        $crawler = $client->request('GET', $url);
        $token = $crawler->filter("input[name=\"{$tokenFieldName}\"]")->first();
        if ($token->count() > 0) {
            return $token->attr('value');
        }
        // Fallback: look for csrf_token in hidden inputs
        $token = $crawler->filter('input[type="hidden"][name="_token"]')->first();
        return $token->count() > 0 ? $token->attr('value') : '';
    }

    // ─── Functional Tests ───

    public function testStudentCanSubmitCertificationRequest(): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $student = $em->getRepository(User::class)->find(self::$studentId);

        $client->loginUser($student);

        // Load profile page to get CSRF token
        $crawler = $client->request('GET', '/fo/profile');
        $this->assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');

        // Submit certification request
        $client->request('POST', '/fo/profile/certification', [
            '_token' => $token,
            'motivation' => 'Je suis enseignant en mathématiques.',
        ]);

        $this->assertResponseRedirects('/fo/profile');

        // Verify request was created
        $certRepo = $em->getRepository(TeacherCertificationRequest::class);
        $req = $certRepo->findOneBy(['user' => $student, 'status' => 'PENDING']);
        $this->assertNotNull($req, 'A PENDING certification request should exist');
        $this->assertEquals('Je suis enseignant en mathématiques.', $req->getMotivation());
    }

    public function testDoublePendingRequestBlocked(): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $student = $em->getRepository(User::class)->find(self::$studentId);

        // Create a PENDING request
        $req = new TeacherCertificationRequest();
        $req->setUser($student);
        $req->setMotivation('First request');
        $em->persist($req);
        $em->flush();

        $client->loginUser($student);

        // Load profile to get token — should show "pending" state (no form), so use a direct POST
        $crawler = $client->request('GET', '/fo/profile');
        $this->assertResponseIsSuccessful();

        // The page should NOT have a certification form since there's already a PENDING request
        $formCount = $crawler->filter('form[action*="certification"]')->count();
        $this->assertEquals(0, $formCount, 'No certification form should be shown when PENDING');
    }

    public function testAdminCanApproveRequest(): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->find(self::$adminId);
        $student = $em->getRepository(User::class)->find(self::$studentId);

        // Create a PENDING request
        $req = new TeacherCertificationRequest();
        $req->setUser($student);
        $em->persist($req);
        $em->flush();
        $reqId = $req->getId();

        $client->loginUser($admin);

        // Load show page to get CSRF token
        $crawler = $client->request('GET', "/bo/certifications/{$reqId}");
        $this->assertResponseIsSuccessful();
        $approveForm = $crawler->filter("form[action*=\"/approve\"]");
        $this->assertGreaterThan(0, $approveForm->count(), 'Approve form should exist');
        $token = $approveForm->filter('input[name="_token"]')->first()->attr('value');

        // Approve
        $client->request('POST', "/bo/certifications/{$reqId}/approve", [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects("/bo/certifications/{$reqId}");

        // Verify
        $em->clear();
        $updatedReq = $em->getRepository(TeacherCertificationRequest::class)->find($reqId);
        $this->assertEquals('APPROVED', $updatedReq->getStatus());
        $this->assertNotNull($updatedReq->getReviewedAt());
        $this->assertEquals(self::$adminId, $updatedReq->getReviewedBy()->getId());

        // User should now be TEACHER
        $updatedStudent = $em->getRepository(User::class)->find(self::$studentId);
        $this->assertEquals('TEACHER', $updatedStudent->getUserType());
        $this->assertTrue($updatedStudent->isCertifiedTeacher());
    }

    public function testAdminCanRejectRequest(): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->find(self::$adminId);
        $student = $em->getRepository(User::class)->find(self::$studentId);

        // Reset student to STUDENT
        $student->setUserType('STUDENT');
        $em->flush();

        // Create a PENDING request
        $req = new TeacherCertificationRequest();
        $req->setUser($student);
        $em->persist($req);
        $em->flush();
        $reqId = $req->getId();

        $client->loginUser($admin);

        // Load show page to get CSRF token
        $crawler = $client->request('GET', "/bo/certifications/{$reqId}");
        $this->assertResponseIsSuccessful();
        $rejectForm = $crawler->filter("form[action*=\"/reject\"]");
        $token = $rejectForm->filter('input[name="_token"]')->first()->attr('value');

        // Reject with reason
        $client->request('POST', "/bo/certifications/{$reqId}/reject", [
            '_token' => $token,
            'reason' => 'Documents insuffisants',
        ]);

        $this->assertResponseRedirects("/bo/certifications/{$reqId}");

        // Verify
        $em->clear();
        $updatedReq = $em->getRepository(TeacherCertificationRequest::class)->find($reqId);
        $this->assertEquals('REJECTED', $updatedReq->getStatus());
        $this->assertEquals('Documents insuffisants', $updatedReq->getReason());

        // User should still be STUDENT
        $updatedStudent = $em->getRepository(User::class)->find(self::$studentId);
        $this->assertEquals('STUDENT', $updatedStudent->getUserType());
    }

    public function testRejectedUserCanResubmit(): void
    {
        $client = static::createClient();
        $this->ensureTestUsers();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $student = $em->getRepository(User::class)->find(self::$studentId);

        // Create a REJECTED request
        $req = new TeacherCertificationRequest();
        $req->setUser($student);
        $req->setStatus(TeacherCertificationRequest::STATUS_REJECTED);
        $req->setReviewedAt(new \DateTimeImmutable());
        $req->setReason('Test rejection');
        $em->persist($req);
        $em->flush();

        $client->loginUser($student);

        // Load profile — should show "rejected" state with re-submit form
        $crawler = $client->request('GET', '/fo/profile');
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[action*="certification"]');
        $this->assertGreaterThan(0, $form->count(), 'Re-submit form should be shown after rejection');
        $token = $form->filter('input[name="_token"]')->first()->attr('value');

        // Submit new request
        $client->request('POST', '/fo/profile/certification', [
            '_token' => $token,
            'motivation' => 'New attempt after rejection',
        ]);

        $this->assertResponseRedirects('/fo/profile');

        // Should have a new PENDING request
        $certRepo = $em->getRepository(TeacherCertificationRequest::class);
        $latest = $certRepo->findLatestByUser($student);
        $this->assertNotNull($latest);
        $this->assertEquals('PENDING', $latest->getStatus());
        $this->assertEquals('New attempt after rejection', $latest->getMotivation());
    }
}
