<?php

/**
 * @task validate Configuration Validation
 */
final class ManiphestTaskCategory extends ManiphestConstants {

  private static function getCategoryConfig() {
    return PhabricatorEnv::getEnvConfig('maniphest.categories');
  }

  private static function getEnabledCategoryMap() {
    $spec = self::getCategoryConfig();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    foreach ($spec as $const => $category) {
      if ($is_serious && !empty($category['silly'])) {
        unset($spec[$const]);
      }
    }

    return $spec;
  }

  public static function getTaskCategoryMap() {
    $result = array();
    foreach (self::getEnabledCategoryMap() as $const => $category) {
      $temp = array();
      foreach ($category['subcategories'] as $subcategory) {
        $val = $category['app'] . " / " . $category['name'] . " / " . $subcategory;
        $temp[$val] = $subcategory;
      }
      asort($temp);
      $result[$category['app'] . " / " . $category['name']] = $temp;
    }
    asort($result);
    return array_merge(array("NA" => "NA"), $result);
  }

  public static function getTaskCategoryName($category) {
    return self::getCategoryAttribute($category, 'name', pht('Unknown Category'));
  }

  public static function renderFullDescription($category, $priority) {
    $name = self::getTaskCategoryName($category);
    $icon = 'fa-tag';

    $tag = id(new PHUITagView())
      ->setName($name)
      ->setIcon($icon)
      ->setType(PHUITagView::TYPE_SHADE);

    return $tag;
  }

  public static function isDisabledCategory($category) {
    return self::getCategoryAttribute($category, 'disabled');
  }

  private static function getCategoryAttribute($category, $key, $default = null) {
    $config = self::getCategoryConfig();

    $spec = idx($config, $category);
    if ($spec) {
      return idx($spec, $key, $default);
    }

    return $default;
  }


/* -(  Configuration Validation  )------------------------------------------- */


  /**
   * @task validate
   */
  public static function isValidCategoryConstant($constant) {
    return strlen($constant);
  }

  /**
   * @task validate
   */
  public static function validateConfiguration(array $config) {
    foreach ($config as $key => $value) {
      if (!self::isValidCategoryConstant($key)) {
        throw new Exception(
          pht(
            'Key "%s" is not a valid category constant. Category constants '.
            'must be alphanumeric.',
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
          'app' => 'string',
          'subcategories' => 'optional list<string>',
          'silly' => 'optional bool',
          'disabled' => 'optional bool',
        ));
    }

  }

}
