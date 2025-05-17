<?php
namespace WPM\Utils;

use Morilog\Jalali\Jalalian;

defined('ABSPATH') || exit;

class PersianDate {
    public static function to_persian($date, $format = 'Y/m/d') {
        if (empty($date) || $date == '0000-00-00' || !strtotime($date)) {
            return '';
        }
        return Jalalian::fromDateTime($date)->format($format);
    }

    public static function to_gregorian($persian_date, $format = 'Y-m-d') {
        if (empty($persian_date) || $persian_date == '0000-00-00' || !preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $persian_date)) {
            return '';
        }
        try {
            list($year, $month, $day) = explode('/', $persian_date);
            $jDate = new Jalalian($year, $month, $day);
            return $jDate->toCarbon()->format($format);
        } catch (\Exception $e) {
            return '';
        }
    }

    public static function is_valid_persian_date($date) {
        // Validate format: YYYY/MM/DD
        if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $date, $matches)) {
            return false;
        }

        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];

        // Basic validation for Persian calendar
        if ($year < 1300 || $year > 1500 || $month < 1 || $month > 12 || $day < 1) {
            return false;
        }

        // Check days in month (simplified for Persian calendar)
        $days_in_month = $month <= 6 ? 31 : ($month <= 11 ? 30 : 29);
        return $day <= $days_in_month;
    }

    public static function is_valid_date($date) {
        if (empty($date)) {
            return true; // Allow empty date
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
    }

    public static function now($format = 'Y/m/d') {
        return Jalalian::now()->format($format);
    }
}
?>