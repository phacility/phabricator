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

class PhabricatorProjectQuickCreateController
  extends PhabricatorProjectController {


  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $project = new PhabricatorProject();
    $project->setAuthorPHID($user->getPHID());
    $profile = new PhabricatorProjectProfile();

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

        return id(new AphrontAjaxResponse())
          ->setContent(array(
            'phid' => $project->getPHID(),
            'name' => $project->getName(),
          ));
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setWidth(AphrontErrorView::WIDTH_DIALOG);
      $error_view->setErrors($errors);
    }

    $form = id(new AphrontFormLayoutView())
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
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setValue($profile->getBlurb()));

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle('Create a New Project')
      ->appendChild($error_view)
      ->appendChild($form)
      ->addSubmitButton('Create Project')
      ->addCancelButton('/project/');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
