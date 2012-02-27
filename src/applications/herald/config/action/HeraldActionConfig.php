<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class HeraldActionConfig {

  const ACTION_ADD_CC       = 'addcc';
  const ACTION_REMOVE_CC    = 'remcc';
  const ACTION_EMAIL        = 'email';
  const ACTION_NOTHING      = 'nothing';
  const ACTION_AUDIT        = 'audit';

  public static function getActionMessageMapForRuleType($rule_type) {
    $generic_mappings =
      array(
        self::ACTION_NOTHING      => 'Do nothing',
      );

    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        $specific_mappings =
          array(
            self::ACTION_ADD_CC       => 'Add emails to CC',
            self::ACTION_REMOVE_CC    => 'Remove emails from CC',
            self::ACTION_EMAIL        => 'Send an email to',
            self::ACTION_AUDIT        => 'Trigger an Audit for project',
          );
        break;
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        $specific_mappings =
          array(
            self::ACTION_ADD_CC       => 'CC me',
            self::ACTION_REMOVE_CC    => 'Remove me from CC',
            self::ACTION_EMAIL        => 'Email me',
            self::ACTION_AUDIT        => 'Trigger an Audit by me',
          );
        break;
      default:
        throw new Exception("Unknown rule type '${rule_type}'");
    }
    return $generic_mappings + $specific_mappings;
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
            self::ACTION_NOTHING,
          ));
      case HeraldContentTypeConfig::CONTENT_TYPE_COMMIT:
        return array_select_keys(
          $map,
          array(
            self::ACTION_EMAIL,
            self::ACTION_AUDIT,
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
        throw new Exception("Unknown content type '{$type}'.");
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
