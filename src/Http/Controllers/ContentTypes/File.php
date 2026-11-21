<?php

namespace TCG\Voyager\Http\Controllers\ContentTypes;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class File extends BaseType
{
    /**
     * Extensões aceitas quando config('voyager.storage.allowed_file_extensions')
     * não está definida. Fora daqui: html, svg, xml, js e afins, que o
     * navegador executaria na mesma origem do painel.
     */
    public const DEFAULT_ALLOWED_EXTENSIONS = [
        'pdf', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        'zip', 'rar', '7z', 'mp3', 'mp4', 'ogg', 'wav',
    ];

    /**
     * @return string
     */
    public function handle()
    {
        if (!$this->request->hasFile($this->row->field)) {
            return;
        }

        $files = Arr::wrap($this->request->file($this->row->field));

        $filesPath = [];
        $path = $this->generatePath();

        foreach ($files as $file) {
            $extension = $this->extensionOf($file);
            $filename = $this->generateFileName($file, $path, $extension);
            $file->storeAs(
                $path,
                $filename.'.'.$extension,
                config('voyager.storage.disk', 'public')
            );

            array_push($filesPath, [
                'download_link' => $path.$filename.'.'.$extension,
                'original_name' => $file->getClientOriginalName(),
            ]);
        }

        return json_encode($filesPath);
    }

    /**
     * Extensão pelo MIME detectado no conteúdo, não pelo nome enviado: um
     * .html renomeado para .pdf continua html e um .html com conteúdo de PDF
     * vira .pdf. Fora da allowlist, o upload é recusado.
     *
     * @return string
     */
    protected function extensionOf($file)
    {
        $extension = strtolower((string) $file->guessExtension());
        $allowed = array_map('strtolower', (array) config(
            'voyager.storage.allowed_file_extensions',
            self::DEFAULT_ALLOWED_EXTENSIONS
        ));

        if ($extension === '' || !in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                $this->row->field => __('voyager::generic.mimetype_not_allowed'),
            ]);
        }

        return $extension;
    }

    /**
     * @return string
     */
    protected function generatePath()
    {
        return $this->slug.DIRECTORY_SEPARATOR.date('FY').DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     */
    protected function generateFileName($file, $path, $extension = null)
    {
        $extension = $extension ?? $this->extensionOf($file);
        $disk = Storage::disk(config('voyager.storage.disk'));

        if (isset($this->options->preserveFileUploadName) && $this->options->preserveFileUploadName) {
            $base = basename($file->getClientOriginalName(), '.'.$file->getClientOriginalExtension());
            $filename = $base;
            $filename_counter = 1;

            // Make sure the filename does not exist, if it does make sure to add a number to the end 1, 2, 3, etc...
            while ($disk->exists($path.$filename.'.'.$extension)) {
                $filename = $base.(string) ($filename_counter++);
            }
        } else {
            $filename = Str::random(20);

            // Make sure the filename does not exist, if it does, just regenerate
            while ($disk->exists($path.$filename.'.'.$extension)) {
                $filename = Str::random(20);
            }
        }

        return $filename;
    }
}
