<?php

final class LegalpadDocumentCommentController extends LegalpadController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $document = id(new LegalpadDocumentQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needDocumentBodies(true)
      ->executeOne();

    if (!$document) {
      return new Aphront404Response();
    }

    $is_preview = $request->isPreviewRequest();

    $draft = PhabricatorDraft::buildFromRequest($request);

    $document_uri = $this->getApplicationURI('view/'.$document->getID());

    $comment = $request->getStr('comment');

    $xactions = array();

    if (strlen($comment)) {
      $xactions[] = id(new LegalpadTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new LegalpadTransactionComment())
            ->setDocumentID($document->getID())
            ->setLineNumber(0)
            ->setLineLength(0)
            ->setContent($comment));
    }

    $editor = id(new LegalpadDocumentEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($document, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($document_uri)
        ->setException($ex);
    }

    if ($draft) {
      $draft->replaceOrDelete();
    }

    if ($request->isAjax() && $is_preview) {
      return id(new PhabricatorApplicationTransactionResponse())
        ->setViewer($viewer)
        ->setTransactions($xactions)
        ->setIsPreview($is_preview);
    } else {
      return id(new AphrontRedirectResponse())->setURI($document_uri);
    }
  }

}
