<?php

/**
 * @task validate Configuration Validation
 */
final class ManiphestTaskEscalation extends ManiphestConstants {

  private static function getEscalationConfig() {
    return PhabricatorEnv::getEnvConfig('maniphest.escalation');
  }

  private static function getEnabledEscalationMap() {
    $spec = self::getEscalationConfig();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    foreach ($spec as $const => $escalation) {
      if ($is_serious && !empty($escalation['silly'])) {
        unset($spec[$const]);
      }
    }

    return $spec;
  }

  public static function getTaskEscalationMap() {
    $result = array();
    foreach (self::getEnabledEscalationMap() as $const => $escalation) {
      $temp = array();
      foreach ($escalation['escalate_to'] as $escalateTo) {
        $val = $escalation['project'] . " / " . $escalateTo;
        $temp[$val] = $escalateTo;
      }
      asort($temp);
      $result[$escalation['project']] = $temp;
    }
    asort($result);
    return array_merge(array("NA" => "NA"), $result);
  }

  public static function getTaskEscalationName($escalation) {
    return self::getEscalationAttribute($escalation, 'project', pht('Unknown Escalation Project'));
  }

  public static function renderFullDescription($escalation, $priority) {
    $name = self::getTaskEscalationName($escalation);
    $icon = 'fa-user';

    $tag = id(new PHUITagView())
      ->setName($name)
      ->setIcon($icon)
      ->setType(PHUITagView::TYPE_SHADE);

    return $tag;
  }

  public static function isDisabledEscalation($escalation) {
    return self::getEscalationAttribute($escalation, 'disabled');
  }

  private static function getEscalationAttribute($escalation, $key, $default = null) {
    $config = self::getEscalationConfig();

    $spec = idx($config, $escalation);
    if ($spec) {
      return idx($spec, $key, $default);
    }

    return $default;
  }


/* -(  Configuration Validation  )------------------------------------------- */


  /**
   * @task validate
   */
  public static function isValidEscalationConstant($constant) {
    return strlen($constant);
  }

  /**
   * @task validate
   */
  public static function validateConfiguration(array $config) {
    foreach ($config as $key => $value) {
      if (!self::isValidEscalationConstant($key)) {
        throw new Exception(
          pht(
            'Key "%s" is not a valid escalation constant. Escalation constants '.
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
          'project' => 'string',
          'escalate_to' => 'optional list<string>',
          'silly' => 'optional bool',
          'disabled' => 'optional bool',
        ));
    }

  }

}
