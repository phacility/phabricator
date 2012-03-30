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

abstract class HeraldObjectAdapter {

  abstract public function getPHID();
  abstract public function getHeraldName();
  abstract public function getHeraldTypeName();
  abstract public function getHeraldField($field_name);
  abstract public function applyHeraldEffects(array $effects);

  public static function applyFlagEffect(HeraldEffect $effect, $phid) {
    $color = $effect->getTarget();

    // TODO: Silly that we need to load this again here.
    $rule = id(new HeraldRule())->load($effect->getRuleID());
    $user = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $rule->getAuthorPHID());

    $flag = PhabricatorFlagQuery::loadUserFlag($user, $phid);
    if ($flag) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        'Object already flagged.');
    }

    $handle = PhabricatorObjectHandleData::loadOneHandle($phid);

    $flag = new PhabricatorFlag();
    $flag->setOwnerPHID($user->getPHID());
    $flag->setType($handle->getType());
    $flag->setObjectPHID($handle->getPHID());

    // TOOD: Should really be transcript PHID, but it doesn't exist yet.
    $flag->setReasonPHID($user->getPHID());

    $flag->setColor($color);
    $flag->setNote('Flagged by Herald Rule "'.$rule->getName().'".');
    $flag->save();

    return new HeraldApplyTranscript(
      $effect,
      true,
      'Added flag.');
  }

}

