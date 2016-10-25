<?php

/**
 * @file
 *  SL7 Array API.
 */

class ApiArray {

  /**
   * Remove empty elements from array.
   *
   * @param array $array Source array
   * @return array
   */
  public static function removeEmptyElements($array) {
    return array_diff($array, array(''));
  }

  /**
   * Recursively convert an object to an array.
   *
   * @param $obj
   * @return array
   */
  public static function objectToArray($obj) {
    if(is_object($obj)) $obj = (array) $obj;
    if(is_array($obj)) {
      $new = array();
      foreach($obj as $key => $val) {
        $new[$key] = self::objectToArray($val);
      }
    }
    else $new = $obj;

    return $new;
  }
}