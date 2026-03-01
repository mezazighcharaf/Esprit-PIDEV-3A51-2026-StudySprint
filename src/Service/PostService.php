<?php

namespace App\Service;

use App\Dto\PostCreateDTO;
use App\Entity\GroupPost;
use App\Entity\StudyGroup;
use App\Entity\User;
use App\Enum\GroupRole;
use App\Repository\GroupMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PostService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GroupMemberRepository $memberRepository,
    ) {}

    /**
     * Create a new post in a group
     */
    public function createPost(StudyGroup $group, User $author, PostCreateDTO $dto): GroupPost
    {
        // Check if user is a member
        if (!$this->isMember($group, $author)) {
            throw new AccessDeniedHttpException('Vous devez être membre du groupe pour publier.');
        }

        $post = new GroupPost();
        $post->setGroup($group);
        $post->setAuthor($author);
        $post->setTitle($dto->title);
        $post->setBody($dto->body ?? '');
        $post->setPostType($dto->postType ?? 'text');
        
        // Handle file upload
        if ($dto->file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $uploadsDir = __DIR__ . '/../../public/uploads/groups/posts';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0775, true);
            }
            $originalName = pathinfo($dto->file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $dto->file->guessExtension() ?? $dto->file->getClientOriginalExtension();
            $filename = $originalName . '-' . uniqid() . '.' . $extension;
            $dto->file->move($uploadsDir, $filename);
            $post->setAttachmentUrl('/uploads/groups/posts/' . $filename);
        } else {
            $post->setAttachmentUrl($dto->attachmentUrl);
        }

        $group->setLastActivity(new \DateTimeImmutable());

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }

    /**
     * Delete a post
     */
    public function deletePost(GroupPost $post, User $user): void
    {
        if (!$this->canDeletePost($post, $user)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas la permission de supprimer ce post.');
        }

        $this->entityManager->remove($post);
        $this->entityManager->flush();
    }

    /**
     * Check if user can delete a post
     */
    public function canDeletePost(GroupPost $post, User $user): bool
    {
        // Author can delete their own post
        $author = $post->getAuthor();
        if ($author && $author->getId() === $user->getId()) {
            return true;
        }

        // Group admin can delete any post
        $group = $post->getGroup();
        if (!$group) return false;

        $membership = $this->memberRepository->findOneBy([
            'group' => $group,
            'user' => $user
        ]);

        if (!$membership) {
            return false;
        }

        $role = GroupRole::tryFromString($membership->getMemberRole());
        return $role !== null && $role->canDeleteAnyPost();
    }

    /**
     * Check if user is a member of the group
     */
    private function isMember(StudyGroup $group, User $user): bool
    {
        return $this->memberRepository->findOneBy([
            'group' => $group,
            'user' => $user
        ]) !== null;
    }
}
