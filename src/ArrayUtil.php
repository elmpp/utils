<?php

namespace Partridge\Utils;

class ArrayUtil
{

  /*
   * Returns the diff of 2 arrays but using the values of the second
   * as the blacklist of keys against the 1st. Avoids the use of array_flip
   * for second
   * http://php.net/manual/en/function.array-diff-key.php\
   * http://php.net/manual/en/function.array-flip.php
   */
    public static function arrayDiffKeyByValues($array1, $array2) {

      // @todo validate the values of array2 as being valid keys
        return array_diff_key($array1, array_flip($array2));
    }

  /**
   * simple util for removing and returning a value from an array by key, returning default if not present
   * @param      $array
   * @param      $key
   * @param null $default
   */
    public static function arrayPluck(&$array, $key, $default = null) {

        if (!isset($array[$key])) {
            return $default;
        }
        $value = $array[$key];
        unset($array[$key]);
        return $value;
    }
}
