<?php

declare(strict_types=1);

namespace App\Simulator\Quote;

use App\Simulator\Clock\SimulatorDate;

/**
 * A quote request as a partner sees it, after the wire payload of
 * specs/05-api-contract.md section 2.1 has been resolved against a reference
 * date. Age and region are resolved here rather than in the pricing engines so
 * that a price is a pure function of this object.
 */
final readonly class QuoteInput
{
    public function __construct(
        public int $ageYears,
        public Region $region,
        public CarCategory $carCategory,
        public Usage $usage,
        public AnnualMileage $annualMileage,
        public bool $garage,
        public CoverageLevel $coverage,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws InvalidQuotePayload
     */
    public static function fromPayload(array $payload, SimulatorDate $today): self
    {
        $postalCode = self::string($payload, 'postal_code');

        if (1 !== preg_match('/^\d{5}$/', $postalCode)) {
            throw new InvalidQuotePayload('Field "postal_code" must be five digits.');
        }

        return new self(
            self::ageInYears(self::string($payload, 'date_of_birth'), $today),
            Region::fromPostalCode($postalCode),
            self::enum(CarCategory::class, $payload, 'car_category'),
            self::enum(Usage::class, $payload, 'usage'),
            self::enum(AnnualMileage::class, $payload, 'annual_mileage'),
            self::bool($payload, 'garage'),
            self::enum(CoverageLevel::class, $payload, 'coverage'),
        );
    }

    /**
     * Stable identity of a priced request, used to key reproducible behaviour
     * off the call rather than off a counter. See
     * `App\Simulator\Behaviour\CallRandomFactory`.
     */
    public function fingerprint(): string
    {
        return implode('|', [
            $this->ageYears,
            $this->region->name,
            $this->carCategory->value,
            $this->usage->value,
            $this->annualMileage->value,
            $this->garage ? 'garage' : 'no_garage',
            $this->coverage->value,
        ]);
    }

    /**
     * @throws InvalidQuotePayload
     */
    private static function ageInYears(string $dateOfBirth, SimulatorDate $today): int
    {
        if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateOfBirth, $parts)) {
            throw new InvalidQuotePayload('Field "date_of_birth" must be a YYYY-MM-DD date.');
        }

        [, $year, $month, $day] = array_map(intval(...), $parts);

        if (!checkdate($month, $day, $year)) {
            throw new InvalidQuotePayload('Field "date_of_birth" is not a real date.');
        }

        $years = $today->year - $year;

        if ($today->month < $month || ($today->month === $month && $today->day < $day)) {
            --$years;
        }

        if ($years < 0) {
            throw new InvalidQuotePayload('Field "date_of_birth" is in the future.');
        }

        return $years;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws InvalidQuotePayload
     */
    private static function string(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;

        if (!is_string($value)) {
            throw new InvalidQuotePayload(sprintf('Field "%s" must be a string.', $field));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws InvalidQuotePayload
     */
    private static function bool(array $payload, string $field): bool
    {
        $value = $payload[$field] ?? null;

        if (!is_bool($value)) {
            throw new InvalidQuotePayload(sprintf('Field "%s" must be a boolean.', $field));
        }

        return $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T>      $enum
     * @param array<string, mixed> $payload
     *
     * @return T
     *
     * @throws InvalidQuotePayload
     */
    private static function enum(string $enum, array $payload, string $field): \BackedEnum
    {
        $value = $enum::tryFrom(self::string($payload, $field));

        if (null === $value) {
            throw new InvalidQuotePayload(sprintf('Field "%s" is not a known value.', $field));
        }

        return $value;
    }
}
