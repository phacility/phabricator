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

echo "Cleaning up old Herald rule applied rows...\n";

$rules = id(new HeraldRule())->loadAll();
foreach ($rules as $key => $rule) {
  $first_policy = HeraldRepetitionPolicyConfig::toInt(
    HeraldRepetitionPolicyConfig::FIRST);
  if ($rule->getRepetitionPolicy() != $first_policy) {
    unset($rules[$key]);
  }
}

$conn_w = id(new HeraldRule())->establishConnection('w');

$clause = '';
if ($rules) {
  $clause = qsprintf(
    $conn_w,
    'WHERE ruleID NOT IN (%Ld)',
    mpull($rules, 'getID'));
}

echo "This may take a moment";
do {
  queryfx(
    $conn_w,
    'DELETE FROM %T %Q LIMIT 1000',
    HeraldRule::TABLE_RULE_APPLIED,
    $clause);
  echo ".";
} while ($conn_w->getAffectedRows());

echo "\n";
echo "Done.\n";
