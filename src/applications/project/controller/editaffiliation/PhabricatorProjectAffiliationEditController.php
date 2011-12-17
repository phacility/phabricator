<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorProjectAffiliationEditController
  extends PhabricatorProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $project = id(new PhabricatorProject())->load($this->id);
    if (!$project) {
      return new Aphront404Response();
    }

    $affiliation = id(new PhabricatorProjectAffiliation())->loadOneWhere(
      'projectPHID = %s AND userPHID = %s',
      $project->getPHID(),
      $user->getPHID());

    if (!$affiliation) {
      $affiliation = new PhabricatorProjectAffiliation();
      $affiliation->setUserPHID($user->getPHID());
      $affiliation->setProjectPHID($project->getPHID());
    }

    if ($request->isFormPost()) {
      $affiliation->setRole($request->getStr('role'));

      if (!strlen($affiliation->getRole())) {
        if ($affiliation->getID()) {
          if ($affiliation->getIsOwner()) {
            $affiliation->setRole('Owner');
            $affiliation->save();
          } else {
            $affiliation->delete();
          }
        }
      } else {
        $affiliation->save();
      }

      return id(new AphrontRedirectResponse())
        ->setURI('/project/view/'.$project->getID().'/');
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction('/project/affiliation/'.$project->getID().'/')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Role')
          ->setName('role')
          ->setValue($affiliation->getRole()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/project/view/'.$project->getID().'/')
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit Project Affiliation');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Edit Project Affiliation',
      ));
  }

}
