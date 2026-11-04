<?php

namespace Tests\Unit;

use App\Services\InstagramImport;
use PHPUnit\Framework\TestCase;

class InstagramImportTest extends TestCase
{
    public function test_normalize_accepts_post_reel_and_tv_urls(): void
    {
        $this->assertSame(
            'https://www.instagram.com/p/ABC123xyz/',
            InstagramImport::normalizePostUrl('https://www.instagram.com/p/ABC123xyz/')
        );
        $this->assertSame(
            'https://www.instagram.com/reel/XYZ789/',
            InstagramImport::normalizePostUrl('https://www.instagram.com/reel/XYZ789/?igsh=token')
        );
        $this->assertSame(
            'https://www.instagram.com/tv/AAAA1111/',
            InstagramImport::normalizePostUrl('http://instagr.am/tv/AAAA1111')
        );
        $this->assertSame(
            'https://www.instagram.com/p/ABC/',
            InstagramImport::normalizePostUrl('  https://www.instagram.com/p/ABC  ')
        );
    }

    public function test_normalize_rejects_non_post_urls(): void
    {
        $this->assertNull(InstagramImport::normalizePostUrl(''));
        $this->assertNull(InstagramImport::normalizePostUrl('not-a-url'));
        $this->assertNull(InstagramImport::normalizePostUrl('https://example.com/p/ABC'));
        $this->assertNull(InstagramImport::normalizePostUrl('https://www.instagram.com/someuser/'));
        $this->assertNull(InstagramImport::normalizePostUrl('https://www.instagram.com/explore/tags/foo/'));
        $this->assertNull(InstagramImport::normalizePostUrl('ftp://www.instagram.com/p/ABC'));
        $this->assertNull(InstagramImport::normalizePostUrl('https://user:pass@www.instagram.com/p/ABC'));
        $this->assertNull(InstagramImport::normalizePostUrl('https://www.instagram.com/p/ABC/extra'));
        $this->assertNull(InstagramImport::normalizePostUrl('javascript:alert(1)'));
    }

    public function test_image_allowlist_requires_https_meta_cdn_hosts(): void
    {
        $this->assertTrue(InstagramImport::isAllowedImageUrl(
            'https://scontent.cdninstagram.com/v/t51.2885-15/123.jpg'
        ));
        $this->assertTrue(InstagramImport::isAllowedImageUrl(
            'https://scontent-gru1-1.cdninstagram.com/v/t51.2885-15/123.jpg?_nc_cat=1'
        ));
        $this->assertTrue(InstagramImport::isAllowedImageUrl(
            'https://scontent.xx.fbcdn.net/v/t51.2885-15/123.jpg'
        ));
    }

    public function test_image_allowlist_blocks_ssrf_vectors(): void
    {
        $this->assertFalse(InstagramImport::isAllowedImageUrl('http://scontent.cdninstagram.com/a.jpg'));
        $this->assertFalse(InstagramImport::isAllowedImageUrl('https://evil.com/a.jpg'));
        $this->assertFalse(InstagramImport::isAllowedImageUrl('https://cdninstagram.com.evil.com/a.jpg'));
        $this->assertFalse(InstagramImport::isAllowedImageUrl('https://evilcdninstagram.com/a.jpg'));
        $this->assertFalse(InstagramImport::isAllowedImageUrl('https://127.0.0.1/a.jpg'));
        $this->assertFalse(InstagramImport::isAllowedImageUrl('https://169.254.169.254/latest/meta-data'));
        $this->assertFalse(InstagramImport::isAllowedImageUrl('https://user:pass@scontent.cdninstagram.com/a.jpg'));
        $this->assertFalse(InstagramImport::isAllowedImageUrl('file:///etc/passwd'));
        $this->assertFalse(InstagramImport::isAllowedImageUrl(''));
    }

    public function test_fetch_meta_returns_null_for_invalid_url_without_http(): void
    {
        // No Http::fake: una URL inválida debe cortar antes de tocar la red.
        $this->assertNull((new InstagramImport)->fetchMeta('https://evil.com/p/x'));
        $this->assertNull((new InstagramImport)->fetchMeta('not-a-url'));
    }
}
