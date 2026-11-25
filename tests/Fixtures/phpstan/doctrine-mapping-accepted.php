<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class AcceptedEvent
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'date_immutable')]
    private ?DateTimeImmutable $day = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $priceInCents = 0;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $amount = '0';

    #[ORM\Column('label', Types::STRING)]
    private string $label = '';

    public function __construct(
        #[ORM\Column(type: Types::TIME_IMMUTABLE)]
        private DateTimeImmutable $at,
        private readonly string $plain = '',
    ) {
        $this->startsAt = $at;
    }
}

/**
 * Not mapped: a \DateTime that Doctrine never sees.
 */
final class NotMapped
{
    public function __construct(public DateTime $when, private string $type = 'decimal')
    {
    }
}
