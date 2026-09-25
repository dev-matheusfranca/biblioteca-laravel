<?php

namespace App\Enums;

enum ExemplarCondition: string
{
    case Circulation = 'circulacao';
    case Maintenance = 'manutencao';
    case Missing = 'extraviado';
    case Decommissioned = 'baixado';
}
