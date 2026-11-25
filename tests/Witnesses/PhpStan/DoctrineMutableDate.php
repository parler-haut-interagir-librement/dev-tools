<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A Doctrine mapping keeps no mutable date type.
 *
 * Expected identifier: `phil.doctrineMutableDate`
 */
#[ORM\Entity]
final class DoctrineMutableDate
{
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeImmutable $at;
}
