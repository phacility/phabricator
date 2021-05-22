<?php

abstract class PhabricatorInlineCommentController
  extends PhabricatorController {

  private $containerObject;

  abstract protected function createComment();
  abstract protected function newInlineCommentQuery();
  abstract protected function loadCommentForDone($id);
  abstract protected function loadObjectOwnerPHID(
    PhabricatorInlineComment $inline);
  abstract protected function newContainerObject();

  final protected function getContainerObject() {
    if ($this->containerObject === null) {
      $object = $this->newContainerObject();
      if (!$object) {
        throw new Exception(
          pht(
            'Failed to load container object for inline comment.'));
      }
      $this->containerObject = $object;
    }

    return $this->containerObject;
  }

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

    if (!$request->validateCSRF()) {
      return new Aphront404Response();
    }

    $this->readRequestParameters();

    $op = $this->getOperation();
    switch ($op) {
      case 'hide':
      case 'show':
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

        $inline = $this->loadCommentByIDForEdit($this->getCommentID());

        if ($is_delete) {
          $inline
            ->setIsEditing(false)
            ->setIsDeleted(1);
        } else {
          $inline->setIsDeleted(0);
        }

        $this->saveComment($inline);

        return $this->buildEmptyResponse();
      case 'save':
        $inline = $this->loadCommentByIDForEdit($this->getCommentID());

        $this->updateCommentContentState($inline);

        $inline
          ->setIsEditing(false)
          ->setIsDeleted(0);

        // Since we're saving the comment, update the committed state.
        $active_state = $inline->getContentState();
        $inline->setCommittedContentState($active_state);

        $this->saveComment($inline);

        return $this->buildRenderedCommentResponse(
          $inline,
          $this->getIsOnRight());
      case 'edit':
        $inline = $this->loadCommentByIDForEdit($this->getCommentID());

        // NOTE: At time of writing, the "editing" state of inlines is
        // preserved by simulating a click on "Edit" when the inline loads.

        // In this case, we don't want to "saveComment()", because it
        // recalculates object drafts and purges versioned drafts.

        // The recalculation is merely unnecessary (state doesn't change)
        // but purging drafts means that loading a page and then closing it
        // discards your drafts.

        // To avoid the purge, only invoke "saveComment()" if we actually
        // have changes to apply.

        $is_dirty = false;
        if (!$inline->getIsEditing()) {
          $inline
            ->setIsDeleted(0)
            ->setIsEditing(true);

          $is_dirty = true;
        }

        if ($this->hasContentState()) {
          $this->updateCommentContentState($inline);
          $is_dirty = true;
        } else {
          PhabricatorInlineComment::loadAndAttachVersionedDrafts(
            $viewer,
            array($inline));
        }

        if ($is_dirty) {
          $this->saveComment($inline);
        }

        $edit_dialog = $this->buildEditDialog($inline)
          ->setTitle(pht('Edit Inline Comment'));

        $view = $this->buildScaffoldForView($edit_dialog);

        return $this->newInlineResponse($inline, $view, true);
      case 'cancel':
        $inline = $this->loadCommentByIDForEdit($this->getCommentID());

        $inline->setIsEditing(false);

        // If the user uses "Undo" to get into an edited state ("AB"), then
        // clicks cancel to return to the previous state ("A"), we also want
        // to set the stored state back to "A".
        $this->updateCommentContentState($inline);

        $this->saveComment($inline);

        return $this->buildEmptyResponse();
      case 'draft':
        $inline = $this->loadCommentByIDForEdit($this->getCommentID());

        $versioned_draft = PhabricatorVersionedDraft::loadOrCreateDraft(
          $inline->getPHID(),
          $viewer->getPHID(),
          $inline->getID());

        $map = $this->newRequestContentState($inline)->newStorageMap();
        $versioned_draft->setProperty('inline.state', $map);
        $versioned_draft->save();

        // We have to synchronize the draft engine after saving a versioned
        // draft, because taking an inline comment from "no text, no draft"
        // to "no text, text in a draft" marks the container object as having
        // a draft.
        $draft_engine = $this->newDraftEngine();
        if ($draft_engine) {
          $draft_engine->synchronize();
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
          ->setReplyToCommentPHID($this->getReplyToCommentPHID())
          ->setIsEditing(true)
          ->setStartOffset($request->getInt('startOffset'))
          ->setEndOffset($request->getInt('endOffset'))
          ->setContent('');

        $document_engine_key = $request->getStr('documentEngineKey');
        if ($document_engine_key !== null) {
          $inline->setDocumentEngineKey($document_engine_key);
        }

        // If you own this object, mark your own inlines as "Done" by default.
        $owner_phid = $this->loadObjectOwnerPHID($inline);
        if ($owner_phid) {
          if ($viewer->getPHID() == $owner_phid) {
            $fixed_state = PhabricatorInlineComment::STATE_DRAFT;
            $inline->setFixedState($fixed_state);
          }
        }

        if ($this->hasContentState()) {
          $this->updateCommentContentState($inline);
        }

        // NOTE: We're writing the comment as "deleted", then reloading to
        // pick up context and undeleting it. This is silly -- we just want
        // to load and attach context -- but just loading context is currently
        // complicated (for example, context relies on cache keys that expect
        // the inline to have an ID).

        $inline->setIsDeleted(1);

        $this->saveComment($inline);

        // Reload the inline to attach context.
        $inline = $this->loadCommentByIDForEdit($inline->getID());

        // Now, we can read the source file and set the initial state.
        $state = $inline->getContentState();
        $default_suggestion = $inline->getDefaultSuggestionText();
        $state->setContentSuggestionText($default_suggestion);

        $inline->setInitialContentState($state);
        $inline->setContentState($state);

        $inline->setIsDeleted(0);

        $this->saveComment($inline);

        $edit_dialog = $this->buildEditDialog($inline);

        if ($this->getOperation() == 'reply') {
          $edit_dialog->setTitle(pht('Reply to Inline Comment'));
        } else {
          $edit_dialog->setTitle(pht('New Inline Comment'));
        }

        $view = $this->buildScaffoldForView($edit_dialog);

        return $this->newInlineResponse($inline, $view, true);
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

    return $this->newInlineResponse($inline, $view, false);
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
    $view,
    $is_edit) {
    $viewer = $this->getViewer();

    if ($inline->getReplyToCommentPHID()) {
      $can_suggest = false;
    } else {
      $can_suggest = (bool)$inline->getInlineContext();
    }

    if ($is_edit) {
      $state = $inline->getContentStateMapForEdit($viewer);
    } else {
      $state = $inline->getContentStateMap();
    }

    $response = array(
      'inline' => array(
        'id' => $inline->getID(),
        'state' => $state,
        'canSuggestEdit' => $can_suggest,
      ),
      'view' => hsprintf('%s', $view),
    );

    return id(new AphrontAjaxResponse())
      ->setContent($response);
  }

  final protected function loadCommentByID($id) {
    $query = $this->newInlineCommentQuery()
      ->withIDs(array($id));

    return $this->loadCommentByQuery($query);
  }

  final protected function loadCommentByPHID($phid) {
    $query = $this->newInlineCommentQuery()
      ->withPHIDs(array($phid));

    return $this->loadCommentByQuery($query);
  }

  final protected function loadCommentByIDForEdit($id) {
    $viewer = $this->getViewer();

    $query = $this->newInlineCommentQuery()
      ->withIDs(array($id))
      ->needInlineContext(true);

    $inline = $this->loadCommentByQuery($query);

    if (!$inline) {
      throw new Exception(
        pht(
          'Unable to load inline "%s".',
          $id));
    }

    if (!$this->canEditInlineComment($viewer, $inline)) {
      throw new Exception(
        pht(
          'Inline comment "%s" is not editable.',
          $id));
    }

    return $inline;
  }

  private function loadCommentByQuery(
    PhabricatorDiffInlineCommentQuery $query) {
    $viewer = $this->getViewer();

    $inline = $query
      ->setViewer($viewer)
      ->executeOne();

    if ($inline) {
      $inline = $inline->newInlineCommentObject();
    }

    return $inline;
  }

  private function hasContentState() {
    $request = $this->getRequest();
    return (bool)$request->getBool('hasContentState');
  }

  private function newRequestContentState($inline) {
    $request = $this->getRequest();
    return $inline->newContentStateFromRequest($request);
  }

  private function updateCommentContentState(PhabricatorInlineComment $inline) {
    if (!$this->hasContentState()) {
      throw new Exception(
        pht(
          'Attempting to update comment content state, but request has no '.
          'content state.'));
    }

    $state = $this->newRequestContentState($inline);
    $inline->setContentState($state);
  }

  private function saveComment(PhabricatorInlineComment $inline) {
    $viewer = $this->getViewer();
    $draft_engine = $this->newDraftEngine();

    $inline->openTransaction();
      $inline->save();

      PhabricatorVersionedDraft::purgeDrafts(
        $inline->getPHID(),
        $viewer->getPHID());

      if ($draft_engine) {
        $draft_engine->synchronize();
      }

    $inline->saveTransaction();
  }

  private function newDraftEngine() {
    $viewer = $this->getViewer();
    $object = $this->getContainerObject();

    if (!($object instanceof PhabricatorDraftInterface)) {
      return null;
    }

    return $object->newDraftEngine()
      ->setObject($object)
      ->setViewer($viewer);
  }

}
