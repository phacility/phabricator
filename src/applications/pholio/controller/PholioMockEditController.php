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
 * @group pholio
 */
final class PholioMockEditController extends PholioController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();


    if ($this->id) {
      // TODO: Support!
      throw new Exception("NOT SUPPORTED YET");
    } else {
      $mock = new PholioMock();
      $mock->setAuthorPHID($user->getPHID());
      $mock->setViewPolicy(PhabricatorPolicies::POLICY_USER);

      $title = pht('Create Mock');
    }

    $e_name = true;
    $e_images = true;
    $errors = array();

    if ($request->isFormPost()) {
      $mock->setName($request->getStr('name'));
      $mock->setDescription($request->getStr('description'));
      $mock->setViewPolicy($request->getStr('can_view'));

      if (!strlen($mock->getName())) {
        $e_name = 'Required';
        $errors[] = pht('You must name the mock.');
      }

      $files = array();

      $file_phids = $request->getArr('file_phids');
      if ($file_phids) {
        $files = id(new PhabricatorFileQuery())
          ->setViewer($user)
          ->withPHIDs($file_phids)
          ->execute();
      }

      if (!$files) {
        $e_images = 'Required';
        $errors[] = pht('You must add at least one image to the mock.');
      } else {
        $mock->setCoverPHID(head($files)->getPHID());
      }

      $sequence = 0;

      $images = array();
      foreach ($files as $file) {
        $image = new PholioImage();
        $image->setName('');
        $image->setDescription('');
        $image->setFilePHID($file->getPHID());
        $image->setSequence($sequence++);

        $images[] = $image;
      }


      if (!$errors) {
        // TODO: Make transaction-object based and move to editor.
        $mock->openTransaction();
          $mock->save();
          foreach ($images as $image) {
            $image->setMockID($mock->getID());
            $image->save();
          }
        $mock->saveTransaction();

        return id(new AphrontRedirectResponse())
          ->setURI('/M'.$mock->getID());
      }
    }

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Form Errors'))
        ->setErrors($errors);
    } else {
      $error_view = null;
    }

    if ($this->id) {
      $submit = id(new AphrontFormSubmitControl())
        ->addCancelButton('/M'.$this->id)
        ->setValue(pht('Save'));
    } else {
      $submit = id(new AphrontFormSubmitControl())
        ->addCancelButton($this->getApplicationURI())
        ->setValue(pht('Create'));
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($mock)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFlexible(true)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setValue($mock->getName())
          ->setLabel(pht('Name'))
          ->setError($e_name))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('description')
          ->setValue($mock->getDescription())
          ->setLabel(pht('Description')))
      ->appendChild(
        id(new AphrontFormDragAndDropUploadControl($request))
          ->setName('file_phids')
          ->setLabel(pht('Images'))
          ->setError($e_images))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicyObject($mock)
          ->setPolicies($policies)
          ->setName('can_view'))
      ->appendChild($submit);

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $content = array(
      $header,
      $error_view,
      $form,
    );

    $nav = $this->buildSideNav();
    $nav->selectFilter(null);
    $nav->appendChild($content);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

}
