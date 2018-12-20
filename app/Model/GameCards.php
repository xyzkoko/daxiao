<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class GameCards extends Model
{
    protected $table = 'game_cards';

    public $keyType = 'string';
    public $incrementing = false;
}
