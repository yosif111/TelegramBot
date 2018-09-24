<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'message';
    protected $primaryKey = 'update_id';

    protected $fillable = [
        'update_id', 'user_id', 'message_id', 'chat_id', 'text', 'handled'
    ];
}
