<?php

final class HeraldActionConfig {

  const ACTION_ADD_CC       = 'addcc';
  const ACTION_REMOVE_CC    = 'remcc';
  const ACTION_EMAIL        = 'email';
  const ACTION_NOTHING      = 'nothing';
  const ACTION_AUDIT        = 'audit';
  const ACTION_FLAG         = 'flag';

  public static function getActionMessageMapForRuleType($rule_type) {
    $generic_mappings = array(
      self::ACTION_NOTHING      => 'Do nothing',
      self::ACTION_ADD_CC       => 'Add emails to CC',
      self::ACTION_REMOVE_CC    => 'Remove emails from CC',
      self::ACTION_EMAIL        => 'Send an email to',
      self::ACTION_AUDIT        => 'Trigger an Audit',
      self::ACTION_FLAG         => 'Mark with flag',
    );

    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        $specific_mappings = array(
          self::ACTION_AUDIT        => 'Trigger an Audit for project',
        );
        break;
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        $specific_mappings = array(
          self::ACTION_ADD_CC       => 'CC me',
          self::ACTION_REMOVE_CC    => 'Remove me from CC',
          self::ACTION_EMAIL        => 'Email me',
          self::ACTION_AUDIT        => 'Trigger an Audit by me',
        );
        break;
      case null:
        $specific_mappings = array();
        // Use generic mappings, used on transcript.
        break;
      default:
        throw new Exception("Unknown rule type '${rule_type}'");
    }
    return $specific_mappings + $generic_mappings;
  }

  public static function getActionMessageMap($content_type,
                                             $rule_type) {
    $map = self::getActionMessageMapForRuleType($rule_type);
    switch ($content_type) {
      case HeraldContentTypeConfig::CONTENT_TYPE_DIFFERENTIAL:
        return array_select_keys(
          $map,
          array(
            self::ACTION_ADD_CC,
            self::ACTION_REMOVE_CC,
            self::ACTION_EMAIL,
            self::ACTION_FLAG,
            self::ACTION_NOTHING,
          ));
      case HeraldContentTypeConfig::CONTENT_TYPE_COMMIT:
        return array_select_keys(
          $map,
          array(
            self::ACTION_EMAIL,
            self::ACTION_AUDIT,
            self::ACTION_FLAG,
            self::ACTION_NOTHING,
          ));
      case HeraldContentTypeConfig::CONTENT_TYPE_MERGE:
        return array_select_keys(
          $map,
          array(
            self::ACTION_EMAIL,
            self::ACTION_NOTHING,
          ));
      case HeraldContentTypeConfig::CONTENT_TYPE_OWNERS:
        return array_select_keys(
          $map,
          array(
            self::ACTION_EMAIL,
            self::ACTION_NOTHING,
          ));
      default:
        throw new Exception("Unknown content type '{$content_type}'.");
    }
  }

  /**
   * Create a HeraldAction to save from data.
   *
   * $data is of the form:
   *   array(
   *     0 => <action type>
   *     1 => array(<targets>)
   *   )
   */
  public static function willSaveAction($rule_type,
                                        $author_phid,
                                        $data) {
    $obj = new HeraldAction();
    $obj->setAction($data[0]);

    // for personal rule types, set the target to be the owner of the rule
    if ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL) {
      switch ($obj->getAction()) {
        case HeraldActionConfig::ACTION_EMAIL:
        case HeraldActionConfig::ACTION_ADD_CC:
        case HeraldActionConfig::ACTION_REMOVE_CC:
        case HeraldActionConfig::ACTION_AUDIT:
          $data[1] = array($author_phid => $author_phid);
          break;
        case HeraldActionConfig::ACTION_FLAG:
          // Make sure flag color is valid; set to blue if not.
          $color_map = PhabricatorFlagColor::getColorNameMap();
          if (empty($color_map[$data[1]])) {
            $data[1] = PhabricatorFlagColor::COLOR_BLUE;
          }
          break;
        case HeraldActionConfig::ACTION_NOTHING:
          break;
        default:
          throw new Exception('Unrecognized action type: ' .
                              $obj->getAction());
      }
    }

    if (is_array($data[1])) {
      $obj->setTarget(array_keys($data[1]));
    } else {
      $obj->setTarget($data[1]);
    }

    return $obj;
  }

}
