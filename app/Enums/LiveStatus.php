<?php

namespace App\Enums;

enum LiveStatus: string
{
    case LIVE = 'live';
    case PAUSE = 'pause';
    case STOP = 'stop';
}
