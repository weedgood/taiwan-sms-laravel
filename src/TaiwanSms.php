<?php
namespace Jarvisho\TaiwanSmsLaravel;

use Jarvisho\TaiwanSmsLaravel\Exceptions\InvalidSms;

class TaiwanSms
{
    public static function send($destination, $text, $test = false)
    {
        $isTaiwanPhone = self::validateTaiwanPhone($destination);

        try {
            $response = $isTaiwanPhone
                ? self::process(self::getPrimaryClassName(), $text, $destination, $isTaiwanPhone, $test)
                : self::process(self::getClassPrefix() . ucfirst('infobip'), $text, $destination, $isTaiwanPhone, $test);
        }catch (\Exception $exception) {
            if(empty(self::getFailoverClassName()) || !$isTaiwanPhone) throw new InvalidSms($exception->getMessage());
            try {
                $response = self::process(self::getFailoverClassName(), $text, $destination, $test);
            }catch (\Exception $exception) {
                throw new InvalidSms($exception->getMessage());
            }
        }

        return $response;
    }

    /**
     * @param string $class
     * @param $text
     * @param $destination
     * @return array
     */
    public static function process(string $class, $text, $destination, $isTaiwanPhone, $test = false): array
    {
        $api = $isTaiwanPhone
            ? new $class()
            : new $class(true);

        $api->setText($text);
        $api->setDestination($destination);
        if($test) return $api->test();

        return $api->send();
    }

    /**
     * @return string
     * @throws InvalidSms
     */
    public static function getPrimaryClassName(): string
    {
        if(empty(config('taiwan_sms.primary'))) throw new InvalidSms('主要簡訊服務尚未設定');
        if(!array_key_exists(strtolower(config('taiwan_sms.primary')), data_get(config('taiwan_sms'), 'services', []))) throw new InvalidSms('主要簡訊服務不在名單中');
        return self::getClassPrefix() . ucfirst(strtolower(config('taiwan_sms.primary')));
    }

    /**
     * @return string
     * @throws InvalidSms
     */
    public static function getFailoverClassName(): string
    {
        if(empty(config('taiwan_sms.failover'))) return '';
        if(!array_key_exists(strtolower(config('taiwan_sms.failover')), data_get(config('taiwan_sms'), 'services', []))) throw new InvalidSms('備援簡訊服務不在名單中');
        return self::getClassPrefix() . ucfirst(strtolower(config('taiwan_sms.failover')));
    }

    public static function getClassPrefix(): string
    {
        $array = explode('\\', __CLASS__);
        array_pop($array);
        $class = implode('\\', $array);
        $class .= '\\Services\\';

        return $class;
    }

    /**
     * 驗證是否為台灣手機號碼格式
     * @param string $phone
     * @return bool
     */
    public static function validateTaiwanPhone(string $phone): bool
    {
        // 移除所有非數字字符
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        // 台灣手機號碼格式驗證
        // 09開頭的10位數字 (例：0912345678)
        // 或 +886開頭後接9開頭的9位數字 (例：+886912345678)
        // 或 886開頭後接9開頭的9位數字 (例：886912345678)

        if (preg_match('/^09\d{8}$/', $cleanPhone)) {
            return true; // 09xxxxxxxx 格式
        }

        if (preg_match('/^8869\d{8}$/', $cleanPhone)) {
            return true; // 8869xxxxxxxx 格式 (去掉+886前綴)
        }

        // 處理包含+886前綴的情況
        if (str_starts_with($phone, '+886')) {
            $phoneWithoutCountryCode = substr($cleanPhone, 3); // 移除886
            if (preg_match('/^9\d{8}$/', $phoneWithoutCountryCode)) {
                return true; // +8869xxxxxxxx 格式
            }
        }

        return false;
    }
}
