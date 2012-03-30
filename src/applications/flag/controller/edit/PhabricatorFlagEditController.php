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

final class PhabricatorFlagEditController extends PhabricatorFlagController {

  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phid = $this->phid;
    $handle = PhabricatorObjectHandleData::loadOneHandle($phid);

    if (!$handle->isComplete()) {
      return new Aphront404Response();
    }

    $flag = PhabricatorFlagQuery::loadUserFlag($user, $phid);

    if (!$flag) {
      $flag = new PhabricatorFlag();
      $flag->setOwnerPHID($user->getPHID());
      $flag->setType($handle->getType());
      $flag->setObjectPHID($handle->getPHID());
      $flag->setReasonPHID($user->getPHID());
    }

    if ($request->isDialogFormPost()) {
      $flag->setColor($request->getInt('color'));
      $flag->setNote($request->getStr('note'));
      $flag->save();

      return id(new AphrontReloadResponse())->setURI('/flag/');
    }

    $type_name = $handle->getTypeName();

    $dialog = new AphrontDialogView();
    $dialog->setUser($user);

    $dialog->setTitle("Flag {$type_name}");

    $form = new AphrontFormLayoutView();

    $is_new = !$flag->getID();

    if ($is_new) {
      $form
        ->appendChild(
          "<p>You can flag this {$type_name} if you want to remember to look ".
          "at it later.</p><br />");
    }

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('color')
          ->setLabel('Flag Color')
          ->setValue($flag->getColor())
          ->setOptions(PhabricatorFlagColor::getColorNameMap()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setName('note')
          ->setLabel('Note')
          ->setValue($flag->getNote()));

    $dialog->appendChild($form);

    $dialog->addCancelButton($handle->getURI());
    $dialog->addSubmitButton(
      $is_new ? "Flag {$type_name}" : 'Save');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
