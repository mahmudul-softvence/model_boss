<?php

namespace App\Enums;

use App\Models\User;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case USER = 'user';
    case ARTIST = 'artist';
}
