<?php

namespace App\Dto;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class PostCreateDTO
{
    #[Assert\Length(
        max: 200,
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $title = null;

    #[Assert\When(
        expression: 'this.postType == "text"',
        constraints: [new Assert\NotBlank(message: 'Le contenu du post est requis')]
    )]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Le contenu ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $body = null;

    #[Assert\NotBlank(message: 'Le type de post est requis')]
    #[Assert\Choice(
        choices: ['text', 'link', 'file'],
        message: 'Type de post invalide'
    )]
    public ?string $postType = 'text';

    #[Assert\When(
        expression: 'this.attachmentUrl !== null and this.attachmentUrl !== ""',
        constraints: [new Assert\Url(message: 'L\'URL fournie n\'est pas valide')]
    )]
    public ?string $attachmentUrl = null;

    #[Assert\File(
        maxSize: '10M',
        maxSizeMessage: 'Le fichier est trop volumineux ({{ size }} {{ suffix }}). La taille maximale autorisée est {{ limit }} {{ suffix }}.',
        mimeTypes: [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ],
        mimeTypesMessage: 'Type de fichier non autorisé. Types acceptés : PDF, images, documents Word/Excel, texte.'
    )]
    public ?UploadedFile $file = null;
}
