<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the MySQL test database (catalogix_test). HTTP and
| processes are faked in every test: no live site and no Python helper is ever hit.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Contents of a saved HTML/JSON fixture from tests/Fixtures (Pest's fixture() gives the path).
 */
function page(string $name): string
{
    return (string) file_get_contents(fixture($name));
}

/**
 * A real PNG of the given size, for image download tests.
 */
function pngImage(int $width = 400, int $height = 400): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 117, 51, 203));

    for ($i = 0; $i < 400; $i++) {
        imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
    }

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}
