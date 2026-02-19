<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Entity\Subject;
use App\Entity\Chapter;
use App\Entity\StudyGroup;
use App\Entity\GroupMember;
use App\Entity\GroupPost;
use App\Entity\RevisionPlan;
use App\Entity\PlanTask;
use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\FlashcardDeck;
use App\Entity\Flashcard;
use App\Entity\FlashcardReviewState;
use App\Entity\AiModel;
use App\Entity\AiGenerationLog;
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
        // ===== USERS =====
        $admin = (new User())
            ->setEmail('admin@studysprint.local')
            ->setFullName('Admin StudySprint')
            ->setUserType('ADMIN')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $user1 = (new User())
            ->setEmail('alice.martin@studysprint.local')
            ->setFullName('Alice Martin')
            ->setUserType('STUDENT')
            ->setRoles(['ROLE_USER']);
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'user123'));
        $manager->persist($user1);

        $user2 = (new User())
            ->setEmail('bob.dupont@studysprint.local')
            ->setFullName('Bob Dupont')
            ->setUserType('STUDENT')
            ->setRoles(['ROLE_USER']);
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'user123'));
        $manager->persist($user2);

        $teacher = (new User())
            ->setEmail('prof.claire@studysprint.local')
            ->setFullName('Claire Leroux')
            ->setUserType('TEACHER')
            ->setRoles(['ROLE_USER']);
        $teacher->setPassword($this->passwordHasher->hashPassword($teacher, 'user123'));
        $manager->persist($teacher);

        $manager->flush();

        // User Profiles
        $profile1 = (new UserProfile())
            ->setUser($user1)
            ->setLevel('LICENCE')
            ->setSpecialty('Mathématiques');
        $manager->persist($profile1);

        $profile2 = (new UserProfile())
            ->setUser($user2)
            ->setLevel('MASTER')
            ->setSpecialty('Physique');
        $manager->persist($profile2);

        $manager->flush();

        // ===== 2. SUBJECTS & CHAPTERS =====
        $mathSubject = (new Subject())
            ->setCode('MATH101')
            ->setName('Mathématiques Avancées')
            ->setDescription('Analyse, algèbre linéaire et équations différentielles')
            ->setCreatedBy($teacher);
        $manager->persist($mathSubject);

        $physSubject = (new Subject())
            ->setCode('PHYS201')
            ->setName('Physique Quantique')
            ->setDescription('Mécanique quantique et applications')
            ->setCreatedBy($teacher);
        $manager->persist($physSubject);

        $chemSubject = (new Subject())
            ->setCode('CHEM301')
            ->setName('Chimie Organique')
            ->setDescription('Réactions organiques et mécanismes')
            ->setCreatedBy($admin);
        $manager->persist($chemSubject);

        // Chapters for Math
        $mathChapters = [
            ['Suites et séries', 'Convergence, séries numériques'],
            ['Intégrales', 'Intégration de Riemann et applications'],
            ['Équations différentielles', 'EDO du 1er et 2nd ordre'],
        ];
        foreach ($mathChapters as $idx => $chData) {
            $ch = (new Chapter())
                ->setSubject($mathSubject)
                ->setTitle($chData[0])
                ->setSummary($chData[1])
                ->setOrderNo($idx + 1)
                ->setCreatedBy($teacher);
            $manager->persist($ch);
        }

        // Chapters for Physics
        $physChapters = [
            ['Postulats de la MQ', 'Bases de la mécanique quantique'],
            ['Opérateurs et observables', 'Hamiltonien et mesure'],
            ['Atome d\'hydrogène', 'Résolution exacte'],
        ];
        foreach ($physChapters as $idx => $chData) {
            $ch = (new Chapter())
                ->setSubject($physSubject)
                ->setTitle($chData[0])
                ->setSummary($chData[1])
                ->setOrderNo($idx + 1)
                ->setCreatedBy($teacher);
            $manager->persist($ch);
        }

        // Chapters for Chemistry
        $chemChapters = [
            ['Alcènes et alcynes', 'Réactivité des liaisons multiples'],
            ['Mécanismes réactionnels', 'SN1, SN2, E1, E2'],
        ];
        foreach ($chemChapters as $idx => $chData) {
            $ch = (new Chapter())
                ->setSubject($chemSubject)
                ->setTitle($chData[0])
                ->setSummary($chData[1])
                ->setOrderNo($idx + 1)
                ->setCreatedBy($admin);
            $manager->persist($ch);
        }

        $manager->flush();

        // ===== 3. STUDY GROUPS + MEMBERS + POSTS =====
        $group1 = (new StudyGroup())
            ->setName('Groupe Maths Terminale')
            ->setDescription('Entraide pour préparation bac et concours')
            ->setPrivacy('PUBLIC')
            ->setCreatedBy($user1);
        $manager->persist($group1);

        $group2 = (new StudyGroup())
            ->setName('Physique Prépa')
            ->setDescription('Révisions physique niveau prépa')
            ->setPrivacy('PUBLIC')
            ->setCreatedBy($teacher);
        $manager->persist($group2);

        $group3 = (new StudyGroup())
            ->setName('Chimie Organique Avancée')
            ->setDescription('Questions et exercices de chimie organique')
            ->setPrivacy('PRIVATE')
            ->setCreatedBy($user2);
        $manager->persist($group3);

        $manager->flush();

        // Group Members
        $member1 = (new GroupMember())->setGroup($group1)->setUser($user1)->setMemberRole(GroupMember::ROLE_ADMIN);
        $manager->persist($member1);
        $member2 = (new GroupMember())->setGroup($group1)->setUser($user2)->setMemberRole(GroupMember::ROLE_MEMBER);
        $manager->persist($member2);
        $member3 = (new GroupMember())->setGroup($group2)->setUser($teacher)->setMemberRole(GroupMember::ROLE_ADMIN);
        $manager->persist($member3);
        $member4 = (new GroupMember())->setGroup($group2)->setUser($user1)->setMemberRole(GroupMember::ROLE_MEMBER);
        $manager->persist($member4);
        $member5 = (new GroupMember())->setGroup($group3)->setUser($user2)->setMemberRole(GroupMember::ROLE_ADMIN);
        $manager->persist($member5);

        $manager->flush();

        // Posts for group1
        $post1 = (new GroupPost())
            ->setGroup($group1)
            ->setAuthor($user1)
            ->setTitle('Besoin d\'aide sur les intégrales')
            ->setBody('Bonjour, j\'ai du mal avec l\'intégration par parties. Quelqu\'un peut m\'expliquer la méthode ?')
            ->setPostType(GroupPost::TYPE_POST);
        $manager->persist($post1);

        $comment1 = (new GroupPost())
            ->setGroup($group1)
            ->setAuthor($teacher)
            ->setBody('Bien sûr ! L\'intégration par parties utilise la formule ∫u dv = uv - ∫v du. Tu choisis u et dv judicieusement.')
            ->setPostType(GroupPost::TYPE_COMMENT)
            ->setParentPost($post1);
        $manager->persist($comment1);

        $post2 = (new GroupPost())
            ->setGroup($group1)
            ->setAuthor($user2)
            ->setTitle('Partage: formulaire de dérivées')
            ->setBody('J\'ai créé un formulaire PDF avec toutes les dérivées usuelles. Disponible sur demande !')
            ->setPostType(GroupPost::TYPE_POST);
        $manager->persist($post2);

        // Posts for group2
        $post3 = (new GroupPost())
            ->setGroup($group2)
            ->setAuthor($teacher)
            ->setTitle('Exercice du jour: effet tunnel')
            ->setBody('Calculez la probabilité de transmission d\'une particule à travers une barrière de potentiel.')
            ->setPostType(GroupPost::TYPE_POST);
        $manager->persist($post3);

        $comment2 = (new GroupPost())
            ->setGroup($group2)
            ->setAuthor($user1)
            ->setBody('J\'ai trouvé T ≈ 0.15 avec V0=5eV et E=3eV. Est-ce correct ?')
            ->setPostType(GroupPost::TYPE_COMMENT)
            ->setParentPost($post3);
        $manager->persist($comment2);

        $manager->flush();

        // ===== 4. REVISION PLANS + TASKS =====
        $plan1 = (new RevisionPlan())
            ->setUser($user1)
            ->setSubject($mathSubject)
            ->setTitle('Plan de révision Maths - Examen final')
            ->setStartDate(new \DateTimeImmutable('2026-02-01'))
            ->setEndDate(new \DateTimeImmutable('2026-02-28'))
            ->setStatus(RevisionPlan::STATUS_ACTIVE);
        $manager->persist($plan1);

        $plan2 = (new RevisionPlan())
            ->setUser($user2)
            ->setSubject($physSubject)
            ->setTitle('Révisions Physique Quantique')
            ->setStartDate(new \DateTimeImmutable('2026-02-10'))
            ->setEndDate(new \DateTimeImmutable('2026-02-28'))
            ->setStatus(RevisionPlan::STATUS_ACTIVE);
        $manager->persist($plan2);

        $manager->flush();

        // Tasks for plan1
        $task1 = (new PlanTask())
            ->setPlan($plan1)
            ->setTitle('Réviser suites et séries')
            ->setNotes('Relire le cours et faire les exercices 1 à 10')
            ->setStartAt(new \DateTimeImmutable('2026-02-09 09:00'))
            ->setEndAt(new \DateTimeImmutable('2026-02-09 11:00'))
            ->setStatus(PlanTask::STATUS_TODO);
        $manager->persist($task1);

        $task2 = (new PlanTask())
            ->setPlan($plan1)
            ->setTitle('Exercices d\'intégrales')
            ->setNotes('Compléter la feuille d\'exercices')
            ->setStartAt(new \DateTimeImmutable('2026-02-11 14:00'))
            ->setEndAt(new \DateTimeImmutable('2026-02-11 16:00'))
            ->setStatus(PlanTask::STATUS_TODO);
        $manager->persist($task2);

        $task3 = (new PlanTask())
            ->setPlan($plan1)
            ->setTitle('EDO - Cours et exercices')
            ->setNotes('Chapitre 4')
            ->setStartAt(new \DateTimeImmutable('2026-02-13 10:00'))
            ->setEndAt(new \DateTimeImmutable('2026-02-13 12:00'))
            ->setStatus(PlanTask::STATUS_TODO);
        $manager->persist($task3);

        $task4 = (new PlanTask())
            ->setPlan($plan2)
            ->setTitle('Postulats MQ')
            ->setStartAt(new \DateTimeImmutable('2026-02-16 09:00'))
            ->setEndAt(new \DateTimeImmutable('2026-02-16 10:30'))
            ->setStatus(PlanTask::STATUS_TODO);
        $manager->persist($task4);

        $manager->flush();

        // ===== 5. QUIZZES (using JSON structure) =====
        $quiz1 = (new Quiz())
            ->setOwner($teacher)
            ->setSubject($mathSubject)
            ->setTitle('Intégrales - Test rapide')
            ->setDifficulty(Quiz::DIFFICULTY_MEDIUM)
            ->setQuestions([
                [
                    'question' => 'Quelle est l\'intégrale de x² ?',
                    'type' => 'single',
                    'choices' => ['x³/3 + C', 'x³ + C', '2x + C'],
                    'correct' => 0
                ],
                [
                    'question' => 'Quelle méthode utilise-t-on pour ∫ln(x) dx ?',
                    'type' => 'single',
                    'choices' => ['Intégration par parties', 'Changement de variable', 'Intégration directe'],
                    'correct' => 0
                ]
            ])
            ->setIsPublished(true);
        $manager->persist($quiz1);

        $quiz2 = (new Quiz())
            ->setOwner($teacher)
            ->setSubject($physSubject)
            ->setTitle('Mécanique Quantique - QCM')
            ->setDifficulty(Quiz::DIFFICULTY_HARD)
            ->setQuestions([
                [
                    'question' => 'Quel opérateur correspond à l\'énergie ?',
                    'type' => 'single',
                    'choices' => ['Hamiltonien', 'Impulsion', 'Position'],
                    'correct' => 0
                ]
            ])
            ->setIsPublished(true);
        $manager->persist($quiz2);

        $manager->flush();

        // Quiz Attempts (pour historique FO)
        $attempt1 = (new QuizAttempt())
            ->setUser($user1)
            ->setQuiz($quiz1)
            ->setTotalQuestions(2)
            ->setCorrectCount(2)
            ->setScore(100.0)
            ->setDurationSeconds(180)
            ->setCompletedAt(new \DateTimeImmutable('-2 days'));
        $manager->persist($attempt1);

        $attempt2 = (new QuizAttempt())
            ->setUser($user2)
            ->setQuiz($quiz1)
            ->setTotalQuestions(2)
            ->setCorrectCount(1)
            ->setScore(50.0)
            ->setDurationSeconds(240)
            ->setCompletedAt(new \DateTimeImmutable('-1 day'));
        $manager->persist($attempt2);

        $manager->flush();

        // ===== 6. FLASHCARD DECKS + FLASHCARDS + REVIEW STATES =====
        $deck1 = (new FlashcardDeck())
            ->setOwner($teacher)
            ->setSubject($mathSubject)
            ->setTitle('Formules Mathématiques Essentielles')
            ->setIsPublished(true);
        $manager->persist($deck1);

        $deck2 = (new FlashcardDeck())
            ->setOwner($teacher)
            ->setSubject($physSubject)
            ->setTitle('Constantes et Relations Physiques')
            ->setIsPublished(true);
        $manager->persist($deck2);

        $manager->flush();

        // Flashcards for deck1
        $flashcards1 = [
            ['Dérivée de sin(x)', 'cos(x)'],
            ['Dérivée de cos(x)', '-sin(x)'],
            ['Dérivée de e^x', 'e^x'],
            ['Dérivée de ln(x)', '1/x'],
            ['Intégrale de 1/x', 'ln|x| + C'],
            ['Formule de Taylor ordre 1', 'f(a+h) ≈ f(a) + h·f\'(a)'],
        ];
        foreach ($flashcards1 as $idx => $card) {
            $fc = (new Flashcard())
                ->setDeck($deck1)
                ->setFront($card[0])
                ->setBack($card[1])
                ->setPosition($idx + 1);
            $manager->persist($fc);

            // Review states for user1
            $state = (new FlashcardReviewState())
                ->setUser($user1)
                ->setFlashcard($fc)
                ->setEaseFactor(2.5)
                ->setIntervalDays(1)
                ->setRepetitions(0)
                ->setDueAt(new \DateTimeImmutable('today'));
            $manager->persist($state);
        }

        // Flashcards for deck2
        $flashcards2 = [
            ['Vitesse de la lumière (c)', '3.00 × 10^8 m/s'],
            ['Constante de Planck (h)', '6.626 × 10^-34 J·s'],
            ['Charge élémentaire (e)', '1.602 × 10^-19 C'],
            ['Relation de de Broglie', 'λ = h/p'],
        ];
        foreach ($flashcards2 as $idx => $card) {
            $fc = (new Flashcard())
                ->setDeck($deck2)
                ->setFront($card[0])
                ->setBack($card[1])
                ->setPosition($idx + 1);
            $manager->persist($fc);

            // Review states for user2
            $state = (new FlashcardReviewState())
                ->setUser($user2)
                ->setFlashcard($fc)
                ->setEaseFactor(2.5)
                ->setIntervalDays(1)
                ->setRepetitions(0)
                ->setDueAt(new \DateTimeImmutable('today'));
            $manager->persist($state);
        }

        $manager->flush();

        // ===== 7. AI MODEL + GENERATION LOGS + AI FIELDS =====
        $aiModel = (new AiModel())
            ->setName('vanilj/qwen2.5-14b-instruct-iq4_xs:latest')
            ->setProvider('ollama')
            ->setBaseUrl('http://localhost:11434')
            ->setIsDefault(true);
        $manager->persist($aiModel);
        $manager->flush();

        // AI-generated quiz log (success)
        $logQuiz = (new AiGenerationLog())
            ->setUser($user1)
            ->setModel($aiModel)
            ->setFeature(AiGenerationLog::FEATURE_QUIZ)
            ->setPrompt('Génère 5 questions QCM de niveau intermédiaire pour Mathématiques Avancées')
            ->setInputJson(['user_id' => 2, 'subject_id' => 1, 'num_questions' => 5, 'difficulty' => 'MEDIUM'])
            ->setOutputJson(['quiz_id' => 1, 'questions_count' => 5])
            ->setStatus(AiGenerationLog::STATUS_SUCCESS)
            ->setLatencyMs(12400)
            ->setUserFeedback(4)
            ->setIdempotencyKey('demo_quiz_001');
        $manager->persist($logQuiz);

        // AI-generated flashcard log (success)
        $logFlashcard = (new AiGenerationLog())
            ->setUser($user2)
            ->setModel($aiModel)
            ->setFeature(AiGenerationLog::FEATURE_FLASHCARD)
            ->setPrompt('Génère 10 flashcards pour Physique Quantique - Postulats')
            ->setInputJson(['user_id' => 3, 'subject_id' => 2, 'num_cards' => 10])
            ->setOutputJson(['deck_id' => 2, 'cards_count' => 10])
            ->setStatus(AiGenerationLog::STATUS_SUCCESS)
            ->setLatencyMs(8900)
            ->setUserFeedback(5)
            ->setIdempotencyKey('demo_flashcard_001');
        $manager->persist($logFlashcard);

        // Profile enhancement log (success)
        $logProfile = (new AiGenerationLog())
            ->setUser($user1)
            ->setModel($aiModel)
            ->setFeature(AiGenerationLog::FEATURE_PROFILE)
            ->setPrompt('Génère des suggestions de profil pour un étudiant en Mathématiques')
            ->setInputJson(['user_id' => 2])
            ->setOutputJson([
                'suggested_bio' => 'Étudiante passionnée en mathématiques avancées, je cherche à approfondir mes connaissances en analyse et algèbre.',
                'suggested_goals' => "• Maîtriser les intégrales multiples\n• Obtenir une note ≥ 16/20 à l'examen final\n• Résoudre 50 exercices par semaine",
                'suggested_routine' => "Matin (8h-10h) : Cours théorique\nAprès-midi (14h-16h) : Exercices pratiques\nSoir (20h-21h) : Flashcards SM-2",
            ])
            ->setStatus(AiGenerationLog::STATUS_SUCCESS)
            ->setLatencyMs(5200)
            ->setIdempotencyKey('demo_profile_001');
        $manager->persist($logProfile);

        // Chapter summary log (success)
        $logSummary = (new AiGenerationLog())
            ->setUser($teacher)
            ->setModel($aiModel)
            ->setFeature(AiGenerationLog::FEATURE_SUMMARY)
            ->setPrompt('Analyse le chapitre Suites et séries')
            ->setInputJson(['chapter_id' => 1])
            ->setOutputJson([
                'summary' => 'Ce chapitre couvre les suites numériques, leur convergence, et les séries associées.',
                'key_points' => ['Convergence monotone', 'Critère de Cauchy', 'Séries géométriques', 'Séries de Riemann'],
                'tags' => ['suites', 'séries', 'convergence', 'analyse'],
            ])
            ->setStatus(AiGenerationLog::STATUS_SUCCESS)
            ->setLatencyMs(6800)
            ->setUserFeedback(4)
            ->setIdempotencyKey('demo_summary_001');
        $manager->persist($logSummary);

        // Planning suggestion log (success)
        $logPlanning = (new AiGenerationLog())
            ->setUser($user1)
            ->setModel($aiModel)
            ->setFeature(AiGenerationLog::FEATURE_PLANNING_SUGGEST)
            ->setPrompt('Analyse le plan de révision Maths - Examen final')
            ->setInputJson(['plan_id' => 1, 'tasks_count' => 3])
            ->setOutputJson([
                'suggestions' => [
                    ['task_id' => 1, 'action' => 'reschedule', 'reason' => 'Décaler pour éviter la surcharge', 'new_start' => '2026-02-10T09:00:00', 'new_end' => '2026-02-10T11:00:00'],
                ],
                'explanation' => 'Le plan est globalement bon, une tâche gagnerait à être décalée.',
                'can_apply' => true,
            ])
            ->setStatus(AiGenerationLog::STATUS_SUCCESS)
            ->setLatencyMs(9500)
            ->setIdempotencyKey('demo_planning_001');
        $manager->persist($logPlanning);

        // Post summary log (success)
        $logPost = (new AiGenerationLog())
            ->setUser($user1)
            ->setModel($aiModel)
            ->setFeature(AiGenerationLog::FEATURE_POST_SUMMARY)
            ->setPrompt('Analyse le post: Besoin d\'aide sur les intégrales')
            ->setInputJson(['post_id' => 1])
            ->setOutputJson([
                'summary' => 'Demande d\'aide sur la méthode d\'intégration par parties.',
                'category' => 'question',
                'tags' => ['intégrales', 'aide', 'méthode'],
            ])
            ->setStatus(AiGenerationLog::STATUS_SUCCESS)
            ->setLatencyMs(3200)
            ->setIdempotencyKey('demo_post_001');
        $manager->persist($logPost);

        // Failed log for monitoring demo
        $logFailed = (new AiGenerationLog())
            ->setUser($user2)
            ->setModel($aiModel)
            ->setFeature(AiGenerationLog::FEATURE_QUIZ)
            ->setPrompt('Génère un quiz complexe')
            ->setInputJson(['user_id' => 3, 'subject_id' => 3, 'num_questions' => 20])
            ->setStatus(AiGenerationLog::STATUS_FAILED)
            ->setErrorMessage('Ollama timeout after 120s')
            ->setLatencyMs(120000)
            ->setIdempotencyKey('demo_failed_001');
        $manager->persist($logFailed);

        // Set AI fields on existing entities
        $profile1->setAiSuggestedBio('Étudiante passionnée en mathématiques avancées, je cherche à approfondir mes connaissances en analyse et algèbre.');
        $profile1->setAiSuggestedGoals("• Maîtriser les intégrales multiples\n• Obtenir une note ≥ 16/20 à l'examen final\n• Résoudre 50 exercices par semaine");
        $profile1->setAiSuggestedRoutine("Matin (8h-10h) : Cours théorique\nAprès-midi (14h-16h) : Exercices pratiques\nSoir (20h-21h) : Flashcards SM-2");

        $post1->setAiSummary('Demande d\'aide sur la méthode d\'intégration par parties.');
        $post1->setAiCategory('question');
        $post1->setAiTags(['intégrales', 'aide', 'méthode']);

        $manager->flush();

        echo "✅ Fixtures chargées avec succès!\n";
        echo "   - 4 users (admin, alice, bob, prof) + 2 profiles\n";
        echo "   - 3 subjects + 8 chapters\n";
        echo "   - 3 study groups + 5 members + 5 posts/comments\n";
        echo "   - 2 revision plans + 4 tasks\n";
        echo "   - 2 quizzes + 2 attempts (historique)\n";
        echo "   - 2 flashcard decks + 10 flashcards + 10 review states\n";
        echo "   - 1 AI model + 7 generation logs (6 success, 1 failed)\n";
        echo "   - AI fields set on: 1 profile, 1 post\n";
    }
}
