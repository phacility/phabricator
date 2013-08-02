<?php

final class HeraldActionConfig {

  const ACTION_ADD_CC       = 'addcc';
  const ACTION_REMOVE_CC    = 'remcc';
  const ACTION_EMAIL        = 'email';
  const ACTION_NOTHING      = 'nothing';
  const ACTION_AUDIT        = 'audit';
  const ACTION_FLAG         = 'flag';

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
