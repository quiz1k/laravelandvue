<?php

namespace App\Models;

use App\Http\Controllers\CommentController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'text_en',
        'text_ru',
        'text_uk',
        'image',
    ];

    public function comment() {
        return $this->hasMany(Comment::class, 'post_id');
    }
}
