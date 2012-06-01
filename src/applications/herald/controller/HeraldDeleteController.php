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

final class HeraldDeleteController extends HeraldController {

  private $id;

  public function getFilter() {
    // note this controller is only used from a dialog-context at the moment
    // and there is actually no "delete" filter
    return 'delete';
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $rule = id(new HeraldRule())->load($this->id);
    if (!$rule) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();
    $user = $request->getUser();

    // Anyone can delete a global rule, but only the rule owner can delete a
    // personal one.
    if ($rule->getRuleType() == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL) {
      if ($user->getPHID() != $rule->getAuthorPHID()) {
        return new Aphront400Response();
      }
    }

    if ($request->isFormPost()) {
      $rule->openTransaction();
        $rule->logEdit($user->getPHID(), 'delete');
        $rule->delete();
      $rule->saveTransaction();
      return id(new AphrontReloadResponse())->setURI('/herald/');
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($request->getUser());
    $dialog->setTitle('Really delete this rule?');
    $dialog->appendChild(
      "Are you sure you want to delete the rule ".
      "'<strong>".phutil_escape_html($rule->getName())."</strong>'?");
    $dialog->addSubmitButton('Delete');
    $dialog->addCancelButton('/herald/');
    $dialog->setSubmitURI($request->getPath());

    return id(new AphrontDialogResponse())->setDialog($dialog);

  }

}
