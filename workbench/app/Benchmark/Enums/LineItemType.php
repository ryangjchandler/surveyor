<?php

namespace App\Benchmark\Enums;

enum LineItemType: string
{
    case Product = 'product';
    case Fee = 'fee';
    case Discount = 'discount';
}
