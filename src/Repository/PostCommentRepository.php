<?php

namespace App\Repository;

use App\Entity\GroupPost;
use App\Entity\PostComment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostComment>
 */
class PostCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostComment::class);
    }

    /**
     * Count total comments for a post (including replies)
     */
    public function countByPost(GroupPost $post): int
    {
        return $this->count(['post' => $post]);
    }

    /**
     * Find all comments for a post ordered by creation date
     * Returns only top-level comments (no parent)
     */
    /** @return list<PostComment> */ public function findTopLevelByPost(GroupPost $post): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.post = :post')
            ->andWhere('c.parentComment IS NULL')
            ->setParameter('post', $post)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find replies to a comment
     */
    /** @return list<PostComment> */ public function findRepliesByComment(PostComment $comment): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parentComment = :parent')
            ->setParameter('parent', $comment)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all comments with their replies for a post
     */
    /** @return list<PostComment> */ public function findByPostWithReplies(GroupPost $post): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.post = :post')
            ->setParameter('post', $post)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all comments for BO, ordered by most recent, with post/author/group loaded.
     */
    /** @return list<PostComment> */ public function findAllOrderByCreatedAtDesc(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.post', 'p')
            ->addSelect('p')
            ->innerJoin('c.author', 'author')
            ->addSelect('author')
            ->innerJoin('p.group', 'g')
            ->addSelect('g')
            ->leftJoin('p.author', 'postAuthor')
            ->addSelect('postAuthor')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find comments on posts authored by a given user (feedback received), ordered by most recent.
     */
    /** @return list<PostComment> */ public function findByPostAuthorOrderByCreatedAtDesc(\App\Entity\User $author, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.post', 'p')
            ->addSelect('p')
            ->innerJoin('c.author', 'commentAuthor')
            ->addSelect('commentAuthor')
            ->innerJoin('p.group', 'g')
            ->addSelect('g')
            ->where('p.author = :author')
            ->andWhere('c.author != :author')
            ->setParameter('author', $author)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
