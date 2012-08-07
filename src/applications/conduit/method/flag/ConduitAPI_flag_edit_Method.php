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

/**
 * @group conduit
 */
final class ConduitAPI_flag_edit_Method extends ConduitAPI_flag_Method {

  public function getMethodDescription() {
    return "Create or modify a flag.";
  }

  public function defineParamTypes() {
    return array(
      'objectPHID' => 'required phid',
      'color'      => 'optional int',
      'note'       => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'dict';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser()->getPHID();
    $phid = $request->getValue('objectPHID');
    $new = false;

    $flag = id(new PhabricatorFlag())->loadOneWhere(
      'objectPHID = %s AND ownerPHID = %s',
      $phid,
      $user);
    if ($flag) {
      $params = $request->getAllParameters();
      if (isset($params['color'])) {
        $flag->setColor($params['color']);
      }
      if (isset($params['note'])) {
        $flag->setNote($params['note']);
      }
    } else {
      $default_color = PhabricatorFlagColor::COLOR_BLUE;
      $flag = id(new PhabricatorFlag())
        ->setOwnerPHID($user)
        ->setType(phid_get_type($phid))
        ->setObjectPHID($phid)
        ->setReasonPHID($user)
        ->setColor($request->getValue('color', $default_color))
        ->setNote($request->getValue('note', ''));
      $new = true;
    }
    $this->attachHandleToFlag($flag);
    $flag->save();
    $ret = $this->buildFlagInfoDictionary($flag);
    $ret['new'] = $new;
    return $ret;
  }

}
