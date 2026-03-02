<?php

namespace App\Service\Planning;

use App\Entity\PlanTask;
use App\Entity\RevisionPlan;
use Psr\Log\LoggerInterface;

class BoMagicBreakdownService
{
    private const DEFAULT_MAX_TASKS = 8;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return list<PlanTask>
     */
    public function generateTasks(RevisionPlan $plan, int $maxTasks = self::DEFAULT_MAX_TASKS): array
    {
        if ($plan->getStatus() === RevisionPlan::STATUS_DONE) {
            throw new \RuntimeException('Le plan est deja termine. Generation impossible.');
        }

        $existingTitles = [];
        foreach ($plan->getTasks() as $task) {
            $existingTitles[mb_strtolower(trim($task->getTitle()))] = true;
        }

        $tasks = [];
        foreach ($this->buildBlueprints($plan, $maxTasks) as $blueprint) {
            $normalizedTitle = mb_strtolower(trim($blueprint['title']));
            if ($normalizedTitle === '' || isset($existingTitles[$normalizedTitle])) {
                continue;
            }

            $existingTitles[$normalizedTitle] = true;
            $startAt = $blueprint['suggestedDate'];
            $endAt = $startAt->modify('+' . $blueprint['durationMinutes'] . ' minutes');

            $task = (new PlanTask())
                ->setPlan($plan)
                ->setTitle($blueprint['title'])
                ->setTaskType($blueprint['taskType'])
                ->setPriority($blueprint['priority'])
                ->setStatus(PlanTask::STATUS_TODO)
                ->setStartAt($startAt)
                ->setEndAt($endAt)
                ->setNotes('[Magic Breakdown BO] ' . $blueprint['notes']);

            $tasks[] = $task;
        }

        $this->logger->info('BO magic breakdown generated tasks.', [
            'plan_id' => $plan->getId(),
            'user_id' => $plan->getUser()->getId(),
            'generated_count' => count($tasks),
        ]);

        return $tasks;
    }

    /**
     * @return list<array{title:string,taskType:string,priority:int,durationMinutes:int,suggestedDate:\DateTimeImmutable,notes:string}>
     */
    private function buildBlueprints(RevisionPlan $plan, int $maxTasks): array
    {
        $maxTasks = max(1, min($maxTasks, self::DEFAULT_MAX_TASKS));

        $context = mb_strtolower(trim($plan->getTitle() . ' ' . $plan->getSubject()->getName()));
        $templates = array_slice($this->selectTemplates($context), 0, $maxTasks);

        $start = $plan->getStartDate()->setTime(9, 0);
        $end = $plan->getEndDate()->setTime(18, 0);
        $daySpan = max(0, (int) $start->diff($end)->format('%a'));

        $count = max(1, count($templates));
        $blueprints = [];

        foreach ($templates as $index => $template) {
            $offsetDays = $count > 1 ? (int) round(($daySpan * $index) / ($count - 1)) : 0;
            $slotHour = 9 + (($index % 3) * 2);
            $suggestedDate = $start->modify('+' . $offsetDays . ' days')->setTime($slotHour, 0);

            if ($suggestedDate > $end) {
                $suggestedDate = $end;
            }

            $blueprints[] = [
                'title' => $template['title'],
                'taskType' => $template['taskType'],
                'priority' => $template['priority'],
                'durationMinutes' => $template['durationMinutes'],
                'suggestedDate' => $suggestedDate,
                'notes' => $template['notes'],
            ];
        }

        return $blueprints;
    }

    /**
     * @return list<array{title:string,taskType:string,priority:int,durationMinutes:int,notes:string}>
     */
    private function selectTemplates(string $context): array
    {
        if (
            str_contains($context, 'revision')
            || str_contains($context, 'examen')
            || str_contains($context, 'cours')
            || str_contains($context, 'quiz')
        ) {
            return [
                [
                    'title' => 'Organiser les supports de cours',
                    'taskType' => PlanTask::TYPE_REVISION,
                    'priority' => 2,
                    'durationMinutes' => 30,
                    'notes' => 'Centraliser cours, annales et supports en un seul espace.',
                ],
                [
                    'title' => 'Creer des fiches active recall',
                    'taskType' => PlanTask::TYPE_FLASHCARD,
                    'priority' => 3,
                    'durationMinutes' => 60,
                    'notes' => 'Transformer les notions en questions/reponses rapides.',
                ],
                [
                    'title' => 'Session exercices cibles',
                    'taskType' => PlanTask::TYPE_QUIZ,
                    'priority' => 3,
                    'durationMinutes' => 90,
                    'notes' => 'Prioriser les chapitres avec les plus gros ecarts.',
                ],
                [
                    'title' => 'Auto evaluation sur points faibles',
                    'taskType' => PlanTask::TYPE_QUIZ,
                    'priority' => 2,
                    'durationMinutes' => 45,
                    'notes' => 'Verifier la retention et ajuster le plan suivant.',
                ],
            ];
        }

        if (
            str_contains($context, 'projet')
            || str_contains($context, 'develop')
            || str_contains($context, 'application')
            || str_contains($context, 'module')
        ) {
            return [
                [
                    'title' => 'Cadrage et planification',
                    'taskType' => PlanTask::TYPE_CUSTOM,
                    'priority' => 2,
                    'durationMinutes' => 45,
                    'notes' => 'Definir scope, jalons et livrables.',
                ],
                [
                    'title' => 'Implementer le socle principal',
                    'taskType' => PlanTask::TYPE_CUSTOM,
                    'priority' => 3,
                    'durationMinutes' => 120,
                    'notes' => 'Construire les fonctionnalites critiques du module.',
                ],
                [
                    'title' => 'Revue technique et tests',
                    'taskType' => PlanTask::TYPE_CUSTOM,
                    'priority' => 3,
                    'durationMinutes' => 60,
                    'notes' => 'Couvrir les cas limites et corriger les regressions.',
                ],
                [
                    'title' => 'Finalisation et documentation',
                    'taskType' => PlanTask::TYPE_CUSTOM,
                    'priority' => 1,
                    'durationMinutes' => 45,
                    'notes' => 'Preparer la livraison et les notes de maintenance.',
                ],
            ];
        }

        return [
            [
                'title' => 'Recherche preliminaire et documentation',
                'taskType' => PlanTask::TYPE_REVISION,
                'priority' => 2,
                'durationMinutes' => 45,
                'notes' => 'Poser les bases de comprehension avant execution.',
            ],
            [
                'title' => 'Execution de la premiere phase',
                'taskType' => PlanTask::TYPE_CUSTOM,
                'priority' => 3,
                'durationMinutes' => 90,
                'notes' => 'Lancer les taches a plus forte valeur.',
            ],
            [
                'title' => 'Revue et corrections',
                'taskType' => PlanTask::TYPE_CUSTOM,
                'priority' => 2,
                'durationMinutes' => 60,
                'notes' => 'Valider la qualite et corriger rapidement.',
            ],
            [
                'title' => 'Session de consolidation',
                'taskType' => PlanTask::TYPE_REVISION,
                'priority' => 1,
                'durationMinutes' => 30,
                'notes' => 'Ancrer les acquis et preparer la suite.',
            ],
        ];
    }
}

