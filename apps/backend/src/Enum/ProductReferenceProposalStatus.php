<?php

declare(strict_types=1);

namespace App\Enum;

enum ProductReferenceProposalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Merged = 'merged';
}
