<?php

namespace App\Entity;

use App\Repository\ChatbotConfigRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChatbotConfigRepository::class)]
#[ORM\Table(name: 'chatbot_config')]
#[ORM\UniqueConstraint(name: 'uniq_chatbot_group', columns: ['group_id'])]
class ChatbotConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: StudyGroup::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StudyGroup $group = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isEnabled = true;

    #[ORM\Column(length: 50, options: ['default' => 'StudyBot'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $botName = 'StudyBot';

    #[ORM\Column(length: 20, options: ['default' => 'tutor'])]
    #[Assert\Choice(choices: ['tutor', 'assistant', 'mentor', 'quiz-master'])]
    private string $personality = 'tutor';

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    private ?string $subjectContext = null;

    #[ORM\Column(length: 20, options: ['default' => 'mention'])]
    #[Assert\Choice(choices: ['mention', 'auto-detect', 'keyword'])]
    private string $triggerMode = 'auto-detect';

    #[ORM\Column(type: 'json')]
    private array $triggerKeywords = ['@studybot', '@bot'];

    #[ORM\Column(type: 'integer', options: ['default' => 500])]
    private int $maxResponseLength = 500;

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    private string $language = 'fr';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): ?StudyGroup
    {
        return $this->group;
    }

    public function setGroup(StudyGroup $group): static
    {
        $this->group = $group;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): static
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    public function getBotName(): string
    {
        return $this->botName;
    }

    public function setBotName(string $botName): static
    {
        $this->botName = $botName;
        return $this;
    }

    public function getPersonality(): string
    {
        return $this->personality;
    }

    public function setPersonality(string $personality): static
    {
        $this->personality = $personality;
        return $this;
    }

    public function getSubjectContext(): ?string
    {
        return $this->subjectContext;
    }

    public function setSubjectContext(?string $subjectContext): static
    {
        $this->subjectContext = $subjectContext;
        return $this;
    }

    public function getTriggerMode(): string
    {
        return $this->triggerMode;
    }

    public function setTriggerMode(string $triggerMode): static
    {
        $this->triggerMode = $triggerMode;
        return $this;
    }

    public function getTriggerKeywords(): array
    {
        return $this->triggerKeywords;
    }

    public function setTriggerKeywords(array $triggerKeywords): static
    {
        $this->triggerKeywords = $triggerKeywords;
        return $this;
    }

    public function getMaxResponseLength(): int
    {
        return $this->maxResponseLength;
    }

    public function setMaxResponseLength(int $maxResponseLength): static
    {
        $this->maxResponseLength = $maxResponseLength;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
