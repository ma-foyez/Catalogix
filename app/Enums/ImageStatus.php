<?php

namespace App\Enums;

enum ImageStatus: string
{
    case Pending = 'pending';
    case Downloaded = 'downloaded';
    case Failed = 'failed';
}
