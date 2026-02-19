<?php

namespace App\Service;

class QuizTemplateService
{
    private const TEMPLATES = [
        'qcm_basic' => [
            'name' => 'QCM Classique',
            'description' => 'Questions à choix multiples avec 4 réponses possibles',
            'icon' => '📝',
            'questionCount' => 10,
            'structure' => [
                'type' => 'multiple_choice',
                'optionsCount' => 4,
            ],
        ],
        'true_false' => [
            'name' => 'Vrai/Faux',
            'description' => 'Questions simples avec réponse vraie ou fausse',
            'icon' => '✓✗',
            'questionCount' => 15,
            'structure' => [
                'type' => 'true_false',
                'optionsCount' => 2,
            ],
        ],
        'fill_blank' => [
            'name' => 'Texte à trous',
            'description' => 'Compléter les phrases avec les mots manquants',
            'icon' => '📄',
            'questionCount' => 10,
            'structure' => [
                'type' => 'fill_in_blank',
                'optionsCount' => 0,
            ],
        ],
        'short_answer' => [
            'name' => 'Réponse courte',
            'description' => 'Questions nécessitant une réponse textuelle courte',
            'icon' => '✍️',
            'questionCount' => 8,
            'structure' => [
                'type' => 'short_answer',
                'optionsCount' => 0,
            ],
        ],
        'mixed' => [
            'name' => 'Quiz Mixte',
            'description' => 'Mélange de différents types de questions',
            'icon' => '🎯',
            'questionCount' => 12,
            'structure' => [
                'type' => 'mixed',
                'optionsCount' => 'variable',
            ],
        ],
    ];

    public function getAllTemplates(): array
    {
        return self::TEMPLATES;
    }

    public function getTemplate(string $key): ?array
    {
        return self::TEMPLATES[$key] ?? null;
    }

    public function generateEmptyQuestions(string $templateKey): array
    {
        $template = $this->getTemplate($templateKey);
        if (!$template) {
            throw new \InvalidArgumentException("Template '{$templateKey}' introuvable.");
        }

        $questions = [];
        $count = $template['questionCount'];
        $type = $template['structure']['type'];

        for ($i = 0; $i < $count; $i++) {
            $question = [
                'id' => $i + 1,
                'question' => '',
                'type' => $type === 'mixed' ? $this->getRandomQuestionType() : $type,
            ];

            if ($type === 'multiple_choice' || ($type === 'mixed' && $question['type'] === 'multiple_choice')) {
                $question['options'] = ['', '', '', ''];
                $question['correctIndex'] = 0;
            } elseif ($type === 'true_false' || ($type === 'mixed' && $question['type'] === 'true_false')) {
                $question['options'] = ['Vrai', 'Faux'];
                $question['correctIndex'] = 0;
            } elseif ($type === 'short_answer' || $type === 'fill_in_blank') {
                $question['correctAnswer'] = '';
            }

            $questions[] = $question;
        }

        return $questions;
    }

    private function getRandomQuestionType(): string
    {
        $types = ['multiple_choice', 'true_false', 'short_answer'];
        return $types[array_rand($types)];
    }
}
