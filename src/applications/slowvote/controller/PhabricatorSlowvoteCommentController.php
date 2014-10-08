<?php

final class PhabricatorSlowvoteCommentController
  extends PhabricatorSlowvoteController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $poll = id(new PhabricatorSlowvoteQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$poll) {
      return new Aphront404Response();
    }

    $is_preview = $request->isPreviewRequest();
    $draft = PhabricatorDraft::buildFromRequest($request);

    $view_uri = '/V'.$poll->getID();

    $xactions = array();
    $xactions[] = id(new PhabricatorSlowvoteTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PhabricatorSlowvoteTransactionComment())
          ->setContent($request->getStr('comment')));

    $editor = id(new PhabricatorSlowvoteEditor())
      ->setActor($user)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setContentSourceFromRequest($request)
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($poll, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($view_uri)
        ->setException($ex);
    }

    if ($draft) {
      $draft->replaceOrDelete();
    }

    if ($request->isAjax() && $is_preview) {
      return id(new PhabricatorApplicationTransactionResponse())
        ->setViewer($user)
        ->setTransactions($xactions)
        ->setIsPreview($is_preview);
    } else {
      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }
  }

}
