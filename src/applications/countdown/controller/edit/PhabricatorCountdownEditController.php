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

class PhabricatorCountdownEditController
  extends PhabricatorCountdownController {

  private $id;
  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $action_label = 'Create Timer';

    if ($this->id)  {
      $timer = id(new PhabricatorTimer())->load($this->id);
      // If no timer is found
      if (!$timer) {
        return new Aphront404Response();
      }

      if (($timer->getAuthorPHID() != $user->getPHID())
          && $user->getIsAdmin() == false) {
        return new Aphront404Response();
      }

      $action_label = 'Update Timer';
    } else {
      $timer = new PhabricatorTimer();
      $timer->setDatePoint(time());
    }

    $error_view = null;
    $e_text = null;

    if ($request->isFormPost()) {
      $errors = array();
      $title = $request->getStr('title');
      $datepoint = $request->getStr('datepoint');
      $timestamp = strtotime($datepoint);

      $e_text = null;
      if (!strlen($title)) {
        $e_text = 'Required';
        $errors[] = 'You must give it a name';
      }

      if ($timestamp === false) {
        $errors[] = 'You entered an incorrect date. You can enter date like'.
          ' \'2011-06-26 13:33:37\' to create an event at'.
          ' 13:33:37 on the 26th of June 2011';
      }

      $timer->setTitle($title);
      $timer->setDatePoint($timestamp);

      if (!count($errors)) {
        $timer->setAuthorPHID($user->getPHID());
        $timer->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/countdown/'.$timer->getID().'/');
      }
      else {
        $error_view = id(new AphrontErrorView())
          ->setErrors($errors)
          ->setTitle('It\'s not The Final Countdown (du nu nuuu nun)' .
            ' until you fix these problem');
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setValue($timer->getTitle())
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('End date')
          ->setValue(strftime("%F %H:%M:%S", $timer->getDatePoint()))
          ->setName('datepoint')
          ->setCaption('Post any date that is parsable by strtotime'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/countdown/')
          ->setValue($action_label));

    $panel = id(new AphrontPanelView())
      ->setWidth(AphrontPanelView::WIDTH_FORM)
      ->setHeader($action_label)
      ->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Edit Countdown',
      ));
  }
}
