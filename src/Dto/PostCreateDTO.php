<?php

namespace App\Dto;

class PostCreateDTO
{
    public ?string $title = null;
    public ?string $body = null;
    public ?string $postType = 'text'; // text, link, file
    public ?string $attachmentUrl = null;
    public $file = null; // Can be UploadedFile
}
