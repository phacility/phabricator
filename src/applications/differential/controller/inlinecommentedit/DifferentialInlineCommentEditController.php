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

class DifferentialInlineCommentEditController extends DifferentialController {

  private $revisionID;

  public function willProcessRequest(array $data) {
    $this->revisionID = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $changeset = $request->getInt('changeset');
    $is_new = $request->getInt('is_new');
    $on_right = $request->getInt('on_right');
    $number = $request->getInt('number');
    $length = $request->getInt('length');
    $text = $request->getStr('text');
    $op = $request->getStr('op');

    $user = $request->getUser();

    $submit_uri = '/differential/comment/inline/edit/'.$this->revisionID.'/';

    switch ($op) {
      case 'delete':
        if ($request->isFormPost()) {
          // do the delete;
          return new AphrontAjaxResponse();
        }

        $dialog = new AphrontDialogView();
        $dialog->setTitle('Really delete this comment?');

        return id(new AphrontDialogResponse())->setDialog($dialog);
      case 'edit':
        $dialog = new AphrontDialogView();

        return id(new AphrontDialogResponse())->setDialog($dialog);
      case 'create':

        if (!$request->isFormPost() || !strlen($text)) {
          return new AphrontAjaxResponse();
        }

        $factory = new DifferentialMarkupEngineFactory();
        $engine = $factory->newDifferentialCommentMarkupEngine();
        
        $phids = array($user->getPHID());
        
        $handles = id(new PhabricatorObjectHandleData($phids))
          ->loadHandles();

        $inline = id(new DifferentialInlineComment())
          ->setRevisionID($this->revisionID)
          ->setChangesetID($changeset)
          ->setCommentID(null)
          ->setAuthorPHID($user->getPHID())
          ->setLineNumber($number)
          ->setLineLength($length)
          ->setIsNewFile($is_new)
          ->setContent($text)
          ->save();

        $view = new DifferentialInlineCommentView();
        $view->setInlineComment($inline);
        $view->setOnRight($on_right);
        $view->setBuildScaffolding(true);
        $view->setMarkupEngine($engine);
        $view->setHandles($handles);

        return id(new AphrontAjaxResponse())
          ->setContent(
            array(
              'inlineCommentID' => $inline->getID(),
              'markup'          => $view->render(),
            ));
      default:
        $dialog = new AphrontDialogView();
        $dialog->setUser($user);
        $dialog->setTitle('New Inline Comment');
        $dialog->setSubmitURI($submit_uri);

        $dialog->addHiddenInput('op', 'create');
        $dialog->addHiddenInput('changeset', $changeset);
        $dialog->addHiddenInput('is_new', $is_new);
        $dialog->addHiddenInput('on_right', $on_right);
        $dialog->addHiddenInput('number', $number);
        $dialog->addHiddenInput('length', $length);

        $dialog->addSubmitButton();
        $dialog->addCancelButton('#');
        $dialog->appendChild('<textarea name="text"></textarea>');

        return id(new AphrontDialogResponse())->setDialog($dialog);
    }
  }

}
