<?php

namespace App\Models;

class SupportMessage extends CentralModel
{
    protected $fillable = ['ticket_id', 'author_type', 'author_name', 'body'];
}
