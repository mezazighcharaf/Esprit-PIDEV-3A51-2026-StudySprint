<?php

namespace App\Service;

use App\Dto\PostCreateDTO;
use App\Entity\GroupPost;
use App\Entity\StudyGroup;
use App\Entity\User;
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
            $uploadsDir = 'uploads/groups/posts';
            $filename = uniqid() . '.' . $dto->file->guessExtension();
            $dto->file->move($uploadsDir, $filename);
            $post->setAttachmentUrl('/' . $uploadsDir . '/' . $filename);
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
        if ($post->getAuthor()->getId() === $user->getId()) {
            return true;
        }

        // Group admin can delete any post
        $membership = $this->memberRepository->findOneBy([
            'group' => $post->getGroup(),
            'user' => $user
        ]);

        return $membership && $membership->getMemberRole() === 'admin';
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
