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

final class PhabricatorProjectUpdateController
  extends PhabricatorProjectController {

  private $id;
  private $action;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $project = id(new PhabricatorProject())->load($this->id);
    if (!$project) {
      return new Aphront404Response();
    }

    $process_action = false;
    switch ($this->action) {
      case 'join':
        $process_action = $request->isFormPost();
        break;
      case 'leave':
        $process_action = $request->isDialogFormPost();
        break;
      default:
        return new Aphront404Response();
    }

    $project_uri = '/project/view/'.$project->getID().'/';

    if ($process_action) {
      $xactions = array();

      switch ($this->action) {
        case 'join':
          $affils = $project->loadAffiliations();
          $affils = mpull($affils, null, 'getUserPHID');
          if (empty($affils[$user->getPHID()])) {
            $affils[$user->getPHID()] = true;

            $xaction = new PhabricatorProjectTransaction();
            $xaction->setTransactionType(
              PhabricatorProjectTransactionType::TYPE_MEMBERS);
            $xaction->setNewValue(array_keys($affils));
            $xactions[] = $xaction;
          }
          break;
        case 'leave':
          $affils = $project->loadAffiliations();
          $affils = mpull($affils, null, 'getUserPHID');
          if (isset($affils[$user->getPHID()])) {
            unset($affils[$user->getPHID()]);

            $xaction = new PhabricatorProjectTransaction();
            $xaction->setTransactionType(
              PhabricatorProjectTransactionType::TYPE_MEMBERS);
            $xaction->setNewValue(array_keys($affils));
            $xactions[] = $xaction;
          }
          break;
      }

      if ($xactions) {
        $editor = new PhabricatorProjectEditor($project);
        $editor->setUser($user);
        $editor->applyTransactions($xactions);
      }

      return id(new AphrontRedirectResponse())->setURI($project_uri);
    }

    $dialog = null;
    switch ($this->action) {
      case 'leave':
        $dialog = new AphrontDialogView();
        $dialog->setUser($user);
        $dialog->setTitle('Really leave project?');
        $dialog->appendChild(
          '<p>Your tremendous contributions to this project will be sorely '.
          'missed. Are you sure you want to leave?</p>');
        $dialog->addCancelButton($project_uri);
        $dialog->addSubmitButton('Leave Project');
        break;
      default:
        return new Aphront404Response();
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
