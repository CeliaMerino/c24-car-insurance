<?php

declare(strict_types=1);

namespace App\Tests\Domain\Offer;

use App\Domain\Offer\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Test]
    public function add_returns_sum_in_cents(): void
    {
        $total = (new Money(47800))->add(new Money(9100));

        self::assertSame(56900, $total->cents());
    }

    #[Test]
    public function subtract_returns_difference_in_cents(): void
    {
        $difference = (new Money(56900))->subtract(new Money(8540));

        self::assertSame(48360, $difference->cents());
    }

    #[Test]
    public function applyDiscountPercentage_rounds_half_up_to_nearest_cent(): void
    {
        $discounted = (new Money(56900))->applyDiscountPercentage(15);

        self::assertSame(48365, $discounted->cents());
    }

    #[Test]
    public function applyDiscountPercentage_at_zero_leaves_amount_unchanged(): void
    {
        $discounted = (new Money(47800))->applyDiscountPercentage(0);

        self::assertSame(47800, $discounted->cents());
    }

    #[Test]
    public function applyDiscountPercentage_at_one_hundred_yields_zero(): void
    {
        $discounted = (new Money(47800))->applyDiscountPercentage(100);

        self::assertSame(0, $discounted->cents());
    }

    #[Test]
    public function compareTo_orders_by_cents(): void
    {
        self::assertSame(-1, (new Money(47800))->compareTo(new Money(56900)));
        self::assertSame(1, (new Money(56900))->compareTo(new Money(47800)));
        self::assertSame(0, (new Money(47800))->compareTo(new Money(47800)));
    }

    #[Test]
    public function rejects_negative_amounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Money(-1);
    }
}
