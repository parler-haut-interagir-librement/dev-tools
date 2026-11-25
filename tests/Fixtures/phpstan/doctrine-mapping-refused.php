<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class RefusedEvent
{
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetime')]
    private DateTimeImmutable $endsAt;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price = '0';

    #[ORM\Column('amount', 'decimal')]
    private string $amount = '0';

    private ?DateTime $updatedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private DateTimeImmutable $day;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private DateTimeImmutable $time;

    #[ORM\Column(type: 'DATETIMETZ')]
    private DateTimeImmutable $zoned;

    public function __construct(
        #[ORM\Column]
        private DateTime $createdAt,
    ) {
    }
}

#[ORM\Embeddable]
final class RefusedPeriod
{
    private DateTime|null $from = null;
}

final class RefusedMappedProperty
{
    #[ORM\Column]
    private ?\DateTime $seen = null;
}
