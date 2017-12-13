<?php

namespace ImporterBundle\Util;

use Mockery\Matcher\Closure;
use Symfony\Component\EventDispatcher\Tests\CallableClass;
use Symfony\Component\Finder\Tests\Iterator\Iterator;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class ArrayUtil
{

  // @todo - move these into OperatorUtil
    const ITERATOR_RETURN_FIRST_TRUTHY       = 'ITERATOR_RETURN_FIRST_TRUTHY';
    const ITERATOR_PLUCK_FIRST_TRUTHY        = 'ITERATOR_PLUCK_FIRST_TRUTHY';
    const ITERATOR_RETURN_FIRST_FALSY        = 'ITERATOR_RETURN_FIRST_FALSY';
    const ITERATOR_RETURN_ACCUMULATED_TRUTHY = 'ITERATOR_RETURN_ACCUMULATED_TRUTHY';

    const SORT_ASC = 'SORT_ASC';

    public static function arraySetIfUnset(&$array, $path, $value) {

        if (isset($array[$path])) {
            return;
        }
        $array[$path] = $value;
    }

  /**
   * Simply recursively gets an element from each subarray by key
   * @param $array
   * @param $key
   */
    public static function arrayGetRecursive($array, $key) {

        return array_map(
            function ($item) use ($key) {
                return $item[$key];
            },
            $array
        );
    }

  /**
   * Recurses through array values and does comparisons on the values for supplied key
   * @param $array
   * @param $key
   */
    public static function arraySearchRecursive(&$array, $key, $expected) {

        foreach ($array as $item) {
            if ($item[$key] === $expected) {
                return $item;
            }
        }
    }

  /**
   * Recurses through array values and does comparisons on the values for supplied key but using accessor path for key
   * Returns the top level element subtree comparison succeeds
   * @param $array
   * @param mixed $key        If array, specifies multiple properties that must match
   * @param mixed $expected   If array, specifies multiple properties that must match. Can be a closure also
   */
    public static function arraySearchAccessorRecursive(&$array, $key, $expected, $action = self::ITERATOR_RETURN_FIRST_TRUTHY) {

        $accessor = PropertyAccess::createPropertyAccessor();
        $array    = self::arrayCast($array);
        $key      = self::arrayCast($key);
        $expected = self::arrayCast($expected);
        $acc      = [];                                      // for accumulator-type ones

        $checkAll = function ($item, array $keys, array $expected) use ($accessor, $action, &$acc) {
            foreach ($keys as $keyKey => $aKey) {
                $foundValue    = ObjectUtil::propertyAccessUsingDotNotation($item, $aKey);
                $expectedValue = $expected[$keyKey];

              // get the result
                switch (true) {
                    case ($expectedValue instanceof \Closure):
                        $res = $expectedValue->__invoke($foundValue);
                        break;
                    default:
                        $res = ($foundValue === $expectedValue);
                }

                switch ($action) {
                    case self::ITERATOR_RETURN_FIRST_TRUTHY:
                        if ($res) {
                            return true;
                        }
                        break;
                    case self::ITERATOR_PLUCK_FIRST_TRUTHY:
                        if ($res) {
                            unset($item[$aKey]);
                            return true;
                        }
                        break;
                    case self::ITERATOR_RETURN_FIRST_FALSY:
                        if (!$res) {
                            return true;
                        }
                        break;
                    case self::ITERATOR_RETURN_ACCUMULATED_TRUTHY:
                        if ($res) {
                            $acc[] = $item;
                        }
                        break;
                    default:
                        throw new \Exception("Implement me");
                }
            }
            return null; // make it explicit
        };

        foreach ($array as $item) {
            if ($checkAll->__invoke($item, $key, $expected)) {
                return $item;
            }
        }
        return empty($acc) ? null : $acc;                 // allow the function to build up a response if in correct mode
    }

    public static function arrayPartitionByDistinctProperty(array $array, $property) {

        $buckets  = [];
        $accessor = new PropertyAccessor;
        foreach ($array as $key => $element) {
            $value = $accessor->getValue($element, $property);
            if ($value) {
                if (!isset($buckets[$value])) {
                    $buckets[$value] = [];
                }
                $buckets[$value][] = $element;
            }
        }
        return $buckets;
    }

  /**
   * More suited to deeply nested API responses where there are likely multiple arrays preventing a path property
   * being used. This will return ALL instances of values matching the key provided so be careful
   * This uses PHP Generators, didn't realise were possible
   *   - https://stackoverflow.com/a/3975706/2968327
   */
    public static function arrayDeepSearchByKey(array $array, $searchKey) {

        $iterator  = new \RecursiveArrayIterator($array);
        $recursive = new \RecursiveIteratorIterator(
            $iterator,
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $ret = [];
        foreach ($recursive as $key => $value) {
            if ($searchKey === $key) {
              //yield $value;
                if (is_array($value)) {
                    $ret = array_merge($ret, $value);
                } else {
                    array_push($ret, $value);
                }
            }
        }
        return $ret;
    }

  /**
   * Uses the above arrayDeepSearchByKey to deeply pull out all values by key but then performs constraints
   * using the familiar path notation on the values found.
   * For example, we can just pull out all `Outcome`s and then constrain on their bookie's name, for instance.
   */
    public static function arrayDeepSearchByKeyAccessor(array $array, $searchKey, $property, $value, $action = self::ITERATOR_RETURN_FIRST_TRUTHY) {

        $values = self::arrayDeepSearchByKey($array, $searchKey);

        if (!empty($values)) {
            $values = self::arraySearchAccessorRecursive($values, $property, $value, $action);
        }

        return empty($values) ? null : $values;
    }

  /**
   * Simply as wrapper around PropertAccessor
   * @param $array
   * @param $path
   */
    public static function arrayGetByPath(&$array, $path) {

        $accessor = PropertyAccess::createPropertyAccessor();
        return $accessor->getValue($array, $path);
    }

  /**
   * Makes subtrees distinct by a property. Useful for boiling down outcomes by related event, for instance
   * @param $array
   * @param $key
   */
    public static function arrayDeduplicateByProperty(&$array, $path) {

        $arr = [];
        foreach ($array as $key => $item) {
            $propertyValue = ObjectUtil::propertyAccessUsingDotNotation($item, $path);
            if (in_array($propertyValue, $arr)) {
                unset($array[$key]);
            }
            $arr[] = $propertyValue;
        }
        return array_values($array);
    }

  /**
   * Sets a value by path, IF IT DOES NOT EXIST
   * @param $array
   * @param $key
   */
    public static function arraySetMergeByProperty(&$array, $path, $value) {

        $accessor = PropertyAccess::createPropertyAccessor();
        if (is_null($accessor->getValue($array, $path))) {
            $accessor->setValue($array, $path, $value);
        }
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

  /**
   * Returns an array containing values whose keys match the regex. The original array values will be unset
   * @param      $array
   * @param      $key
   * @param null $default
   */
    public static function arrayPluckByPattern(&$array, $regex, $captureMatchingPart = false) {

        $retArr = [];

        foreach ($array as $key => $value) {
            if (preg_match("/${regex}/i", $key, $matches)) {
                if ($captureMatchingPart) {
                    $retArr[$key] = $value;
                } else {
                    $retArr[str_replace($regex, '', $key)] = $value;
                }
                unset($array[$key]);
            }
        }
        return $retArr;
    }

  /*
   * Recursively diff two arrays by keys as usual in array_diff_key but then also
   * performs a further array diff using the values of array2.
   * Returns the elements of array
   * http://php.net/manual/en/function.array-diff-key.php
   *
   */
    public static function arrayDiffKeyRecursive($array1, $array2) {

        $difference = [];
        $array1Keys = array_keys($array1);

        foreach ($array2 as $array2Key => $array2Value) {
            if (!in_array($array2Key, $array1Keys)) {
                $difference[$array2Key] = $array2Value;
                continue;
            }
          // array2 values define arrays in which all keys must exist in the array1 value
            if (array_diff_key($array2Value, $array1[$array2Key])) {
                $difference[$array2Key] = $array2Value;
            }
        }
        return $difference;
    }

  /**
   * Batshit crazy function. Like array_combine but uses keys of array1 and values of array2
   * If array1 > array2, will pad remaining array1 values with last of array2
   *
   * @param $array1
   * @param $array2
   * @return array
   */
    public static function arrayCombineKeys($array1, $array2) {

        if (count($array1) > count($array2)) {
            $lastValue = end($array2);
            $countDiff = count($array1) - count($array2);
            for ($i = 0; $i < $countDiff; $i++) {
                $array2[] = $lastValue;
            }
        }

        $ret = [];
        reset($array1);
        reset($array2);
        $currentArr1 = key($array1);
        $currentArr2 = current($array2);
        while ($currentArr1 !== false && $currentArr2 !== false) {
            $ret[$currentArr1] = $currentArr2;
            next($array1);
            next($array2);
            $currentArr1 = key($array1);
            $currentArr2 = current($array2);
        }

        return $ret;
    }

  /**
   * Drops down through nested arr and returns those values in whitelist mentioned anywhere (field or subarray key)
   * @param $whitelist
   * @param $nested
   */
    public static function arrayIntersectKeyRecursive($whitelist, $nested) {

        $argsClean = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($nested), \RecursiveIteratorIterator::CHILD_FIRST); // http://php.net/manual/en/recursiveiteratoriterator.construct.php

        foreach ($iterator as $key => $item) {
          // we've reached a tree node
            if (is_scalar($item)) {
                $searchFor = $key;
            } else {
                $searchFor = $key;
            }

            if (in_array($searchFor, $whitelist)) {
                $argsClean[$searchFor] = $searchFor;  // prevents duplicates
                unset($whitelist[array_search($searchFor, $whitelist)]);        // marginal speed increase i should imagine
            }

            if (empty($whitelist)) {
                break;
            }
        }

        return empty($argsClean) ? [] : array_keys($argsClean);
    }

  /**
   * e.g. $arr = [
            'win'  => $latestGame['team_streak_win'],
            'lost' => $latestGame['team_streak_lost'],
            'drew' => $latestGame['team_streak_drew'],
          ];
   * Which key (win, lost, drew) has the max, min etc value
   * @param array $arr
   * @param mixed $func  If a closure, will
   * @return mixed  Returns the key of the top aggregated value
   */
    public static function getKeyByAggregateValue(array $arr, $func = 'asort') {

        $func($arr);
        $val = array_slice($arr, -1);
        return key($val);
    }

//  /**
//   * Runs array_map with the provided Closure and then reports result based on
//   * an aggregate indicator of boolean returns. For example:
//   * - at least one returned true
//   * - at least one returned false
//   * - all returned true
//   * - all returned false
//   */
//  public static function reduceArrayMap(array $mappable, \Closure $func, $aggregateIndicator) {
//
//    $default = true;
//    switch ($aggregateIndicator) {
//      case self::AGGREGATE_INDICATOR_AT_LEAST_ONE_TRUE:
//        $response = true;
//        $test     = true;
//        $default  = false;
//        break;
//      case self::AGGREGATE_INDICATOR_AT_LEAST_ONE_FALSE:
//        $response = true;
//        $test     = false;
//        $default  = false;
//        break;
//      case self::AGGREGATE_INDICATOR_ALL_TRUE:
//        $response = false;
//        $test     = false;
//        $default  = true;
//        break;
//      case self::AGGREGATE_INDICATOR_ALL_FALSE:
//        $response = false;
//        $test     = true;
//        $default  = true;
//        break;
//    }
//    foreach ($mappable as $k => $v) {
//      if ($test === $func->__invoke($v, $k)) return $response;
//    }
//    return $default;
//  }

//  /**
//   * Iterates over the supplied iterator, applying the closure. Can be "short-circuited" based on the aggregateIndicator
//   */
//  public static function iterate(Array $items, callable $func, Array $funcVars = [], $aggregateIndicator = self::ITERATOR_RETURN_FIRST_TRUTHY) {
//
//    foreach ($items as $key => $item) {
//
//      $res = $func($item, ...$funcVars);
//
//      switch ($aggregateIndicator) {
//        case self::ITERATOR_RETURN_FIRST_TRUTHY:
//          if ($res) return $item;
//          break;
//      }
//    }
//  }

//  /**
//   * Ensures an array will have the keys supplied
//   * @param       $result
//   * @param array $keyList
//   * @return array  This is safe for use with list()
//   */
//  public static function spreadSafe($result, array $keyList = []) : array {
//
//    $ret = array_merge(array_combine($keyList, array_pad([], count($keyList), null)), $result);
//    return array_values($ret);
//  }

    public static function arrayCast($item = null, $default = null) {

        if (is_null($item)) {
            return $default;
        }
        if (!self::isArrayLike($item)) {
            return [$item];
        }
        return $item;
    }

    public static function isArrayLike($var) {

        return is_array($var) ||
        ($var instanceof \ArrayAccess  &&
        $var instanceof \Traversable  &&
        $var instanceof \Countable)
        ;
    }

    public static function arraySortByProperty(array &$array, $path, $direction = self::SORT_ASC) {

        $accessor = PropertyAccess::createPropertyAccessor();
        uasort($array, function ($a, $b) use ($accessor, $path, $direction) {

            $valA = $accessor->getValue($a, $path);
            $valB = $accessor->getValue($b, $path);

            if ($valA === $valB) {
                return 0;
            }

            switch ($direction) {
                case self::SORT_ASC:
                    return $valA < $valB ? -1 : 1;
            }
        });
    }
}
