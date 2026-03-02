<?php

namespace App\Service;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class QrCodeService
{
    private PngWriter $writer;

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
        $this->writer = new PngWriter();
    }

    public function generateForRoute(string $routeName, array $params = []): string
    {
        $url = $this->urlGenerator->generate($routeName, $params, UrlGeneratorInterface::ABSOLUTE_URL);
        return $this->generateForUrl($url);
    }

    public function generateForUrl(string $url): string
    {
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 250,
            margin: 10,
        );

        $result = $this->writer->write($qrCode);
        return $result->getDataUri();
    }

    public function generateResponse(string $url): Response
    {
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 10,
        );

        $result = $this->writer->write($qrCode);

        return new Response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
        ]);
    }
}
