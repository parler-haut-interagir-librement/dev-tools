<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A Doctrine mapping keeps no decimal column.
 *
 * Expected identifier: `phil.doctrineDecimal`
 */
#[ORM\Entity]
final class DoctrineDecimal
{
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $price = '0';
}
