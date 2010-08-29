<?php

/**
 * Utility functions that augment how PHP works.
 */
class CampUtils {
  
  function arrayGet($array = array(), $key = '', $default = FALSE, $finder = NULL) {
    if(is_array($array) and (is_string($key) or is_numeric($key) or is_bool($key))) {
      if(array_key_exists($key, $array)) {
        if(is_array($finder)) {
          if(in_array($key, $finder)) {
            return($array[$key]);
          } else {
            return($default);
          }
        } elseif(is_string($finder) or is_numeric($finder) or is_bool($finder)) {
          if($key == $finder) {
            return($array[$key]);
          } else {
            return($default);
          }
        } else {
          return($array[$key]);
        }
      } else {
        return($default);
      }
    } else {
      return($default);
    }
  }
}

