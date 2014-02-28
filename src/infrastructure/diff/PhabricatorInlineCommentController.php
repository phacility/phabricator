<?php

abstract class PhabricatorInlineCommentController
  extends PhabricatorController {

  abstract protected function createComment();
  abstract protected function loadComment($id);
  abstract protected function loadCommentForEdit($id);
  abstract protected function deleteComment(
    PhabricatorInlineCommentInterface $inline);
  abstract protected function saveComment(
    PhabricatorInlineCommentInterface $inline);

  private $changesetID;
  private $isNewFile;
  private $isOnRight;
  private $lineNumber;
  private $lineLength;
  private $commentText;
  private $operation;
  private $commentID;

  public function getCommentID() {
    return $this->commentID;
  }

  public function getOperation() {
    return $this->operation;
  }

  public function getCommentText() {
    return $this->commentText;
  }

  public function getLineLength() {
    return $this->lineLength;
  }

  public function getLineNumber() {
    return $this->lineNumber;
  }

  public function getIsOnRight() {
    return $this->isOnRight;
  }

  public function getChangesetID() {
    return $this->changesetID;
  }

  public function getIsNewFile() {
    return $this->isNewFile;
  }


  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $this->readRequestParameters();

    switch ($this->getOperation()) {
      case 'delete':
        $inline = $this->loadCommentForEdit($this->getCommentID());

        if ($request->isFormPost()) {
          $this->deleteComment($inline);
          return $this->buildEmptyResponse();
        }

        $dialog = new AphrontDialogView();
        $dialog->setUser($user);
        $dialog->setSubmitURI($request->getRequestURI());

        $dialog->setTitle(pht('Really delete this comment?'));
        $dialog->addHiddenInput('id', $this->getCommentID());
        $dialog->addHiddenInput('op', 'delete');
        $dialog->appendChild(
          phutil_tag('p', array(), pht('Delete this inline comment?')));

        $dialog->addCancelButton('#');
        $dialog->addSubmitButton(pht('Delete'));

        return id(new AphrontDialogResponse())->setDialog($dialog);
      case 'edit':
        $inline = $this->loadCommentForEdit($this->getCommentID());

        $text = $this->getCommentText();

        if ($request->isFormPost()) {
          if (strlen($text)) {
            $inline->setContent($text);
            $this->saveComment($inline);
            return $this->buildRenderedCommentResponse(
              $inline,
              $this->getIsOnRight());
          } else {
            $this->deleteComment($inline);
            return $this->buildEmptyResponse();
          }
        }

        $edit_dialog = $this->buildEditDialog();
        $edit_dialog->setTitle(pht('Edit Inline Comment'));

        $edit_dialog->addHiddenInput('id', $this->getCommentID());
        $edit_dialog->addHiddenInput('op', 'edit');

        $edit_dialog->appendChild(
          $this->renderTextArea(
            nonempty($text, $inline->getContent())));

        return id(new AphrontAjaxResponse())
          ->setContent($edit_dialog->render());
      case 'create':

        $text = $this->getCommentText();

        if (!$request->isFormPost() || !strlen($text)) {
          return $this->buildEmptyResponse();
        }

        $inline = $this->createComment()
          ->setChangesetID($this->getChangesetID())
          ->setAuthorPHID($user->getPHID())
          ->setLineNumber($this->getLineNumber())
          ->setLineLength($this->getLineLength())
          ->setIsNewFile($this->getIsNewFile())
          ->setContent($text);
        $this->saveComment($inline);

        return $this->buildRenderedCommentResponse(
          $inline,
          $this->getIsOnRight());
      case 'reply':
      default:
        $edit_dialog = $this->buildEditDialog();

        if ($this->getOperation() == 'reply') {
          $inline = $this->loadComment($this->getCommentID());

          $edit_dialog->setTitle(pht('Reply to Inline Comment'));
          $changeset = $inline->getChangesetID();
          $is_new = $inline->getIsNewFile();
          $number = $inline->getLineNumber();
          $length = $inline->getLineLength();
        } else {
          $edit_dialog->setTitle(pht('New Inline Comment'));
          $changeset = $this->getChangesetID();
          $is_new = $this->getIsNewFile();
          $number = $this->getLineNumber();
          $length = $this->getLineLength();
        }

        $edit_dialog->addHiddenInput('op', 'create');
        $edit_dialog->addHiddenInput('changeset', $changeset);
        $edit_dialog->addHiddenInput('is_new', $is_new);
        $edit_dialog->addHiddenInput('number', $number);
        $edit_dialog->addHiddenInput('length', $length);

        $text_area = $this->renderTextArea($this->getCommentText());
        $edit_dialog->appendChild($text_area);

        return id(new AphrontAjaxResponse())
          ->setContent($edit_dialog->render());
    }
  }

  private function readRequestParameters() {
    $request = $this->getRequest();

    // NOTE: This isn't necessarily a DifferentialChangeset ID, just an
    // application identifier for the changeset. In Diffusion, it's a Path ID.
    $this->changesetID    = $request->getInt('changeset');

    $this->isNewFile      = (int)$request->getBool('is_new');
    $this->isOnRight      = $request->getBool('on_right');
    $this->lineNumber     = $request->getInt('number');
    $this->lineLength     = $request->getInt('length');
    $this->commentText    = $request->getStr('text');
    $this->commentID      = $request->getInt('id');
    $this->operation      = $request->getStr('op');
  }

  private function buildEditDialog() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $edit_dialog = new DifferentialInlineCommentEditView();
    $edit_dialog->setUser($user);
    $edit_dialog->setSubmitURI($request->getRequestURI());
    $edit_dialog->setOnRight($this->getIsOnRight());
    $edit_dialog->setNumber($this->getLineNumber());
    $edit_dialog->setLength($this->getLineLength());

    return $edit_dialog;
  }

  private function buildEmptyResponse() {
    return id(new AphrontAjaxResponse())
      ->setContent(
        array(
          'markup'          => '',
        ));
  }

  private function buildRenderedCommentResponse(
    PhabricatorInlineCommentInterface $inline,
    $on_right) {

    $request = $this->getRequest();
    $user = $request->getUser();

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    $engine->addObject(
      $inline,
      PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);
    $engine->process();

    $phids = array($user->getPHID());

    $handles = $this->loadViewerHandles($phids);

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

  private function renderTextArea($text) {
    return id(new PhabricatorRemarkupControl())
      ->setUser($this->getRequest()->getUser())
      ->setSigil('differential-inline-comment-edit-textarea')
      ->setName('text')
      ->setValue($text);
  }

}
