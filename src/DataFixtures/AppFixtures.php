<?php

namespace App\DataFixtures;

use App\Entity\Administrator;
use App\Entity\AiGenerationLog;
use App\Entity\AiModel;
use App\Entity\Chapter;
use App\Entity\Flashcard;
use App\Entity\FlashcardDeck;
use App\Entity\FlashcardReviewState;
use App\Entity\GroupMember;
use App\Entity\GroupPost;
use App\Entity\Notification;
use App\Entity\PostComment;
use App\Entity\PostLike;
use App\Entity\PostRating;
use App\Entity\GroupInvitation;
use App\Entity\PlanTask;
use App\Entity\Professor;
use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\RevisionPlan;
use App\Entity\Student;
use App\Entity\StudyGroup;
use App\Entity\Subject;
use App\Entity\TeacherCertificationRequest;
use App\Entity\UserProfile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ===== 1. USERS (STI) =====

        // Admin
        $admin = new Administrator();
        $admin->setNom('StudySprint')->setPrenom('Admin')
              ->setEmail('admin@studysprint.local')
              ->setRole('ROLE_ADMIN')->setStatut('actif');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // Étudiants
        $alice = new Student();
        $alice->setNom('Martin')->setPrenom('Alice')
              ->setEmail('alice.martin@studysprint.local')
              ->setRole('ROLE_USER')->setStatut('actif')
              ->setAge(20)->setSexe('F')
              ->setEtablissement('Université Paris-Saclay')->setNiveau('LICENCE3');
        $alice->setPassword($this->passwordHasher->hashPassword($alice, 'user123'));
        $manager->persist($alice);

        $bob = new Student();
        $bob->setNom('Dupont')->setPrenom('Bob')
            ->setEmail('bob.dupont@studysprint.local')
            ->setRole('ROLE_USER')->setStatut('actif')
            ->setAge(22)->setSexe('M')
            ->setEtablissement('ENS Lyon')->setNiveau('MASTER1');
        $bob->setPassword($this->passwordHasher->hashPassword($bob, 'user123'));
        $manager->persist($bob);

        $charlie = new Student();
        $charlie->setNom('Bernard')->setPrenom('Charlie')
                ->setEmail('charlie.bernard@studysprint.local')
                ->setRole('ROLE_USER')->setStatut('actif')
                ->setAge(19)->setSexe('M')
                ->setEtablissement('Université Lyon 1')->setNiveau('LICENCE2');
        $charlie->setPassword($this->passwordHasher->hashPassword($charlie, 'user123'));
        $manager->persist($charlie);

        // Professeurs
        $prof = new Professor();
        $prof->setNom('Leroux')->setPrenom('Claire')
             ->setEmail('prof.claire@studysprint.local')
             ->setRole('ROLE_TEACHER')->setStatut('actif')
             ->setAge(38)->setSexe('F')
             ->setEtablissement('Université Paris VI')
             ->setSpecialite('Mathématiques Appliquées')
             ->setNiveauEnseignement('Licence / Master')
             ->setAnneesExperience(12);
        $prof->setPassword($this->passwordHasher->hashPassword($prof, 'user123'));
        $manager->persist($prof);

        $profPhys = new Professor();
        $profPhys->setNom('Dubois')->setPrenom('Marc')
                 ->setEmail('prof.marc@studysprint.local')
                 ->setRole('ROLE_TEACHER')->setStatut('actif')
                 ->setAge(45)->setSexe('M')
                 ->setEtablissement('Polytechnique')
                 ->setSpecialite('Physique Quantique')
                 ->setNiveauEnseignement('Master / Doctorat')
                 ->setAnneesExperience(18);
        $profPhys->setPassword($this->passwordHasher->hashPassword($profPhys, 'user123'));
        $manager->persist($profPhys);

        $manager->flush();

        // ===== 2. USER PROFILES =====

        $profileAlice = (new UserProfile())
            ->setUser($alice)->setLevel('LICENCE3')
            ->setSpecialty('Mathématiques')
            ->setBio('Étudiante en L3 maths, passionnée d\'analyse et d\'algèbre.')
            ->setAiSuggestedBio('Étudiante passionnée en mathématiques avancées, je cherche à approfondir mes connaissances en analyse et algèbre linéaire.')
            ->setAiSuggestedGoals("• Maîtriser les intégrales multiples\n• Obtenir ≥ 16/20 à l'examen final\n• Résoudre 50 exercices par semaine")
            ->setAiSuggestedRoutine("Matin 8h-10h : Cours théorique\nAprès-midi 14h-16h : Exercices\nSoir 20h-21h : Flashcards SM-2");
        $manager->persist($profileAlice);

        $profileBob = (new UserProfile())
            ->setUser($bob)->setLevel('MASTER1')
            ->setSpecialty('Physique')
            ->setBio('Passionné de physique quantique et de cosmologie.');
        $manager->persist($profileBob);

        $profileCharlie = (new UserProfile())
            ->setUser($charlie)->setLevel('LICENCE2')
            ->setSpecialty('Chimie')
            ->setBio('Étudiant en chimie organique.');
        $manager->persist($profileCharlie);

        $manager->flush();

        // ===== 3. SUBJECTS & CHAPTERS =====

        $mathSubject = (new Subject())
            ->setCode('MATH101')->setName('Mathématiques Avancées')
            ->setDescription('Analyse, algèbre linéaire et équations différentielles')
            ->setCreatedBy($prof);
        $manager->persist($mathSubject);

        $physSubject = (new Subject())
            ->setCode('PHYS201')->setName('Physique Quantique')
            ->setDescription('Mécanique quantique et applications')
            ->setCreatedBy($profPhys);
        $manager->persist($physSubject);

        $chemSubject = (new Subject())
            ->setCode('CHEM301')->setName('Chimie Organique')
            ->setDescription('Réactions organiques et mécanismes')
            ->setCreatedBy($admin);
        $manager->persist($chemSubject);

        $manager->flush();

        foreach ([
            ['Suites et séries', 'Convergence, séries numériques', 1],
            ['Intégrales', 'Intégration de Riemann et applications', 2],
            ['Équations différentielles', 'EDO du 1er et 2nd ordre', 3],
        ] as [$title, $summary, $order]) {
            $manager->persist((new Chapter())
                ->setSubject($mathSubject)->setTitle($title)
                ->setSummary($summary)->setOrderNo($order)->setCreatedBy($prof));
        }

        foreach ([
            ['Postulats de la MQ', 'Bases de la mécanique quantique', 1],
            ['Opérateurs et observables', 'Hamiltonien et mesure', 2],
            ["Atome d'hydrogène", 'Résolution exacte', 3],
        ] as [$title, $summary, $order]) {
            $manager->persist((new Chapter())
                ->setSubject($physSubject)->setTitle($title)
                ->setSummary($summary)->setOrderNo($order)->setCreatedBy($profPhys));
        }

        foreach ([
            ['Alcènes et alcynes', 'Réactivité des liaisons multiples', 1],
            ['Mécanismes réactionnels', 'SN1, SN2, E1, E2', 2],
        ] as [$title, $summary, $order]) {
            $manager->persist((new Chapter())
                ->setSubject($chemSubject)->setTitle($title)
                ->setSummary($summary)->setOrderNo($order)->setCreatedBy($admin));
        }

        $manager->flush();

        // ===== 4. STUDY GROUPS + MEMBERS + POSTS + LIKES + RATINGS + COMMENTS =====

        $group1 = (new StudyGroup())
            ->setName('Groupe Maths Terminale')
            ->setDescription('Entraide pour préparation bac et concours')
            ->setPrivacy('public')->setSubject('Mathématiques')
            ->setCreatedBy($alice);
        $manager->persist($group1);

        $group2 = (new StudyGroup())
            ->setName('Physique Prépa')
            ->setDescription('Révisions physique niveau prépa')
            ->setPrivacy('public')->setSubject('Physique')
            ->setCreatedBy($prof);
        $manager->persist($group2);

        $group3 = (new StudyGroup())
            ->setName('Chimie Organique Avancée')
            ->setDescription('Questions et exercices de chimie organique')
            ->setPrivacy('private')->setSubject('Chimie')
            ->setCreatedBy($bob);
        $manager->persist($group3);

        $manager->flush();

        // Membres
        foreach ([
            [$group1, $alice, 'admin'],
            [$group1, $bob, 'member'],
            [$group1, $charlie, 'member'],
            [$group2, $prof, 'admin'],
            [$group2, $alice, 'member'],
            [$group2, $bob, 'moderator'],
            [$group3, $bob, 'admin'],
            [$group3, $charlie, 'member'],
        ] as [$group, $user, $role]) {
            $member = (new GroupMember())->setGroup($group)->setUser($user)->setMemberRole($role);
            $manager->persist($member);
        }

        $manager->flush();

        // Posts
        $post1 = (new GroupPost())
            ->setGroup($group1)->setAuthor($alice)
            ->setTitle("Besoin d'aide sur les intégrales")
            ->setBody("Bonjour, j'ai du mal avec l'intégration par parties. Quelqu'un peut m'expliquer ?")
            ->setPostType('text');
        $manager->persist($post1);

        $post2 = (new GroupPost())
            ->setGroup($group1)->setAuthor($bob)
            ->setTitle('Partage: formulaire de dérivées')
            ->setBody("J'ai créé un formulaire avec toutes les dérivées usuelles. Disponible sur demande !")
            ->setPostType('text');
        $manager->persist($post2);

        $post3 = (new GroupPost())
            ->setGroup($group2)->setAuthor($prof)
            ->setTitle('Exercice du jour: effet tunnel')
            ->setBody("Calculez la probabilité de transmission d'une particule à travers une barrière de potentiel.")
            ->setPostType('text');
        $manager->persist($post3);

        $manager->flush();

        // Commentaires (PostComment — nouvelle entité des collègues)
        $comment1 = (new PostComment())
            ->setPost($post1)->setAuthor($prof)
            ->setBody("Bien sûr ! L'intégration par parties utilise ∫u dv = uv - ∫v du.");
        $manager->persist($comment1);

        $comment2 = (new PostComment())
            ->setPost($post1)->setAuthor($bob)
            ->setBody('Merci ! Et pour ∫ln(x) dx, on pose u=ln(x) et dv=dx ?');
        $manager->persist($comment2);

        $comment3 = (new PostComment())
            ->setPost($post1)->setAuthor($prof)
            ->setParentComment($comment2)
            ->setBody('Exactement ! Tu obtiens x·ln(x) - x + C.');
        $manager->persist($comment3);

        $comment4 = (new PostComment())
            ->setPost($post3)->setAuthor($alice)
            ->setBody("J'ai trouvé T ≈ 0.15 avec V0=5eV et E=3eV. Est-ce correct ?");
        $manager->persist($comment4);

        // Likes (PostLike — nouvelle entité)
        foreach ([$alice, $bob, $charlie] as $user) {
            $manager->persist((new PostLike())->setPost($post2)->setUser($user));
        }
        foreach ([$alice, $bob] as $user) {
            $manager->persist((new PostLike())->setPost($post3)->setUser($user));
        }

        // Ratings (PostRating — nouvelle entité)
        $manager->persist((new PostRating())->setPost($post2)->setUser($alice)->setRating(5));
        $manager->persist((new PostRating())->setPost($post2)->setUser($bob)->setRating(4));
        $manager->persist((new PostRating())->setPost($post3)->setUser($alice)->setRating(5));
        $manager->persist((new PostRating())->setPost($post3)->setUser($bob)->setRating(5));

        // Invitation (GroupInvitation — nouvelle entité)
        $invitation = new GroupInvitation();
        $invitation->setGroup($group3);
        $invitation->setInvitedBy($bob);
        $invitation->setEmail('charlie.bernard@studysprint.local');
        $invitation->setCode(bin2hex(random_bytes(16)));
        $invitation->setRole('member');
        $invitation->setStatus('pending');
        $invitation->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $manager->persist($invitation);

        $manager->flush();

        // ===== 5. REVISION PLANS + TASKS =====

        $plan1 = (new RevisionPlan())
            ->setUser($alice)->setSubject($mathSubject)
            ->setTitle('Plan Maths — Examen final')
            ->setStartDate(new \DateTimeImmutable('2026-02-01'))
            ->setEndDate(new \DateTimeImmutable('2026-02-28'))
            ->setStatus('active');
        $manager->persist($plan1);

        $plan2 = (new RevisionPlan())
            ->setUser($bob)->setSubject($physSubject)
            ->setTitle('Révisions Physique Quantique')
            ->setStartDate(new \DateTimeImmutable('2026-02-10'))
            ->setEndDate(new \DateTimeImmutable('2026-02-28'))
            ->setStatus('active');
        $manager->persist($plan2);

        $manager->flush();

        foreach ([
            [$plan1, 'Réviser suites et séries', '2026-02-09 09:00', '2026-02-09 11:00', 'todo'],
            [$plan1, "Exercices d'intégrales", '2026-02-11 14:00', '2026-02-11 16:00', 'todo'],
            [$plan1, 'EDO — Cours et exercices', '2026-02-13 10:00', '2026-02-13 12:00', 'done'],
            [$plan2, 'Postulats MQ', '2026-02-16 09:00', '2026-02-16 10:30', 'todo'],
            [$plan2, 'Opérateurs et hamiltonien', '2026-02-17 14:00', '2026-02-17 16:00', 'in_progress'],
        ] as [$plan, $title, $start, $end, $status]) {
            $manager->persist((new PlanTask())
                ->setPlan($plan)->setTitle($title)
                ->setStartAt(new \DateTimeImmutable($start))
                ->setEndAt(new \DateTimeImmutable($end))
                ->setStatus($status));
        }

        $manager->flush();

        // ===== 6. QUIZZES + ATTEMPTS =====

        $quiz1 = (new Quiz())
            ->setOwner($prof)->setSubject($mathSubject)
            ->setTitle('Intégrales — Test rapide')
            ->setDifficulty(Quiz::DIFFICULTY_MEDIUM)
            ->setQuestions([
                ['question' => "Quelle est l'intégrale de x² ?", 'type' => 'single',
                 'choices' => ['x³/3 + C', 'x³ + C', '2x + C'], 'correct' => 0],
                ['question' => 'Que vaut ∫e^x dx ?', 'type' => 'single',
                 'choices' => ['e^x + C', 'xe^x + C', 'e^(x+1)/(x+1) + C'], 'correct' => 0],
                ['question' => "Pour ∫ln(x) dx, quelle méthode utilise-t-on ?", 'type' => 'single',
                 'choices' => ['Intégration par parties', 'Changement de variable', 'Intégration directe'], 'correct' => 0],
            ])->setIsPublished(true);
        $manager->persist($quiz1);

        $quiz2 = (new Quiz())
            ->setOwner($profPhys)->setSubject($physSubject)
            ->setTitle('Mécanique Quantique — QCM')
            ->setDifficulty(Quiz::DIFFICULTY_HARD)
            ->setQuestions([
                ['question' => "Quel opérateur correspond à l'énergie ?", 'type' => 'single',
                 'choices' => ['Hamiltonien', 'Impulsion', 'Position'], 'correct' => 0],
                ['question' => "Que représente |ψ(x)|² ?", 'type' => 'single',
                 'choices' => ["Densité de probabilité de présence", "Énergie de la particule", "Impulsion"], 'correct' => 0],
            ])->setIsPublished(true);
        $manager->persist($quiz2);

        $quiz3 = (new Quiz())
            ->setOwner($prof)->setSubject($mathSubject)
            ->setTitle('Dérivées — Niveau facile')
            ->setDifficulty(Quiz::DIFFICULTY_EASY)
            ->setQuestions([
                ['question' => "Dérivée de x³ ?", 'type' => 'single',
                 'choices' => ['3x²', 'x²', '3x'], 'correct' => 0],
            ])->setIsPublished(true);
        $manager->persist($quiz3);

        $manager->flush();

        // Tentatives (historique + leaderboard)
        foreach ([
            [$alice, $quiz1, 3, 3, 100.0, 180, '-3 days'],
            [$bob, $quiz1, 3, 2, 66.7, 240, '-2 days'],
            [$charlie, $quiz1, 3, 1, 33.3, 300, '-1 day'],
            [$alice, $quiz2, 2, 2, 100.0, 120, '-2 days'],
            [$bob, $quiz2, 2, 1, 50.0, 200, '-1 day'],
            [$alice, $quiz3, 1, 1, 100.0, 45, '-4 days'],
            [$charlie, $quiz3, 1, 0, 0.0, 60, '-1 day'],
        ] as [$user, $quiz, $total, $correct, $score, $duration, $when]) {
            $attempt = (new QuizAttempt())
                ->setUser($user)->setQuiz($quiz)
                ->setTotalQuestions($total)->setCorrectCount($correct)
                ->setScore($score)->setDurationSeconds($duration)
                ->setCompletedAt(new \DateTimeImmutable($when));
            $manager->persist($attempt);
        }

        $manager->flush();

        // ===== 7. FLASHCARD DECKS + CARDS + REVIEW STATES =====

        $deck1 = (new FlashcardDeck())
            ->setOwner($prof)->setSubject($mathSubject)
            ->setTitle('Formules Mathématiques Essentielles')
            ->setIsPublished(true);
        $manager->persist($deck1);

        $deck2 = (new FlashcardDeck())
            ->setOwner($profPhys)->setSubject($physSubject)
            ->setTitle('Constantes et Relations Physiques')
            ->setIsPublished(true);
        $manager->persist($deck2);

        $deck3 = (new FlashcardDeck())
            ->setOwner($alice)->setSubject($mathSubject)
            ->setTitle('Mes fiches perso — Dérivées')
            ->setIsPublished(false);
        $manager->persist($deck3);

        $manager->flush();

        $cards1 = [
            ['Dérivée de sin(x)', 'cos(x)'],
            ['Dérivée de cos(x)', '-sin(x)'],
            ['Dérivée de e^x', 'e^x'],
            ['Dérivée de ln(x)', '1/x'],
            ["Intégrale de 1/x", 'ln|x| + C'],
            ['Formule de Taylor ordre 1', "f(a+h) ≈ f(a) + h·f'(a)"],
        ];
        foreach ($cards1 as $i => [$front, $back]) {
            $fc = (new Flashcard())->setDeck($deck1)->setFront($front)->setBack($back)->setPosition($i + 1);
            $manager->persist($fc);
            $manager->persist((new FlashcardReviewState())
                ->setUser($alice)->setFlashcard($fc)
                ->setEaseFactor(2.5)->setIntervalDays(1)->setRepetitions(0)
                ->setDueAt(new \DateTimeImmutable('today')));
            $manager->persist((new FlashcardReviewState())
                ->setUser($bob)->setFlashcard($fc)
                ->setEaseFactor(2.3)->setIntervalDays(3)->setRepetitions(2)
                ->setDueAt(new \DateTimeImmutable('+2 days')));
        }

        $cards2 = [
            ['Vitesse de la lumière (c)', '3.00 × 10⁸ m/s'],
            ['Constante de Planck (h)', '6.626 × 10⁻³⁴ J·s'],
            ['Charge élémentaire (e)', '1.602 × 10⁻¹⁹ C'],
            ['Relation de de Broglie', 'λ = h/p'],
            ['Principe incertitude Heisenberg', 'Δx·Δp ≥ ℏ/2'],
        ];
        foreach ($cards2 as $i => [$front, $back]) {
            $fc = (new Flashcard())->setDeck($deck2)->setFront($front)->setBack($back)->setPosition($i + 1);
            $manager->persist($fc);
            $manager->persist((new FlashcardReviewState())
                ->setUser($bob)->setFlashcard($fc)
                ->setEaseFactor(2.5)->setIntervalDays(1)->setRepetitions(0)
                ->setDueAt(new \DateTimeImmutable('today')));
        }

        $manager->flush();

        // ===== 8. NOTIFICATIONS =====

        foreach ([
            [$alice, 'info', 'Nouveau post dans votre groupe', "Bob a publié un post dans 'Groupe Maths Terminale'", '/app/groupes/1'],
            [$alice, 'success', 'Score parfait !', "Vous avez obtenu 100% au quiz 'Intégrales — Test rapide'", '/fo/training/quizzes/history'],
            [$bob, 'info', 'Invitation reçue', "Vous avez été invité à rejoindre 'Chimie Organique Avancée'", '/app/groupes/3'],
            [$bob, 'success', 'Nouveau badge débloqué', "Vous avez obtenu le badge 'Premier Quiz' !", '/fo/profile'],
            [$charlie, 'warning', 'Révision en retard', "3 flashcards sont en retard dans 'Formules Mathématiques'", '/fo/training/decks/1'],
        ] as [$user, $type, $title, $message, $link]) {
            $manager->persist((new Notification())
                ->setUser($user)->setType($type)
                ->setTitle($title)->setMessage($message)
                ->setLink($link)->setIsRead(false));
        }

        $manager->flush();

        // ===== 9. CERTIFICATION REQUEST =====

        $certRequest = (new TeacherCertificationRequest())
            ->setUser($alice)
            ->setStatus('pending')
            ->setMotivation("Je souhaite devenir enseignante pour partager mes connaissances en mathématiques.");
        $manager->persist($certRequest);

        $manager->flush();

        // ===== 10. AI MODEL + GENERATION LOGS =====

        $aiModel = (new AiModel())
            ->setName('studysprint-ai-v1')
            ->setProvider('fastapi')
            ->setBaseUrl('http://localhost:8001')
            ->setIsDefault(true);
        $manager->persist($aiModel);
        $manager->flush();

        $logs = [
            [$alice, 'quiz', 'Génère 3 questions pour Mathématiques', ['subject_id' => 1], 'success', 12400, 4],
            [$bob, 'flashcard', 'Génère 5 flashcards pour Physique Quantique', ['subject_id' => 2], 'success', 8900, 5],
            [$alice, 'profile', 'Améliore la bio de Alice', ['user_id' => 2], 'success', 5200, null],
            [$prof, 'summary', 'Résumé du chapitre Suites et séries', ['chapter_id' => 1], 'success', 6800, 4],
            [$alice, 'planning_suggest', 'Optimise le plan Maths', ['plan_id' => 1], 'success', 9500, null],
            [$bob, 'quiz', 'Génère un quiz complexe niveau HARD', ['subject_id' => 3, 'num' => 20], 'failed', 120000, null],
        ];

        foreach ($logs as [$user, $feature, $prompt, $input, $status, $latency, $feedback]) {
            $log = (new AiGenerationLog())
                ->setUser($user)->setModel($aiModel)
                ->setFeature($feature)->setPrompt($prompt)
                ->setInputJson($input)->setStatus($status)
                ->setLatencyMs($latency)
                ->setIdempotencyKey(uniqid('demo_'));
            if ($status === 'failed') {
                $log->setErrorMessage('Gateway timeout after 120s');
            } else {
                $log->setOutputJson(['ok' => true]);
            }
            if ($feedback !== null) {
                $log->setUserFeedback($feedback);
            }
            $manager->persist($log);
        }

        $manager->flush();

        echo "✅ Fixtures chargées avec succès!\n";
        echo "   Comptes disponibles :\n";
        echo "   - admin@studysprint.local / admin123 (ROLE_ADMIN)\n";
        echo "   - alice.martin@studysprint.local / user123 (Student)\n";
        echo "   - bob.dupont@studysprint.local / user123 (Student)\n";
        echo "   - charlie.bernard@studysprint.local / user123 (Student)\n";
        echo "   - prof.claire@studysprint.local / user123 (Professor — Maths)\n";
        echo "   - prof.marc@studysprint.local / user123 (Professor — Physique)\n";
    }
}
