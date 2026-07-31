<?php

namespace App\Enum;

enum UserRole: string
{
    case BUYER = 'BUYER';
    case SELLER = 'SELLER';
    case ADMIN = 'ADMIN';
}
