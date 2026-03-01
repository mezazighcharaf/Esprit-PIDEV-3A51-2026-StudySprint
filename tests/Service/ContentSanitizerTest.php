<?php

namespace App\Tests\Service;

use App\Service\ContentSanitizer;
use PHPUnit\Framework\TestCase;

class ContentSanitizerTest extends TestCase
{
    private ContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new ContentSanitizer();
    }

    public function testSanitizeRichAllowsSafeTags(): void
    {
        $input = '<p>Hello <strong>world</strong>!</p><script>alert("xss")</script>';
        $expected = '<p>Hello <strong>world</strong>!</p>';
        $this->assertEquals($expected, $this->sanitizer->sanitizeRich($input));
    }

    public function testSanitizeRichStripsDangerousAttributes(): void
    {
        $input = '<a href="javascript:alert(1)" onclick="bad()">Click me</a>';
        // Now it should correctly preserve the <a> tag but clean attributes
        $this->assertEquals('<a href="#">Click me</a>', $this->sanitizer->sanitizeRich($input));
    }

    public function testSanitizePlainStripsEverythingExceptBasicFormatting(): void
    {
        $input = '<div>Title</div><p>Para <strong>Bold</strong> <em>Italic</em></p>';
        $expected = 'Title<p>Para <strong>Bold</strong> <em>Italic</em></p>';
        $this->assertEquals($expected, $this->sanitizer->sanitizePlain($input));
    }

    public function testEscapeText(): void
    {
        $input = 'Hello < & > "World"';
        $expected = 'Hello &lt; &amp; &gt; "World"';
        $this->assertEquals($expected, $this->sanitizer->escapeText($input));
    }

    public function testCleanWhitespace(): void
    {
        $input = "<p>Line 1</p>\n\n   <p>Line 2</p>  ";
        $expected = "<p>Line 1</p>\n<p>Line 2</p>";
        $this->assertEquals($expected, $this->sanitizer->sanitizeRich($input));
    }
}
