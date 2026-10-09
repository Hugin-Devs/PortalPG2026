<?php

namespace Tests\Feature;

use Tests\TestCase;

class StorageSymlinkTest extends TestCase
{
    public function test_storage_symlink_points_inside_app_public_storage(): void
    {
        $link = public_path('storage');

        if (! is_link($link)) {
            $this->markTestSkipped('El enlace simbólico public/storage no existe en este entorno.');
        }

        $target = realpath(readlink($link));
        $expected = realpath(storage_path('app/public'));

        $this->assertNotFalse($target);
        $this->assertNotFalse($expected);
        $this->assertStringStartsWith(
            rtrim($expected, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
            rtrim($target, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
            'public/storage debe apuntar únicamente a storage/app/public.'
        );
    }
}
