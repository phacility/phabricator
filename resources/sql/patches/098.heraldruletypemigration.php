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

echo "Checking for rules that can be converted to 'personal'. ";

$rules = id(new HeraldRule())->loadAll();

foreach ($rules as $rule) {
  if ($rule->getRuleType() !== HeraldRuleTypeConfig::RULE_TYPE_PERSONAL) {
    $actions = $rule->loadActions();
    $can_be_personal = true;
    foreach ($actions as $action) {
      $target = $action->getTarget();
      if (is_array($target)) {
        if (count($target) > 1) {
          $can_be_personal = false;
          break;
        } else {
          $targetPHID = head($target);
          if ($targetPHID !== $rule->getAuthorPHID()) {
            $can_be_personal = false;
            break;
          }
        }
      } else if ($target) {
        if ($target !== $rule->getAuthorPHID()) {
          $can_be_personal = false;
          break;
        }
      }
    }

    if ($can_be_personal) {
      $rule->setRuleType(HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
      queryfx(
        $rule->establishConnection('w'),
        'UPDATE %T SET ruleType = %s WHERE id = %d',
        $rule->getTableName(),
        $rule->getRuleType(),
        $rule->getID());

      echo "Setting rule '" . $rule->getName() . "' to personal. ";
    }
  }
}

echo "Done. ";
