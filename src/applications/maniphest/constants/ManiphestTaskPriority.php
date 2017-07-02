<?php

final class ManiphestTaskPriority extends ManiphestConstants {

  const UNKNOWN_PRIORITY_KEYWORD = '!!unknown!!';

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
   * Get the canonical keyword for a given priority constant.
   *
   * @return string|null Keyword, or `null` if no keyword is configured.
   */
  public static function getKeywordForTaskPriority($priority) {
    $map = self::getConfig();

    $spec = idx($map, $priority);
    if (!$spec) {
      return null;
    }

    $keywords = idx($spec, 'keywords');
    if (!$keywords) {
      return null;
    }

    return head($keywords);
  }


  /**
   * Get a map of supported alternate names for each priority.
   *
   * Keys are aliases, like "wish" and "wishlist". Values are canonical
   * priority keywords, like "wishlist".
   *
   * @return map<string, string> Map of aliases to canonical priority keywords.
   */
  public static function getTaskPriorityAliasMap() {
    $keyword_map = self::getTaskPriorityKeywordsMap();

    $result = array();
    foreach ($keyword_map as $key => $keywords) {
      $target = self::getKeywordForTaskPriority($key);
      if ($target === null) {
        continue;
      }

      // NOTE: Include the raw priority value, like "25", in the list of
      // aliases. This supports legacy sources like saved EditEngine forms.
      $result[$key] = $target;

      foreach ($keywords as $keyword) {
        $result[$keyword] = $target;
      }
    }

    return $result;
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

  public static function getTaskPriorityFromKeyword($keyword) {
    $map = self::getTaskPriorityKeywordsMap();

    foreach ($map as $priority => $keywords) {
      if (in_array($keyword, $keywords)) {
        return $priority;
      }
    }

    return null;
  }

  public static function isDisabledPriority($priority) {
    $config = idx(self::getConfig(), $priority, array());
    return idx($config, 'disabled', false);
  }

  public static function getConfig() {
    $config = PhabricatorEnv::getEnvConfig('maniphest.priorities');
    krsort($config);
    return $config;
  }

  private static function isValidPriorityKeyword($keyword) {
    if (!strlen($keyword) || strlen($keyword) > 64) {
      return false;
    }

    // Alphanumeric, but not exclusively numeric
    if (!preg_match('/^(?![0-9]*$)[a-zA-Z0-9]+$/', $keyword)) {
      return false;
    }
    return true;
  }

  public static function validateConfiguration($config) {
    if (!is_array($config)) {
      throw new Exception(
        pht(
          'Configuration is not valid. Maniphest priority configurations '.
          'must be dictionaries.',
          $config));
    }

    $all_keywords = array();
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
          'keywords' => 'list<string>',
          'short' => 'optional string',
          'color' => 'optional string',
          'disabled' => 'optional bool',
        ));

      $keywords = $value['keywords'];
      foreach ($keywords as $keyword) {
        if (!self::isValidPriorityKeyword($keyword)) {
          throw new Exception(
            pht(
              'Key "%s" is not a valid priority keyword. Priority keywords '.
              'must be 1-64 alphanumeric characters and cannot be '.
              'exclusively digits. For example, "%s" or "%s" are '.
              'reasonable choices.',
              $keyword,
              'low',
              'critical'));
        }

        if (isset($all_keywords[$keyword])) {
          throw new Exception(
            pht(
              'Two different task priorities ("%s" and "%s") have the same '.
              'keyword ("%s"). Keywords must uniquely identify priorities.',
              $value['name'],
              $all_keywords[$keyword],
              $keyword));
        }

        $all_keywords[$keyword] = $value['name'];
      }
    }
  }

}
