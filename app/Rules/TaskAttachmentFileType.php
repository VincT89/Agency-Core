<?php

namespace App\Rules;

use App\Support\Uploads\LegacyExcelWorkbook;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

final class TaskAttachmentFileType implements ValidationRule
{
    public function __construct(private readonly array $extensions) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Validator::make(['file' => $value], ['file' => 'file|mimes:'.implode(',', $this->extensions)])->passes()) {
            return;
        }

        if ($value instanceof UploadedFile && $value->isValid()
            && in_array('xls', $this->extensions, true)
            && strtolower($value->getClientOriginalExtension()) === 'xls'
            && in_array($value->getMimeType(), ['application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'], true)) {
            $stream = method_exists($value, 'readStream') ? $value->readStream() : @fopen($value->getRealPath(), 'rb');
            if (is_resource($stream)) {
                try {
                    $contents = stream_get_contents($stream, 15 * 1024 * 1024 + 1);
                    if (is_string($contents) && LegacyExcelWorkbook::matches($contents)) {
                        return;
                    }
                } finally {
                    fclose($stream);
                }
            }
        }

        $fail('Formato non riconosciuto. Sono ammessi PDF, immagini, Word, Excel, CSV, ZIP e TXT.');
    }
}
