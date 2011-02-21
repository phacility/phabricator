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

class PhabricatorProjectEditController
  extends PhabricatorProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->id) {
      $project = id(new PhabricatorProject())->load($this->id);
      if (!$project) {
        return new Aphront404Response();
      }
      $profile = id(new PhabricatorProjectProfile())->loadOneWhere(
        'projectPHID = %s',
        $project->getPHID());
    } else {
      $project = new PhabricatorProject();
      $project->setAuthorPHID($user->getPHID());
    }

    if (empty($profile)) {
      $profile = new PhabricatorProjectProfile();
    }

    $e_name = true;
    $errors = array();

    if ($request->isFormPost()) {

      $project->setName($request->getStr('name'));
      $profile->setBlurb($request->getStr('blurb'));

      if (!strlen($project->getName())) {
        $e_name = 'Required';
        $errors[] = 'Project name is required.';
      } else {
        $e_name = null;
      }

      if (!$errors) {
        $project->save();
        $profile->setProjectPHID($project->getPHID());
        $profile->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/project/view/'.$project->getID().'/');
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    }

    if ($project->getID()) {
      $header_name = 'Edit Project';
      $title = 'Edit Project';
      $action = '/project/edit/'.$project->getID().'/';
    } else {
      $header_name = 'Create Project';
      $title = 'Create Project';
      $action = '/project/edit/';
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction($action)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($project->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Blurb')
          ->setName('blurb')
          ->setValue($profile->getBlurb()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/project/')
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setHeader($header_name);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => $title,
      ));
  }

}
