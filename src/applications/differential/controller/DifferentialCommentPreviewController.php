<?php

final class DifferentialCommentPreviewController
  extends DifferentialController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();

    $author_phid = $request->getUser()->getPHID();

    $action = $request->getStr('action');


    $comment = new DifferentialComment();
    $comment->setContent($request->getStr('content'));
    $comment->setAction($action);
    $comment->setAuthorPHID($author_phid);

    $handles = array($author_phid);

    $reviewers = $request->getStrList('reviewers');
    if (DifferentialAction::allowReviewers($action) && $reviewers) {
      $comment->setMetadata(array(
        DifferentialComment::METADATA_ADDED_REVIEWERS => $reviewers));
      $handles = array_merge($handles, $reviewers);
    }

    $ccs = $request->getStrList('ccs');
    if ($action == DifferentialAction::ACTION_ADDCCS && $ccs) {
      $comment->setMetadata(array(
        DifferentialComment::METADATA_ADDED_CCS => $ccs));
      $handles = array_merge($handles, $ccs);
    }

    $handles = $this->loadViewerHandles($handles);

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($request->getUser());
    $engine->addObject($comment, DifferentialComment::MARKUP_FIELD_BODY);
    $engine->process();

    $view = new DifferentialRevisionCommentView();
    $view->setUser($request->getUser());
    $view->setComment($comment);
    $view->setHandles($handles);
    $view->setMarkupEngine($engine);
    $view->setPreview(true);
    $view->setTargetDiff(null);

    $metadata = array(
      'reviewers' => $reviewers,
      'ccs' => $ccs,
    );
    if ($action != DifferentialAction::ACTION_COMMENT) {
      $metadata['action'] = $action;
    }

    id(new PhabricatorDraft())
      ->setAuthorPHID($author_phid)
      ->setDraftKey('differential-comment-'.$this->id)
      ->setDraft($comment->getContent())
      ->setMetadata($metadata)
      ->replaceOrDelete();

    return id(new AphrontAjaxResponse())
      ->setContent($view->render());
  }

}
