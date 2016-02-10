<?php

final class ManiphestTaskPriority extends ManiphestConstants {

  /**
   * Get the priorities and their full descriptions.
   *
   * @return  map Priorities to descriptions.
   */
  public static function getTaskPriorityMap() {
    $map = self::getConfig();
    foreach ($map as $key => $spec) {
      $map[$key] = idx($spec, 'name', $key);
    }
    return $map;
  }


  /**
   * Get the priorities and their command keywords.
   *
   * @return map Priorities to lists of command keywords.
   */
  public static function getTaskPriorityKeywordsMap() {
    $map = self::getConfig();
    foreach ($map as $key => $spec) {
      $words = idx($spec, 'keywords', array());
      if (!is_array($words)) {
        $words = array($words);
      }

      foreach ($words as $word_key => $word) {
        $words[$word_key] = phutil_utf8_strtolower($word);
      }

      $words = array_unique($words);

      $map[$key] = $words;
    }

    return $map;
  }


  /**
   * Get the priorities and their related short (one-word) descriptions.
   *
   * @return  map Priorities to short descriptions.
   */
  public static function getShortNameMap() {
    $map = self::getConfig();
    foreach ($map as $key => $spec) {
      $map[$key] = idx($spec, 'short', idx($spec, 'name', $key));
    }
    return $map;
  }


  /**
   * Get a map from priority constants to their colors.
   *
   * @return map<int, string> Priorities to colors.
   */
  public static function getColorMap() {
    $map = self::getConfig();
    foreach ($map as $key => $spec) {
      $map[$key] = idx($spec, 'color', 'grey');
    }
    return $map;
  }


  /**
   * Return the default priority for this instance of Phabricator.
   *
   * @return int The value of the default priority constant.
   */
  public static function getDefaultPriority() {
    return PhabricatorEnv::getEnvConfig('maniphest.default-priority');
  }


  /**
   * Retrieve the full name of the priority level provided.
   *
   * @param   int     A priority level.
   * @return  string  The priority name if the level is a valid one.
   */
  public static function getTaskPriorityName($priority) {
    return idx(self::getTaskPriorityMap(), $priority, $priority);
  }

  /**
   * Retrieve the color of the priority level given
   *
   * @param   int     A priority level.
   * @return  string  The color of the priority if the level is valid,
   *                  or black if it is not.
   */
  public static function getTaskPriorityColor($priority) {
    return idx(self::getColorMap(), $priority, 'black');
  }

  public static function getTaskPriorityIcon($priority) {
    return 'fa-arrow-right';
  }

  public static function isDisabledPriority($priority) {
    $config = idx(self::getConfig(), $priority, array());
    return idx($config, 'disabled', false);
  }

  private static function getConfig() {
    $config = PhabricatorEnv::getEnvConfig('maniphest.priorities');
    krsort($config);
    return $config;
  }

  public static function validateConfiguration($config) {
    if (!is_array($config)) {
      throw new Exception(
        pht(
          'Configuration is not valid. Maniphest priority configurations '.
          'must be dictionaries.',
          $config));
    }

    foreach ($config as $key => $value) {
      if (!ctype_digit((string)$key)) {
        throw new Exception(
          pht(
            'Key "%s" is not a valid priority constant. Priority constants '.
            'must be nonnegative integers.',
            $key));
      }

      if (!is_array($value)) {
        throw new Exception(
          pht(
            'Value for key "%s" should be a dictionary.',
            $key));
      }

      PhutilTypeSpec::checkMap(
        $value,
        array(
          'name' => 'string',
          'short' => 'optional string',
          'color' => 'optional string',
          'keywords' => 'optional list<string>',
          'disabled' => 'optional bool',
        ));
    }
  }

}
