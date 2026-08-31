<?php

declare(strict_types=1);

namespace App\Domain\Offer;

final class OfferSorter
{
    /**
     * @param list<Offer> $offers
     *
     * @return list<Offer>
     */
    public static function sortByFinalPrice(array $offers): array
    {
        $sorted = $offers;

        usort(
            $sorted,
            static function (Offer $a, Offer $b): int {
                $priceCompare = $a->finalPrice->compareTo($b->finalPrice);

                if ($priceCompare !== 0) {
                    return $priceCompare;
                }

                return strcmp($a->partnerId->value, $b->partnerId->value);
            },
        );

        return $sorted;
    }
}
