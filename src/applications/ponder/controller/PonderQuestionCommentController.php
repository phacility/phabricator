<?php

final class PonderQuestionCommentController extends PonderController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $question = id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$question) {
      return new Aphront404Response();
    }

    $is_preview = $request->isPreviewRequest();
//    $draft = PhabricatorDraft::buildFromRequest($request);

    $qid = $question->getID();
    $view_uri = "/Q{$qid}";

    $xactions = array();
    $xactions[] = id(new PonderQuestionTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PonderQuestionTransactionComment())
          ->setContent($request->getStr('comment')));

    $editor = id(new PonderQuestionEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setContentSourceFromRequest($request)
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($question, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($view_uri)
        ->setException($ex);
    }

//    if ($draft) {
//      $draft->replaceOrDelete();
//    }

    if ($request->isAjax() && $is_preview) {
      return id(new PhabricatorApplicationTransactionResponse())
        ->setViewer($viewer)
        ->setTransactions($xactions)
        ->setIsPreview($is_preview);
    } else {
      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }
  }

}
