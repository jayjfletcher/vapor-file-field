<?php

namespace Jayjfletcher\VaporFileField;

use Laravel\Nova\Fields\File as BaseFile;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Support\Facades\Storage;

class File extends BaseFile
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'vapor-file-field';

    /**
     * Target path for storage.
     *
     * @var string
     */
    public $targetFilePath;

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  string  $requestAttribute
     * @param  object  $model
     * @param  string  $attribute
     * @return mixed
     */
    protected function fillAttribute(NovaRequest $request, $requestAttribute, $model, $attribute)
    {
        if (!$request->input($this->attribute.'.uuid')) {
            return;
        }

        //Vapor is s3 only
        $this->setDisk('s3');

        $result = call_user_func($this->storageCallback, $request, $model);

        if ($result === true) {
            return;
        }

        if (! is_array($result)) {
            return $model->{$attribute} = $result;
        }

        foreach ($result as $key => $value) {
            $model->{$key} = $value;
        }

        if ($this->isPrunable()) {
            return function () use ($model, $request) {
                call_user_func(
                    $this->deleteCallback,
                    $request,
                    $model,
                    $this->getStorageDisk(),
                    $this->getStoragePath()
                );
            };
        }
    }

    /**
     * Store the file on disk.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|false
     */
    protected function storeFile($request)
    {
        $success = Storage::disk($this->disk)->copy(
            $request->input($this->attribute.'key'),
            $this->getTargetPath($request)
        );

        return $success ? $this->getTargetPath($request) : false;
    }

    /**
     * Merge the specified extra file information columns into the storable attributes.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $attributes
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     * @return array
     */
    protected function mergeExtraStorageColumns($request, array $attributes)
    {
        if ($this->originalNameColumn) {
            $attributes[$this->originalNameColumn] = $request->input($this->attribute.'.originalName');
        }

        if ($this->sizeColumn) {
            $attributes[$this->sizeColumn] = Storage::disk($this->disk)->size($this->getTargetPath($request));
        }

        return $attributes;
    }

    /**
     * Gets the files target path.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return string
     */
    protected function getTargetPath($request)
    {
        if($this->targetFilePath)
        {
            return $this->targetFilePath;
        }

        if($this->storeAsCallback)
        {
            return $this->targetFilePath = str_replace('tmp/', $this->storagePath, call_user_func($this->storeAsCallback, $request));
        }

        return $this->targetFilePath = str_replace('tmp/', $this->storagePath, $request->input($this->attribute.'s3Key'));

    }


}
