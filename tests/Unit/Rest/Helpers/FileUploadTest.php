<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest\Helpers;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Rest\Helpers\FileUpload;
use Brain\Monkey\Functions;

class FileUploadTest extends TestCase {

    /**
     * Test process_uploads returns empty when no files
     */
    public function testProcessUploadsReturnsEmptyWhenNoFiles(): void {
        $_FILES = [];
        $result = FileUpload::process_uploads(123);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test maybe_set_featured_image only sets for images
     */
    public function testMaybeSetFeaturedImageOnlySetsForImages(): void {
        $setCalled = false;

        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('set_post_thumbnail')->alias(function() use (&$setCalled) {
            $setCalled = true;
            return true;
        });

        // Test with non-image
        FileUpload::maybe_set_featured_image(123, 456, 'application/pdf');
        $this->assertFalse($setCalled);

        // Test with image
        FileUpload::maybe_set_featured_image(123, 456, 'image/jpeg');
        $this->assertTrue($setCalled);
    }

    /**
     * Test maybe_set_featured_image skips if thumbnail exists
     */
    public function testMaybeSetFeaturedImageSkipsIfThumbnailExists(): void {
        $setCalled = false;

        Functions\when('has_post_thumbnail')->justReturn(true);
        Functions\when('set_post_thumbnail')->alias(function() use (&$setCalled) {
            $setCalled = true;
            return true;
        });

        FileUpload::maybe_set_featured_image(123, 456, 'image/jpeg');
        $this->assertFalse($setCalled);
    }

    /**
     * Test get_uploaded_file_names returns empty when no files
     */
    public function testGetUploadedFileNamesReturnsEmptyWhenNoFiles(): void {
        $_FILES = [];

        $result = FileUpload::get_uploaded_file_names();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_uploaded_file_names returns file names
     */
    public function testGetUploadedFileNamesReturnsFileNames(): void {
        $_FILES = [
            'file1' => ['name' => 'document.pdf', 'size' => 1024],
            'file2' => ['name' => 'image.jpg', 'size' => 2048],
            'file3' => ['name' => '', 'size' => 0]
        ];

        $result = FileUpload::get_uploaded_file_names();

        $this->assertCount(2, $result);
        $this->assertContains('document.pdf', $result);
        $this->assertContains('image.jpg', $result);
        $this->assertNotContains('', $result);

        $_FILES = [];
    }

    /**
     * Test maybe_set_featured_image handles various image MIME types
     */
    public function testMaybeSetFeaturedImageHandlesVariousImageTypes(): void {
        $setCalls = [];

        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('set_post_thumbnail')->alias(function($post_id, $attachment_id) use (&$setCalls) {
            $setCalls[] = $attachment_id;
            return true;
        });

        FileUpload::maybe_set_featured_image(123, 1, 'image/jpeg');
        FileUpload::maybe_set_featured_image(123, 2, 'image/png');
        FileUpload::maybe_set_featured_image(123, 3, 'image/gif');
        FileUpload::maybe_set_featured_image(123, 4, 'image/webp');
        FileUpload::maybe_set_featured_image(123, 5, 'text/plain');

        $this->assertCount(4, $setCalls);
        $this->assertContains(1, $setCalls);
        $this->assertContains(2, $setCalls);
        $this->assertContains(3, $setCalls);
        $this->assertContains(4, $setCalls);
        $this->assertNotContains(5, $setCalls);
    }

    /**
     * Test process_uploads skips empty files
     */
    public function testProcessUploadsSkipsEmptyFiles(): void {
        $_FILES = [
            'file1' => ['name' => '', 'size' => 0, 'tmp_name' => '', 'error' => 0, 'type' => ''],
            'file2' => ['name' => 'empty.txt', 'size' => 0, 'tmp_name' => '', 'error' => 0, 'type' => 'text/plain']
        ];

        $result = FileUpload::process_uploads(100);

        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $_FILES = [];
    }

    /**
     * Test get_uploaded_file_names skips files with empty names
     */
    public function testGetUploadedFileNamesSkipsFilesWithEmptyNames(): void {
        $_FILES = [
            'file1' => ['name' => 'valid.txt'],
            'file2' => ['name' => ''],
            'file3' => ['name' => 'another.doc']
        ];

        $result = FileUpload::get_uploaded_file_names();

        $this->assertCount(2, $result);
        $this->assertContains('valid.txt', $result);
        $this->assertContains('another.doc', $result);

        $_FILES = [];
    }

    /**
     * Test maybe_set_featured_image with svg image type
     */
    public function testMaybeSetFeaturedImageWithSvgImage(): void {
        $setCalled = false;

        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('set_post_thumbnail')->alias(function() use (&$setCalled) {
            $setCalled = true;
            return true;
        });

        FileUpload::maybe_set_featured_image(123, 456, 'image/svg+xml');

        // SVG is an image type
        $this->assertTrue($setCalled);
    }
}
