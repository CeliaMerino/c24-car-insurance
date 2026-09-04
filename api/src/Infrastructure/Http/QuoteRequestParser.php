<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Quote\AnnualMileage;
use App\Domain\Quote\CarCategory;
use App\Domain\Quote\CoverageLevel;
use App\Domain\Quote\DateOfBirth;
use App\Domain\Quote\PostalCode;
use App\Domain\Quote\QuoteRequest;
use App\Domain\Quote\Usage;
use App\Domain\Shared\ReferenceDate;
use App\Domain\Validation\ValidationError;
use App\Domain\Validation\ValidationException;
use BackedEnum;

/**
 * HTTP-layer validation of specs/05-api-contract.md section 2.1. Collects every
 * invalid field, including unknown names, before refusing the request.
 */
final class QuoteRequestParser
{
    private const array KNOWN_FIELDS = [
        'date_of_birth',
        'postal_code',
        'car_category',
        'usage',
        'annual_mileage',
        'garage',
        'coverage',
    ];

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ValidationException
     */
    public function parse(array $payload, ReferenceDate $today): QuoteRequest
    {
        $errors = [];

        $dateOfBirth = $this->dateOfBirth($payload, $today, $errors);
        $postalCode = $this->postalCode($payload, $errors);
        $carCategory = $this->enum($payload, 'car_category', CarCategory::class, $errors);
        $usage = $this->enum($payload, 'usage', Usage::class, $errors);
        $annualMileage = $this->enum($payload, 'annual_mileage', AnnualMileage::class, $errors);
        $garage = $this->garage($payload, $errors);
        $coverage = $this->enum($payload, 'coverage', CoverageLevel::class, $errors);

        foreach (array_keys($payload) as $field) {
            if (!in_array($field, self::KNOWN_FIELDS, true)) {
                $errors[] = new ValidationError(
                    $field,
                    'unknown_field',
                    sprintf('Field "%s" is not in the contract.', $field),
                );
            }
        }

        if ([] !== $errors) {
            throw new ValidationException($errors);
        }

        if (
            !$dateOfBirth instanceof DateOfBirth
            || !$postalCode instanceof PostalCode
            || !$carCategory instanceof CarCategory
            || !$usage instanceof Usage
            || !$annualMileage instanceof AnnualMileage
            || !is_bool($garage)
            || !$coverage instanceof CoverageLevel
        ) {
            throw new ValidationException($errors);
        }

        return new QuoteRequest(
            $dateOfBirth,
            $postalCode,
            $carCategory,
            $usage,
            $annualMileage,
            $garage,
            $coverage,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<ValidationError> $errors
     */
    private function dateOfBirth(array $payload, ReferenceDate $today, array &$errors): ?DateOfBirth
    {
        $value = $this->present($payload, 'date_of_birth', $errors);
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            $errors[] = new ValidationError(
                'date_of_birth',
                'format',
                'Enter a valid date in YYYY-MM-DD format.',
            );

            return null;
        }

        try {
            return DateOfBirth::fromString($value, $today->year, $today->month, $today->day);
        } catch (ValidationException $exception) {
            foreach ($exception->errors as $error) {
                $errors[] = $error;
            }

            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<ValidationError> $errors
     */
    private function postalCode(array $payload, array &$errors): ?PostalCode
    {
        $value = $this->present($payload, 'postal_code', $errors);
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            $errors[] = new ValidationError(
                'postal_code',
                'format',
                'Enter a five-digit postal code.',
            );

            return null;
        }

        try {
            return PostalCode::fromString($value);
        } catch (ValidationException $exception) {
            foreach ($exception->errors as $error) {
                $errors[] = $error;
            }

            return null;
        }
    }

    /**
     * @template T of BackedEnum
     *
     * @param array<string, mixed> $payload
     * @param class-string<T> $enum
     * @param list<ValidationError> $errors
     *
     * @return T|null
     */
    private function enum(array $payload, string $field, string $enum, array &$errors): ?BackedEnum
    {
        $value = $this->present($payload, $field, $errors);
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            $errors[] = new ValidationError(
                $field,
                'format',
                sprintf('Field "%s" has the wrong type.', $field),
            );

            return null;
        }

        $case = $enum::tryFrom($value);
        if (null === $case) {
            $errors[] = new ValidationError(
                $field,
                'unknown_value',
                sprintf('Field "%s" is not a recognised value.', $field),
            );

            return null;
        }

        return $case;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<ValidationError> $errors
     */
    private function garage(array $payload, array &$errors): ?bool
    {
        $value = $this->present($payload, 'garage', $errors);
        if (null === $value) {
            return null;
        }

        if (!is_bool($value)) {
            $errors[] = new ValidationError(
                'garage',
                'format',
                'Field "garage" has the wrong type.',
            );

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<ValidationError> $errors
     */
    private function present(array $payload, string $field, array &$errors): mixed
    {
        if (!array_key_exists($field, $payload) || null === $payload[$field]) {
            $errors[] = new ValidationError(
                $field,
                'required',
                sprintf('Field "%s" is required.', $field),
            );

            return null;
        }

        return $payload[$field];
    }
}
