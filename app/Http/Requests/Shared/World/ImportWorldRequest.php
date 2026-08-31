<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Enums\Landlord\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Validate a World spreadsheet upload.
 */
class ImportWorldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::WorldManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Spreadsheet of World records (xlsx, xls, or csv).
             */
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'extensions:xlsx,xls,csv', 'max:10240', 'clamav'],
        ];
    }

    public function uploadedFile(): UploadedFile
    {
        $file = $this->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
