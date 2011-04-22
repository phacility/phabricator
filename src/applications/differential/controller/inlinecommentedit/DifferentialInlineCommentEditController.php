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
    $inline_id = $request->getInt('id');

    $user = $request->getUser();

    $submit_uri = '/differential/comment/inline/edit/'.$this->revisionID.'/';

    $edit_dialog = new AphrontDialogView();
    $edit_dialog->setUser($user);
    $edit_dialog->setSubmitURI($submit_uri);

    $edit_dialog->addHiddenInput('on_right', $on_right);

    $edit_dialog->addSubmitButton();
    $edit_dialog->addCancelButton('#');

    $inline = null;
    if ($inline_id) {
      $inline = id(new DifferentialInlineComment())
        ->load($inline_id);

      if (!$inline ||
          $inline->getAuthorPHID() != $user->getPHID() ||
          $inline->getCommentID() ||
          $inline->getRevisionID() != $this->revisionID) {
        throw new Exception("That comment is not editable!");
      }
    }

    switch ($op) {
      case 'delete':
        if (!$inline) {
          return new Aphront400Response();
        }

        if ($request->isFormPost()) {
          $inline->delete();
          return $this->buildEmptyResponse();
        }

        $edit_dialog->setTitle('Really delete this comment?');
        $edit_dialog->addHiddenInput('id', $inline_id);
        $edit_dialog->addHiddenInput('op', 'delete');
        $edit_dialog->appendChild(
          '<p>Delete this inline comment?</p>');

        return id(new AphrontDialogResponse())->setDialog($edit_dialog);
      case 'edit':
        if (!$inline) {
          return new Aphront400Response();
        }

        if ($request->isFormPost()) {
          if (strlen($text)) {
            $inline->setContent($text);
            $inline->setCache(null);
            $inline->save();
            return $this->buildRenderedCommentResponse(
              $inline,
              $on_right);
          } else {
            $inline->delete();
            return $this->buildEmptyResponse();
          }
        }

        $edit_dialog->setTitle('Edit Inline Comment');

        $edit_dialog->addHiddenInput('id', $inline_id);
        $edit_dialog->addHiddenInput('op', 'edit');

        $edit_dialog->appendChild(
          $this->renderTextArea(
            $inline->getContent()));

        return id(new AphrontDialogResponse())->setDialog($edit_dialog);
      case 'create':

        if (!$request->isFormPost() || !strlen($text)) {
          return $this->buildEmptyResponse();
        }

        $inline = id(new DifferentialInlineComment())
          ->setRevisionID($this->revisionID)
          ->setChangesetID($changeset)
          ->setCommentID(null)
          ->setAuthorPHID($user->getPHID())
          ->setLineNumber($number)
          ->setLineLength($length)
          ->setIsNewFile($on_right)
          ->setContent($text)
          ->save();

        return $this->buildRenderedCommentResponse($inline, $on_right);
      default:
        $edit_dialog->setTitle('New Inline Comment');

        $edit_dialog->addHiddenInput('op', 'create');
        $edit_dialog->addHiddenInput('changeset', $changeset);
        $edit_dialog->addHiddenInput('is_new', $is_new);
        $edit_dialog->addHiddenInput('number', $number);
        $edit_dialog->addHiddenInput('length', $length);

        $edit_dialog->appendChild($this->renderTextArea(''));

        return id(new AphrontDialogResponse())->setDialog($edit_dialog);
    }
  }

  private function buildRenderedCommentResponse(
    DifferentialInlineComment $inline,
    $on_right) {

    $request = $this->getRequest();
    $user = $request->getUser();

    $factory = new DifferentialMarkupEngineFactory();
    $engine = $factory->newDifferentialCommentMarkupEngine();

    $phids = array($user->getPHID());

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $view = new DifferentialInlineCommentView();
    $view->setInlineComment($inline);
    $view->setOnRight($on_right);
    $view->setBuildScaffolding(true);
    $view->setMarkupEngine($engine);
    $view->setHandles($handles);
    $view->setEditable(true);

    return id(new AphrontAjaxResponse())
      ->setContent(
        array(
          'inlineCommentID' => $inline->getID(),
          'markup'          => $view->render(),
        ));
  }

  private function buildEmptyResponse() {
    return id(new AphrontAjaxResponse())
      ->setContent(
        array(
          'markup'          => '',
        ));
  }

  private function renderTextArea($text) {
    return phutil_render_tag(
      'textarea',
      array(
        'class' => 'differential-inline-comment-edit-textarea',
        'name' => 'text',
      ),
      $text);
  }

}
