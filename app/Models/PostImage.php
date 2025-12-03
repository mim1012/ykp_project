<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'filename',
        'original_name',
        'path',
        'url',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    /**
     * Get the parent imageable model (QnaPost or NoticePost)
     */
    public function imageable()
    {
        return $this->morphTo();
    }

    /**
     * Get the uploader
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
