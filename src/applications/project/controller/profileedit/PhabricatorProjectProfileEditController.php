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

class PhabricatorProjectProfileEditController
  extends PhabricatorProjectController {

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
    $profile = $project->loadProfile();

    if (empty($profile)) {
      $profile = new PhabricatorProjectProfile();
    }

    $options = PhabricatorProjectStatus::getStatusMap();

    $e_name = true;
    $errors = array();
    if ($request->isFormPost()) {
      $project->setName($request->getStr('name'));
      $project->setStatus($request->getStr('status'));
      $profile->setBlurb($request->getStr('blurb'));

      if (!strlen($project->getName())) {
        $e_name = 'Required';
        $errors[] = 'Project name is required.';
      } else {
        $e_name = null;
      }

      if (!empty($_FILES['image'])) {
        $err = idx($_FILES['image'], 'error');
        if ($err != UPLOAD_ERR_NO_FILE) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['image'],
            array(
              'authorPHID' => $user->getPHID(),
            ));
          $okay = $file->isTransformableImage();
          if ($okay) {
            $xformer = new PhabricatorImageTransformer();
            $xformed = $xformer->executeProfileTransform(
              $file,
              $width = 280,
              $min_height = 140,
              $max_height = 420);
            $profile->setProfileImagePHID($xformed->getPHID());
          } else {
            $errors[] =
              'Only valid image files (jpg, jpeg, png or gif) '.
              'will be accepted.';
          }
        }
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

    $header_name = 'Edit Project';
    $title = 'Edit Project';
    $action = '/project/edit/'.$project->getID().'/';

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction($action)
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($project->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Project Status')
          ->setName('status')
          ->setOptions($options)
          ->setValue($project->getStatus()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Blurb')
          ->setName('blurb')
          ->setValue($profile->getBlurb()))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel('Change Image')
          ->setName('image'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/project/view/'.$project->getID().'/')
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
