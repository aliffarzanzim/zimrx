<?php
declare(strict_types=1);

namespace ZimRx\Services;

// Patient demographic enums, clinical units, and referral categories.
final class PatientDemographics
{
    /** @var list<string> */
    public const BLOOD_GROUPS = [
        'A+',
        'A-',
        'B+',
        'B-',
        'O+',
        'O-',
        'AB+',
        'AB-',
        'Others',
    ];

    /** @var list<string> */
    public const GENDERS = [
        'Male',
        'Female',
        'Others',
    ];

    /** @var list<string> */
    public const AGE_UNITS = [
        'Years',
        'Months',
        'Weeks',
        'Days',
    ];

    /** @var list<string> */
    public const WEIGHT_UNITS = [
        'kg',
        'lb',
    ];

    /** @var list<string> */
    public const HEIGHT_UNITS = [
        'inch',
        'feet',
        'cm',
        'meter',
    ];

    /** @var list<string> */
    public const REFERRAL_TYPES = [
        'Self',
        'Other Patient',
        'Doctor',
        'Others',
    ];

    /**
     * @return list<string>
     */
    public static function bloodGroups(): array
    {
        return self::BLOOD_GROUPS;
    }

    /**
     * @return list<string>
     */
    public static function genders(): array
    {
        return self::GENDERS;
    }

    /**
     * @return list<string>
     */
    public static function ageUnits(): array
    {
        return self::AGE_UNITS;
    }

    /**
     * @return list<string>
     */
    public static function weightUnits(): array
    {
        return self::WEIGHT_UNITS;
    }

    /**
     * @return list<string>
     */
    public static function heightUnits(): array
    {
        return self::HEIGHT_UNITS;
    }

    /**
     * @return list<string>
     */
    public static function referralTypes(): array
    {
        return self::REFERRAL_TYPES;
    }
}
