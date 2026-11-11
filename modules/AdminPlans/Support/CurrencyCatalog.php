<?php

namespace Modules\AdminPlans\Support;

class CurrencyCatalog
{
    /**
     * @return array<string, array{code: string, name: string, symbol: string}>
     */
    public static function all(): array
    {
        return [
            'AED' => ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ'],
            'AFN' => ['code' => 'AFN', 'name' => 'Afghan Afghani', 'symbol' => '؋'],
            'ALL' => ['code' => 'ALL', 'name' => 'Albanian Lek', 'symbol' => 'L'],
            'AMD' => ['code' => 'AMD', 'name' => 'Armenian Dram', 'symbol' => '֏'],
            'ANG' => ['code' => 'ANG', 'name' => 'Netherlands Antillean Guilder', 'symbol' => 'ƒ'],
            'AOA' => ['code' => 'AOA', 'name' => 'Angolan Kwanza', 'symbol' => 'Kz'],
            'ARS' => ['code' => 'ARS', 'name' => 'Argentine Peso', 'symbol' => '$'],
            'AUD' => ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => '$'],
            'AWG' => ['code' => 'AWG', 'name' => 'Aruban Florin', 'symbol' => 'ƒ'],
            'AZN' => ['code' => 'AZN', 'name' => 'Azerbaijani Manat', 'symbol' => '₼'],
            'BAM' => ['code' => 'BAM', 'name' => 'Bosnia and Herzegovina Convertible Mark', 'symbol' => 'KM'],
            'BBD' => ['code' => 'BBD', 'name' => 'Barbadian Dollar', 'symbol' => '$'],
            'BDT' => ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳'],
            'BGN' => ['code' => 'BGN', 'name' => 'Bulgarian Lev', 'symbol' => 'лв'],
            'BHD' => ['code' => 'BHD', 'name' => 'Bahraini Dinar', 'symbol' => '.د.ب'],
            'BIF' => ['code' => 'BIF', 'name' => 'Burundian Franc', 'symbol' => 'FBu'],
            'BMD' => ['code' => 'BMD', 'name' => 'Bermudian Dollar', 'symbol' => '$'],
            'BND' => ['code' => 'BND', 'name' => 'Brunei Dollar', 'symbol' => '$'],
            'BOB' => ['code' => 'BOB', 'name' => 'Bolivian Boliviano', 'symbol' => 'Bs.'],
            'BRL' => ['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$'],
            'BSD' => ['code' => 'BSD', 'name' => 'Bahamian Dollar', 'symbol' => '$'],
            'BTN' => ['code' => 'BTN', 'name' => 'Bhutanese Ngultrum', 'symbol' => 'Nu.'],
            'BWP' => ['code' => 'BWP', 'name' => 'Botswanan Pula', 'symbol' => 'P'],
            'BYN' => ['code' => 'BYN', 'name' => 'Belarusian Ruble', 'symbol' => 'Br'],
            'BZD' => ['code' => 'BZD', 'name' => 'Belize Dollar', 'symbol' => '$'],
            'CAD' => ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => '$'],
            'CDF' => ['code' => 'CDF', 'name' => 'Congolese Franc', 'symbol' => 'FC'],
            'CHF' => ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF'],
            'CLP' => ['code' => 'CLP', 'name' => 'Chilean Peso', 'symbol' => '$'],
            'CNY' => ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
            'COP' => ['code' => 'COP', 'name' => 'Colombian Peso', 'symbol' => '$'],
            'CRC' => ['code' => 'CRC', 'name' => 'Costa Rican Colon', 'symbol' => '₡'],
            'CUP' => ['code' => 'CUP', 'name' => 'Cuban Peso', 'symbol' => '$'],
            'CVE' => ['code' => 'CVE', 'name' => 'Cape Verdean Escudo', 'symbol' => '$'],
            'CZK' => ['code' => 'CZK', 'name' => 'Czech Koruna', 'symbol' => 'Kč'],
            'DJF' => ['code' => 'DJF', 'name' => 'Djiboutian Franc', 'symbol' => 'Fdj'],
            'DKK' => ['code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr'],
            'DOP' => ['code' => 'DOP', 'name' => 'Dominican Peso', 'symbol' => 'RD$'],
            'DZD' => ['code' => 'DZD', 'name' => 'Algerian Dinar', 'symbol' => 'د.ج'],
            'EGP' => ['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => 'E£'],
            'ERN' => ['code' => 'ERN', 'name' => 'Eritrean Nakfa', 'symbol' => 'Nfk'],
            'ETB' => ['code' => 'ETB', 'name' => 'Ethiopian Birr', 'symbol' => 'Br'],
            'EUR' => ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            'FJD' => ['code' => 'FJD', 'name' => 'Fijian Dollar', 'symbol' => '$'],
            'FKP' => ['code' => 'FKP', 'name' => 'Falkland Islands Pound', 'symbol' => '£'],
            'GBP' => ['code' => 'GBP', 'name' => 'British Pound Sterling', 'symbol' => '£'],
            'GEL' => ['code' => 'GEL', 'name' => 'Georgian Lari', 'symbol' => '₾'],
            'GHS' => ['code' => 'GHS', 'name' => 'Ghanaian Cedi', 'symbol' => '₵'],
            'GIP' => ['code' => 'GIP', 'name' => 'Gibraltar Pound', 'symbol' => '£'],
            'GMD' => ['code' => 'GMD', 'name' => 'Gambian Dalasi', 'symbol' => 'D'],
            'GNF' => ['code' => 'GNF', 'name' => 'Guinean Franc', 'symbol' => 'FG'],
            'GTQ' => ['code' => 'GTQ', 'name' => 'Guatemalan Quetzal', 'symbol' => 'Q'],
            'GYD' => ['code' => 'GYD', 'name' => 'Guyanaese Dollar', 'symbol' => '$'],
            'HKD' => ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => '$'],
            'HNL' => ['code' => 'HNL', 'name' => 'Honduran Lempira', 'symbol' => 'L'],
            'HTG' => ['code' => 'HTG', 'name' => 'Haitian Gourde', 'symbol' => 'G'],
            'HUF' => ['code' => 'HUF', 'name' => 'Hungarian Forint', 'symbol' => 'Ft'],
            'IDR' => ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
            'ILS' => ['code' => 'ILS', 'name' => 'Israeli New Shekel', 'symbol' => '₪'],
            'INR' => ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹'],
            'IQD' => ['code' => 'IQD', 'name' => 'Iraqi Dinar', 'symbol' => 'ع.د'],
            'IRR' => ['code' => 'IRR', 'name' => 'Iranian Rial', 'symbol' => '﷼'],
            'ISK' => ['code' => 'ISK', 'name' => 'Icelandic Krona', 'symbol' => 'kr'],
            'JMD' => ['code' => 'JMD', 'name' => 'Jamaican Dollar', 'symbol' => '$'],
            'JOD' => ['code' => 'JOD', 'name' => 'Jordanian Dinar', 'symbol' => 'د.ا'],
            'JPY' => ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
            'KES' => ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
            'KGS' => ['code' => 'KGS', 'name' => 'Kyrgystani Som', 'symbol' => 'сом'],
            'KHR' => ['code' => 'KHR', 'name' => 'Cambodian Riel', 'symbol' => '៛'],
            'KMF' => ['code' => 'KMF', 'name' => 'Comorian Franc', 'symbol' => 'CF'],
            'KRW' => ['code' => 'KRW', 'name' => 'South Korean Won', 'symbol' => '₩'],
            'KWD' => ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك'],
            'KYD' => ['code' => 'KYD', 'name' => 'Cayman Islands Dollar', 'symbol' => '$'],
            'KZT' => ['code' => 'KZT', 'name' => 'Kazakhstani Tenge', 'symbol' => '₸'],
            'LAK' => ['code' => 'LAK', 'name' => 'Lao Kip', 'symbol' => '₭'],
            'LBP' => ['code' => 'LBP', 'name' => 'Lebanese Pound', 'symbol' => 'ل.ل'],
            'LKR' => ['code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'symbol' => 'Rs'],
            'LRD' => ['code' => 'LRD', 'name' => 'Liberian Dollar', 'symbol' => '$'],
            'LSL' => ['code' => 'LSL', 'name' => 'Lesotho Loti', 'symbol' => 'L'],
            'LYD' => ['code' => 'LYD', 'name' => 'Libyan Dinar', 'symbol' => 'ل.د'],
            'MAD' => ['code' => 'MAD', 'name' => 'Moroccan Dirham', 'symbol' => 'د.م.'],
            'MDL' => ['code' => 'MDL', 'name' => 'Moldovan Leu', 'symbol' => 'L'],
            'MGA' => ['code' => 'MGA', 'name' => 'Malagasy Ariary', 'symbol' => 'Ar'],
            'MKD' => ['code' => 'MKD', 'name' => 'Macedonian Denar', 'symbol' => 'ден'],
            'MMK' => ['code' => 'MMK', 'name' => 'Myanmar Kyat', 'symbol' => 'Ks'],
            'MNT' => ['code' => 'MNT', 'name' => 'Mongolian Tugrik', 'symbol' => '₮'],
            'MOP' => ['code' => 'MOP', 'name' => 'Macanese Pataca', 'symbol' => 'MOP$'],
            'MRU' => ['code' => 'MRU', 'name' => 'Mauritanian Ouguiya', 'symbol' => 'UM'],
            'MUR' => ['code' => 'MUR', 'name' => 'Mauritian Rupee', 'symbol' => '₨'],
            'MVR' => ['code' => 'MVR', 'name' => 'Maldivian Rufiyaa', 'symbol' => 'Rf'],
            'MWK' => ['code' => 'MWK', 'name' => 'Malawian Kwacha', 'symbol' => 'MK'],
            'MXN' => ['code' => 'MXN', 'name' => 'Mexican Peso', 'symbol' => '$'],
            'MYR' => ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
            'MZN' => ['code' => 'MZN', 'name' => 'Mozambican Metical', 'symbol' => 'MT'],
            'NAD' => ['code' => 'NAD', 'name' => 'Namibian Dollar', 'symbol' => '$'],
            'NGN' => ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦'],
            'NIO' => ['code' => 'NIO', 'name' => 'Nicaraguan Cordoba', 'symbol' => 'C$'],
            'NOK' => ['code' => 'NOK', 'name' => 'Norwegian Krone', 'symbol' => 'kr'],
            'NPR' => ['code' => 'NPR', 'name' => 'Nepalese Rupee', 'symbol' => '₨'],
            'NZD' => ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => '$'],
            'OMR' => ['code' => 'OMR', 'name' => 'Omani Rial', 'symbol' => 'ر.ع.'],
            'PAB' => ['code' => 'PAB', 'name' => 'Panamanian Balboa', 'symbol' => 'B/.'],
            'PEN' => ['code' => 'PEN', 'name' => 'Peruvian Sol', 'symbol' => 'S/'],
            'PGK' => ['code' => 'PGK', 'name' => 'Papua New Guinean Kina', 'symbol' => 'K'],
            'PHP' => ['code' => 'PHP', 'name' => 'Philippine Peso', 'symbol' => '₱'],
            'PKR' => ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨'],
            'PLN' => ['code' => 'PLN', 'name' => 'Polish Zloty', 'symbol' => 'zł'],
            'PYG' => ['code' => 'PYG', 'name' => 'Paraguayan Guarani', 'symbol' => '₲'],
            'QAR' => ['code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => 'ر.ق'],
            'RON' => ['code' => 'RON', 'name' => 'Romanian Leu', 'symbol' => 'lei'],
            'RSD' => ['code' => 'RSD', 'name' => 'Serbian Dinar', 'symbol' => 'дин.'],
            'RUB' => ['code' => 'RUB', 'name' => 'Russian Ruble', 'symbol' => '₽'],
            'RWF' => ['code' => 'RWF', 'name' => 'Rwandan Franc', 'symbol' => 'FRw'],
            'SAR' => ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => 'ر.س'],
            'SBD' => ['code' => 'SBD', 'name' => 'Solomon Islands Dollar', 'symbol' => '$'],
            'SCR' => ['code' => 'SCR', 'name' => 'Seychellois Rupee', 'symbol' => '₨'],
            'SDG' => ['code' => 'SDG', 'name' => 'Sudanese Pound', 'symbol' => 'ج.س.'],
            'SEK' => ['code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr'],
            'SGD' => ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => '$'],
            'SHP' => ['code' => 'SHP', 'name' => 'Saint Helena Pound', 'symbol' => '£'],
            'SLE' => ['code' => 'SLE', 'name' => 'Sierra Leonean Leone', 'symbol' => 'Le'],
            'SOS' => ['code' => 'SOS', 'name' => 'Somali Shilling', 'symbol' => 'Sh'],
            'SRD' => ['code' => 'SRD', 'name' => 'Surinamese Dollar', 'symbol' => '$'],
            'SSP' => ['code' => 'SSP', 'name' => 'South Sudanese Pound', 'symbol' => '£'],
            'STN' => ['code' => 'STN', 'name' => 'Sao Tome and Principe Dobra', 'symbol' => 'Db'],
            'SVC' => ['code' => 'SVC', 'name' => 'Salvadoran Colon', 'symbol' => '₡'],
            'SYP' => ['code' => 'SYP', 'name' => 'Syrian Pound', 'symbol' => '£'],
            'SZL' => ['code' => 'SZL', 'name' => 'Swazi Lilangeni', 'symbol' => 'E'],
            'THB' => ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿'],
            'TJS' => ['code' => 'TJS', 'name' => 'Tajikistani Somoni', 'symbol' => 'ЅМ'],
            'TMT' => ['code' => 'TMT', 'name' => 'Turkmenistani Manat', 'symbol' => 'm'],
            'TND' => ['code' => 'TND', 'name' => 'Tunisian Dinar', 'symbol' => 'د.ت'],
            'TOP' => ['code' => 'TOP', 'name' => 'Tongan Paʻanga', 'symbol' => 'T$'],
            'TRY' => ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺'],
            'TTD' => ['code' => 'TTD', 'name' => 'Trinidad and Tobago Dollar', 'symbol' => '$'],
            'TWD' => ['code' => 'TWD', 'name' => 'New Taiwan Dollar', 'symbol' => 'NT$'],
            'TZS' => ['code' => 'TZS', 'name' => 'Tanzanian Shilling', 'symbol' => 'Sh'],
            'UAH' => ['code' => 'UAH', 'name' => 'Ukrainian Hryvnia', 'symbol' => '₴'],
            'UGX' => ['code' => 'UGX', 'name' => 'Ugandan Shilling', 'symbol' => 'USh'],
            'USD' => ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            'UYU' => ['code' => 'UYU', 'name' => 'Uruguayan Peso', 'symbol' => '$U'],
            'UZS' => ['code' => 'UZS', 'name' => 'Uzbekistani Som', 'symbol' => 'soʻm'],
            'VES' => ['code' => 'VES', 'name' => 'Venezuelan Bolivar', 'symbol' => 'Bs.'],
            'VND' => ['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫'],
            'VUV' => ['code' => 'VUV', 'name' => 'Vanuatu Vatu', 'symbol' => 'VT'],
            'WST' => ['code' => 'WST', 'name' => 'Samoan Tala', 'symbol' => 'WS$'],
            'XAF' => ['code' => 'XAF', 'name' => 'Central African CFA Franc', 'symbol' => 'FCFA'],
            'XCD' => ['code' => 'XCD', 'name' => 'East Caribbean Dollar', 'symbol' => '$'],
            'XOF' => ['code' => 'XOF', 'name' => 'West African CFA Franc', 'symbol' => 'CFA'],
            'XPF' => ['code' => 'XPF', 'name' => 'CFP Franc', 'symbol' => '₣'],
            'YER' => ['code' => 'YER', 'name' => 'Yemeni Rial', 'symbol' => '﷼'],
            'ZAR' => ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R'],
            'ZMW' => ['code' => 'ZMW', 'name' => 'Zambian Kwacha', 'symbol' => 'ZK'],
            'ZWG' => ['code' => 'ZWG', 'name' => 'Zimbabwe Gold', 'symbol' => 'ZiG'],
        ];
    }

    /**
     * @return array<int, array{code: string, name: string, symbol: string, label: string}>
     */
    public static function options(): array
    {
        return array_values(array_map(
            static fn (array $currency): array => $currency + [
                'label' => sprintf('%s - %s (%s)', $currency['code'], $currency['name'], $currency['symbol']),
            ],
            self::all()
        ));
    }

    public static function find(?string $code): ?array
    {
        if ($code === null) {
            return null;
        }

        $normalized = strtoupper(trim($code));

        return self::all()[$normalized] ?? null;
    }

    public static function normalizeCode(?string $value, string $fallback = 'USD'): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        $code = strtoupper($value);

        if (isset(self::all()[$code])) {
            return $code;
        }

        $symbolMap = [
            '$' => 'USD',
            'US$' => 'USD',
            '€' => 'EUR',
            '£' => 'GBP',
            '¥' => 'JPY',
            '₹' => 'INR',
            '₫' => 'VND',
            '₽' => 'RUB',
            '₩' => 'KRW',
            '₺' => 'TRY',
            '₴' => 'UAH',
            '₦' => 'NGN',
            '₱' => 'PHP',
            '฿' => 'THB',
            '₪' => 'ILS',
            '₣' => 'XPF',
            'R$' => 'BRL',
            'C$' => 'NIO',
            'A$' => 'AUD',
            'CA$' => 'CAD',
            'HK$' => 'HKD',
            'SG$' => 'SGD',
            'NT$' => 'TWD',
            'RM' => 'MYR',
            'Rp' => 'IDR',
            '₡' => 'CRC',
            '₨' => 'PKR',
            'د.إ' => 'AED',
            'ر.س' => 'SAR',
            'د.ك' => 'KWD',
            'ر.ق' => 'QAR',
            'د.ا' => 'JOD',
            '₭' => 'LAK',
            '₮' => 'MNT',
            '₲' => 'PYG',
            '₼' => 'AZN',
            '₸' => 'KZT',
            'ƒ' => 'ANG',
        ];

        return $symbolMap[$value] ?? $fallback;
    }

    public static function symbolFor(?string $value, string $fallback = '$'): string
    {
        $currency = self::find(self::normalizeCode($value));

        return $currency['symbol'] ?? $fallback;
    }

    public static function nameFor(?string $value, string $fallback = 'US Dollar'): string
    {
        $currency = self::find(self::normalizeCode($value));

        return $currency['name'] ?? $fallback;
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }
}
