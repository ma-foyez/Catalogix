<?php

namespace App\Http\Resources;

use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Export
 */
class ExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scrape_job_id' => $this->scrape_job_id,
            'scope' => $this->scope,
            'format' => $this->format->value,
            'format_label' => $this->format->label(),
            'layout' => $this->layout->value,
            'status' => $this->status->value,
            'file_name' => $this->file_path ? $this->fileName() : null,
            'bytes' => $this->bytes,
            'row_count' => $this->row_count,
            'error' => $this->error,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => $this->downloadUrl($request->is('api/*') ? 'api.exports.download' : 'exports.download'),
        ];
    }
}
