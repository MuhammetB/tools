<?php

namespace App\Trait;

use App\Models\Media;
use Carbon\Carbon;

trait HasMedia
{
    function upload_media($request, $privet = true)
    {
        $uploadedFile = $request->file('media');
        $extension = $uploadedFile->getClientOriginalExtension();
        $folderName = 'uploads/image/' . Carbon::now()->format('Y-m-d') . '/' . time() . uniqid(0, 0);
        $filename = $uploadedFile->getClientOriginalName();
        $this->path = "$folderName.$extension";
        $this->original_name = $uploadedFile->getClientOriginalName();
        $this->extension = $uploadedFile->getClientOriginalExtension();
        $this->size = $uploadedFile->getSize();
        $this->privet = $privet;
        $this->save();
        return $this;
    }

    function update_media($request)
    {
        $this->title = $request['title'];
        $this->description = $request['description'];
//        $media->translate($request);
    }

    function relate_media($media_id, $key)
    {
        $this->$key = $media_id;
    }

}
