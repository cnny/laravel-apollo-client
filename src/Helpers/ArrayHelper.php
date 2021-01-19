<?php

namespace Cann\Apollo\Helpers;

class ArrayHelper
{
    public static function jsonToArray(array $array)
    {
        foreach ($array as $key => $value) {

            $value = json_decode($value, true);

            // value 是 json 字符串
            if (! is_null($value)) {
                $array[$key] = $value;
            }
        }

        return $array;
    }
}
