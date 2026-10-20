<?php

namespace App\Http\Controllers;

use App\Enums\ImageStatus;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController extends Controller
{
    public function download(Request $request, ProductImage $image): StreamedResponse
    {
        abort_unless($image->product->scrapeTargets()
            ->whereHas('scrapeJob', fn ($query) => $query->where('user_id', $request->user()->id))->exists(), 403);
        abort_unless($image->status === ImageStatus::Downloaded && $image->local_path !== null, 409, 'This picture has not finished downloading.');

        $disk = Storage::disk((string) config('catalogix.images.disk', 'public'));
        abort_unless($disk->exists($image->local_path), 404, 'The saved picture is no longer available.');
        $name = (Str::slug($image->product->name) ?: 'product').'-'.$image->product_id.'.'.pathinfo($image->local_path, PATHINFO_EXTENSION);

        return $disk->download($image->local_path, $name);
    }
}
