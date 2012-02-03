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

class DifferentialInlineCommentEditController extends DifferentialController {

  private $revisionID;

  public function willProcessRequest(array $data) {
    $this->revisionID = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $changeset = $request->getInt('changeset');
    $is_new = $request->getBool('is_new');
    $on_right = $request->getBool('on_right');
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

    switch ($op) {
      case 'delete':
        $inline = $this->loadInlineCommentForEditing($inline_id);

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
        $inline = $this->loadInlineCommentForEditing($inline_id);

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
            nonempty($text, $inline->getContent())));

        return id(new AphrontDialogResponse())->setDialog($edit_dialog);
      case 'create':

        if (!$request->isFormPost() || !strlen($text)) {
          return $this->buildEmptyResponse();
        }

        // Verify revision and changeset correspond to actual objects.
        $revision_obj = id(new DifferentialRevision())->load($this->revisionID);
        $changeset_obj = id(new DifferentialChangeset())->load($changeset);
        if (!$revision_obj || !$changeset_obj) {
          throw new Exception("Invalid revision ID or changeset ID!");
        }

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

        return $this->buildRenderedCommentResponse($inline, $on_right);

      case 'reply':
      default:

        if ($op == 'reply') {
          $inline = $this->loadInlineComment($inline_id);
          // Override defaults.
          $changeset = $inline->getChangesetID();
          $is_new = $inline->getIsNewFile();
          $number = $inline->getLineNumber();
          $length = $inline->getLineLength();
          $edit_dialog->setTitle('Reply to Inline Comment');
        } else {
          $edit_dialog->setTitle('New Inline Comment');
        }

        $edit_dialog->addHiddenInput('op', 'create');
        $edit_dialog->addHiddenInput('changeset', $changeset);
        $edit_dialog->addHiddenInput('is_new', $is_new);
        $edit_dialog->addHiddenInput('number', $number);
        $edit_dialog->addHiddenInput('length', $length);

        $edit_dialog->appendChild($this->renderTextArea($text));

        return id(new AphrontDialogResponse())->setDialog($edit_dialog);
    }
  }

  private function buildRenderedCommentResponse(
    DifferentialInlineComment $inline,
    $on_right) {

    $request = $this->getRequest();
    $user = $request->getUser();

    $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();

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
    return javelin_render_tag(
      'textarea',
      array(
        'class' => 'differential-inline-comment-edit-textarea',
        'sigil' => 'differential-inline-comment-edit-textarea',
        'name' => 'text',
      ),
      phutil_escape_html($text));
  }

  private function loadInlineComment($id) {
    $inline = null;

    if ($id) {
      $inline = id(new DifferentialInlineComment())->load($id);
    }

    if (!$inline) {
      throw new Exception("No such inline comment!");
    }

    return $inline;
  }

  private function loadInlineCommentForEditing($id) {
    $inline = $this->loadInlineComment($id);
    $user = $this->getRequest()->getUser();

    if (!$this->canEditInlineComment($user, $inline, $this->revisionID)) {
      throw new Exception("That comment is not editable!");
    }
    return $inline;
  }

  private function canEditInlineComment(
    PhabricatorUser $user,
    DifferentialInlineComment $inline,
    $revision_id) {

    // Only the author may edit a comment.
    if ($inline->getAuthorPHID() != $user->getPHID()) {
      return false;
    }

    // Saved comments may not be edited.
    if ($inline->getCommentID()) {
      return false;
    }

    // Inline must be attached to the active revision.
    if ($inline->getRevisionID() != $revision_id) {
      return false;
    }

    return true;
  }

}
