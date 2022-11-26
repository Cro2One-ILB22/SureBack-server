<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum VariableEnum: string
{
    use EnumToArray;

    case IG_USERNAME = 'ig_username';
    case IG_BASE_URL = 'ig_base_url';
    case IG_HOST = 'ig_host';
    case IG_APP_ID = 'ig_app_id';
    case IG_SESSION_ID = 'ig_session_id';
    case IG_USER_AGENT = 'ig_user_agent';
    case IG_REFERER = 'ig_referer';
    case IG_ORIGIN = 'ig_origin';
    case IG_CSRF_TOKEN = 'ig_csrf_token';
        // case IG_AJAX = 'ig_ajax';
    case IG_ASBD_ID = 'ig_asbd_id';
    case IG_REQUESTED_WITH = 'ig_requested_with';
    case IG_ALT_USED = 'ig_alt_used';
    case IG_SEC_FETCH_SITE = 'ig_sec_fetch_site';
    case IG_SEC_FETCH_MODE = 'ig_sec_fetch_mode';
    case IG_SEC_FETCH_DEST = 'ig_sec_fetch_dest';
    case IG_TE = 'ig_te';

    public static function dicts(): array
    {
        return array_combine(
            array_map(fn ($value) => $value, self::values()),
            array_map(fn ($case) => $case->dynamicDicts(), self::cases())
        );
    }

    private function dynamicDicts(): array
    {
        return match ($this) {
            self::IG_USERNAME => [
                'name' => 'Instagram Username',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_USERNAME],
            ],
            self::IG_BASE_URL => [
                'name' => 'Base URL',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_URL],
            ],
            self::IG_HOST => [
                'name' => 'Host',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER, VariableCategoryEnum::IG_URL],
            ],
            self::IG_APP_ID => [
                'name' => 'X-IG-App-ID',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
            self::IG_SESSION_ID => [
                'name' => 'sessionid',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_COOKIE],
            ],
            self::IG_USER_AGENT => [
                'name' => 'User-Agent',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
            self::IG_REFERER => [
                'name' => 'Referer',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
            self::IG_ORIGIN => [
                'name' => 'Origin',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
            self::IG_CSRF_TOKEN => [
                'name' => 'csrftoken',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_COOKIE, VariableCategoryEnum::IG_HEADER],
            ],
            // self::IG_AJAX => [
            //   'name' => 'X-Instagram-AJAX',
            //   'categories' => [VariableCategoryEnum::IG],
            // ],
            self::IG_ASBD_ID => [
                'name' => 'X-ASBD-ID',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
            self::IG_REQUESTED_WITH => [
                'name' => 'X-Requested-With',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
            self::IG_ALT_USED => [
                'name' => 'Alt-Used',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
            self::IG_SEC_FETCH_SITE => [
                'name' => 'Sec-Fetch-Site',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
            self::IG_SEC_FETCH_MODE => [
                'name' => 'Sec-Fetch-Mode',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
            self::IG_SEC_FETCH_DEST => [
                'name' => 'Sec-Fetch-Dest',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
            self::IG_TE => [
                'name' => 'TE',
                'categories' => [VariableCategoryEnum::IG, VariableCategoryEnum::IG_WS, VariableCategoryEnum::IG_HEADER],
            ],
        };
    }
}
