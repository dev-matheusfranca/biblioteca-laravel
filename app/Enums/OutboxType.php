<?php

namespace App\Enums;

enum OutboxType: string
{
    case ReservationAvailable = 'reservation.available';
    case LoanDueSoon = 'loan.due_soon';
    case LoanOverdue = 'loan.overdue';
}
