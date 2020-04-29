<?php

abstract class PhabricatorInlineCommentController
  extends PhabricatorController {

  abstract protected function createComment();
  abstract protected function loadComment($id);
  abstract protected function loadCommentForEdit($id);
  abstract protected function loadCommentForDone($id);
  abstract protected function loadCommentByPHID($phid);
  abstract protected function loadObjectOwnerPHID(
    PhabricatorInlineComment $inline);
  abstract protected function deleteComment(
    PhabricatorInlineComment $inline);
  abstract protected function undeleteComment(
    PhabricatorInlineComment $inline);
  abstract protected function saveComment(
    PhabricatorInlineComment $inline);

  protected function hideComments(array $ids) {
    throw new PhutilMethodNotImplementedException();
  }

  protected function showComments(array $ids) {
    throw new PhutilMethodNotImplementedException();
  }

  private $changesetID;
  private $isNewFile;
  private $isOnRight;
  private $lineNumber;
  private $lineLength;
  private $commentText;
  private $operation;
  private $commentID;
  private $renderer;
  private $replyToCommentPHID;

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

  public function setRenderer($renderer) {
    $this->renderer = $renderer;
    return $this;
  }

  public function getRenderer() {
    return $this->renderer;
  }

  public function setReplyToCommentPHID($phid) {
    $this->replyToCommentPHID = $phid;
    return $this;
  }

  public function getReplyToCommentPHID() {
    return $this->replyToCommentPHID;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $this->readRequestParameters();

    $op = $this->getOperation();
    switch ($op) {
      case 'hide':
      case 'show':
        if (!$request->validateCSRF()) {
          return new Aphront404Response();
        }

        $ids = $request->getStrList('ids');
        if ($ids) {
          if ($op == 'hide') {
            $this->hideComments($ids);
          } else {
            $this->showComments($ids);
          }
        }

        return id(new AphrontAjaxResponse())->setContent(array());
      case 'done':
        if (!$request->validateCSRF()) {
          return new Aphront404Response();
        }
        $inline = $this->loadCommentForDone($this->getCommentID());

        $is_draft_state = false;
        $is_checked = false;
        switch ($inline->getFixedState()) {
          case PhabricatorInlineComment::STATE_DRAFT:
            $next_state = PhabricatorInlineComment::STATE_UNDONE;
            break;
          case PhabricatorInlineComment::STATE_UNDRAFT:
            $next_state = PhabricatorInlineComment::STATE_DONE;
            $is_checked = true;
            break;
          case PhabricatorInlineComment::STATE_DONE:
            $next_state = PhabricatorInlineComment::STATE_UNDRAFT;
            $is_draft_state = true;
            break;
          default:
          case PhabricatorInlineComment::STATE_UNDONE:
            $next_state = PhabricatorInlineComment::STATE_DRAFT;
            $is_draft_state = true;
            $is_checked = true;
            break;
        }

        $inline->setFixedState($next_state)->save();

        return id(new AphrontAjaxResponse())
          ->setContent(
            array(
              'isChecked' => $is_checked,
              'draftState' => $is_draft_state,
            ));
      case 'delete':
      case 'undelete':
      case 'refdelete':
        if (!$request->validateCSRF()) {
          return new Aphront404Response();
        }

        // NOTE: For normal deletes, we just process the delete immediately
        // and show an "Undo" action. For deletes by reference from the
        // preview ("refdelete"), we prompt first (because the "Undo" may
        // not draw, or may not be easy to locate).

        if ($op == 'refdelete') {
          if (!$request->isFormPost()) {
            return $this->newDialog()
              ->setTitle(pht('Really delete comment?'))
              ->addHiddenInput('id', $this->getCommentID())
              ->addHiddenInput('op', $op)
              ->appendParagraph(pht('Delete this inline comment?'))
              ->addCancelButton('#')
              ->addSubmitButton(pht('Delete'));
          }
        }

        $is_delete = ($op == 'delete' || $op == 'refdelete');

        $inline = $this->loadCommentForEdit($this->getCommentID());

        if ($is_delete) {
          $this->deleteComment($inline);
        } else {
          $this->undeleteComment($inline);
        }

        return $this->buildEmptyResponse();
      case 'edit':
        $inline = $this->loadCommentForEdit($this->getCommentID());
        $text = $this->getCommentText();

        if ($request->isFormPost()) {
          if (strlen($text)) {
            $inline
              ->setContent($text)
              ->setIsEditing(false);

            $this->saveComment($inline);
            return $this->buildRenderedCommentResponse(
              $inline,
              $this->getIsOnRight());
          } else {
            $this->deleteComment($inline);
            return $this->buildEmptyResponse();
          }
        } else {
          $inline->setIsEditing(true);

          if (strlen($text)) {
            $inline->setContent($text);
          }

          $this->saveComment($inline);
        }

        $edit_dialog = $this->buildEditDialog($inline)
          ->setTitle(pht('Edit Inline Comment'));

        $view = $this->buildScaffoldForView($edit_dialog);

        return $this->newInlineResponse($inline, $view);
      case 'cancel':
        $inline = $this->loadCommentForEdit($this->getCommentID());

        $inline->setIsEditing(false);

        $content = $inline->getContent();
        if (!strlen($content)) {
          $this->deleteComment($inline);
        } else {
          $this->saveComment($inline);
        }

        return $this->buildEmptyResponse();
      case 'new':
      case 'reply':
      default:
        // NOTE: We read the values from the client (the display values), not
        // the values from the database (the original values) when replying.
        // In particular, when replying to a ghost comment which was moved
        // across diffs and then moved backward to the most recent visible
        // line, we want to reply on the display line (which exists), not on
        // the comment's original line (which may not exist in this changeset).
        $is_new = $this->getIsNewFile();
        $number = $this->getLineNumber();
        $length = $this->getLineLength();

        $inline = $this->createComment()
          ->setChangesetID($this->getChangesetID())
          ->setAuthorPHID($viewer->getPHID())
          ->setIsNewFile($is_new)
          ->setLineNumber($number)
          ->setLineLength($length)
          ->setContent($this->getCommentText())
          ->setReplyToCommentPHID($this->getReplyToCommentPHID())
          ->setIsEditing(true);

        // If you own this object, mark your own inlines as "Done" by default.
        $owner_phid = $this->loadObjectOwnerPHID($inline);
        if ($owner_phid) {
          if ($viewer->getPHID() == $owner_phid) {
            $fixed_state = PhabricatorInlineComment::STATE_DRAFT;
            $inline->setFixedState($fixed_state);
          }
        }

        $this->saveComment($inline);

        $edit_dialog = $this->buildEditDialog($inline);

        if ($this->getOperation() == 'reply') {
          $edit_dialog->setTitle(pht('Reply to Inline Comment'));
        } else {
          $edit_dialog->setTitle(pht('New Inline Comment'));
        }

        $view = $this->buildScaffoldForView($edit_dialog);

        return $this->newInlineResponse($inline, $view);
    }
  }

  private function readRequestParameters() {
    $request = $this->getRequest();

    // NOTE: This isn't necessarily a DifferentialChangeset ID, just an
    // application identifier for the changeset. In Diffusion, it's a Path ID.
    $this->changesetID = $request->getInt('changesetID');

    $this->isNewFile = (int)$request->getBool('is_new');
    $this->isOnRight = $request->getBool('on_right');
    $this->lineNumber = $request->getInt('number');
    $this->lineLength = $request->getInt('length');
    $this->commentText = $request->getStr('text');
    $this->commentID = $request->getInt('id');
    $this->operation = $request->getStr('op');
    $this->renderer = $request->getStr('renderer');
    $this->replyToCommentPHID = $request->getStr('replyToCommentPHID');

    if ($this->getReplyToCommentPHID()) {
      $reply_phid = $this->getReplyToCommentPHID();
      $reply_comment = $this->loadCommentByPHID($reply_phid);
      if (!$reply_comment) {
        throw new Exception(
          pht('Failed to load comment "%s".', $reply_phid));
      }

      // When replying, force the new comment into the same location as the
      // old comment. If we don't do this, replying to a ghost comment from
      // diff A while viewing diff B can end up placing the two comments in
      // different places while viewing diff C, because the porting algorithm
      // makes a different decision. Forcing the comments to bind to the same
      // place makes sure they stick together no matter which diff is being
      // viewed. See T10562 for discussion.

      $this->changesetID = $reply_comment->getChangesetID();
      $this->isNewFile = $reply_comment->getIsNewFile();
      $this->lineNumber = $reply_comment->getLineNumber();
      $this->lineLength = $reply_comment->getLineLength();
    }
  }

  private function buildEditDialog(PhabricatorInlineComment $inline) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $edit_dialog = id(new PHUIDiffInlineCommentEditView())
      ->setViewer($viewer)
      ->setInlineComment($inline)
      ->setIsOnRight($this->getIsOnRight())
      ->setRenderer($this->getRenderer());

    return $edit_dialog;
  }

  private function buildEmptyResponse() {
    return id(new AphrontAjaxResponse())
      ->setContent(
        array(
          'inline' => array(),
          'view' => null,
        ));
  }

  private function buildRenderedCommentResponse(
    PhabricatorInlineComment $inline,
    $on_right) {

    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($viewer);
    $engine->addObject(
      $inline,
      PhabricatorInlineComment::MARKUP_FIELD_BODY);
    $engine->process();

    $phids = array($viewer->getPHID());

    $handles = $this->loadViewerHandles($phids);
    $object_owner_phid = $this->loadObjectOwnerPHID($inline);

    $view = id(new PHUIDiffInlineCommentDetailView())
      ->setUser($viewer)
      ->setInlineComment($inline)
      ->setIsOnRight($on_right)
      ->setMarkupEngine($engine)
      ->setHandles($handles)
      ->setEditable(true)
      ->setCanMarkDone(false)
      ->setObjectOwnerPHID($object_owner_phid);

    $view = $this->buildScaffoldForView($view);

    return $this->newInlineResponse($inline, $view);
  }

  private function buildScaffoldForView(PHUIDiffInlineCommentView $view) {
    $renderer = DifferentialChangesetHTMLRenderer::getHTMLRendererByKey(
      $this->getRenderer());

    $view = $renderer->getRowScaffoldForInline($view);

    return id(new PHUIDiffInlineCommentTableScaffold())
      ->addRowScaffold($view);
  }

  private function newInlineResponse(
    PhabricatorInlineComment $inline,
    $view) {

    $response = array(
      'inline' => array(
        'id' => $inline->getID(),
      ),
      'view' => hsprintf('%s', $view),
    );

    return id(new AphrontAjaxResponse())
      ->setContent($response);
  }

}
