<?php

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class QrCodeService
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function generateForRoute(string $routeName, array $params = []): string
    {
        $url = $this->urlGenerator->generate($routeName, $params, UrlGeneratorInterface::ABSOLUTE_URL);
        return $this->generateForUrl($url);
    }

    public function generateForUrl(string $url): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(250)
            ->margin(10)
            ->build();

        return $result->getDataUri();
    }

    public function generateResponse(string $url): Response
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(300)
            ->margin(10)
            ->build();

        return new Response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
        ]);
    }
}
