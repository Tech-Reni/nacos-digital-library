<?php

namespace App\Enums;

/**
 * How a book file entered the system.
 */
enum BookSource: string
{
    case Pdf = 'pdf';
    case Scan = 'scan';
}
