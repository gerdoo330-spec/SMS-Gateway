<?php

/**
 * تبدیل تاریخ میلادی دیتابیس به تاریخ شمسی برای نمایش در پنل.
 * مقدار ذخیره‌شده در دیتابیس را تغییر نمی‌دهد.
 */
final class JalaliDate
{
    private const MONTHS = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    public static function format($value, bool $withTime = true): string
    {
        if ($value === null || trim((string)$value) === '') {
            return '—';
        }

        try {
            $date = new DateTimeImmutable((string)$value);
        } catch (Throwable $exception) {
            return (string)$value;
        }

        [$year, $month, $day] = self::toJalali(
            (int)$date->format('Y'),
            (int)$date->format('n'),
            (int)$date->format('j')
        );

        $result = sprintf(
            '%d %s %d',
            $day,
            self::MONTHS[$month],
            $year
        );

        if ($withTime) {
            $result .= ' - ' . $date->format('H:i');
        }

        return self::persianDigits($result);
    }

    public static function persianDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳',
            '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷',
            '8' => '۸', '9' => '۹',
        ]);
    }

    private static function toJalali(int $gy, int $gm, int $gd): array
    {
        $monthDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        $jy = ($gy <= 1600) ? 0 : 979;
        if ($gy > 1600) {
            $gy -= 1600;
        } else {
            $gy -= 621;
        }

        $gy2 = ($gm > 2) ? $gy + 1 : $gy;
        $days = (365 * $gy)
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            - 80
            + $gd
            + $monthDays[$gm - 1];

        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;

        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }
}
