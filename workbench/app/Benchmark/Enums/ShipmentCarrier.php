<?php

namespace App\Benchmark\Enums;

enum ShipmentCarrier: string
{
    case Ups = 'ups';
    case Fedex = 'fedex';
    case Dhl = 'dhl';
}
