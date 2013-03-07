<?php

/**
 * @group pholio
 */
final class PholioMockCommentController extends PholioController {

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

    $mock = id(new PholioMockQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needImages(true)
      ->executeOne();

    if (!$mock) {
      return new Aphront404Response();
    }

    $is_preview = $request->isPreviewRequest();

    $draft = PhabricatorDraft::buildFromRequest($request);

    $mock_uri = '/M'.$mock->getID();

    $comment = $request->getStr('comment');

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    $xactions = array();

    $inline_comments = id(new PholioTransactionComment())->loadAllWhere(
      'authorphid = %s AND transactionphid IS NULL AND imageid IN (%Ld)',
      $user->getPHID(),
      mpull($mock->getImages(), 'getID'));

    if (!$inline_comments || strlen($comment)) {
      $xactions[] = id(new PholioTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new PholioTransactionComment())
            ->setContent($comment));
    }

    foreach ($inline_comments as $inline_comment) {
      $xactions[] = id(new PholioTransaction())
        ->setTransactionType(PholioTransactionType::TYPE_INLINE)
        ->attachComment($inline_comment);
    }

    $editor = id(new PholioMockEditor())
      ->setActor($user)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($mock, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($mock_uri)
        ->setException($ex);
    }

    if ($draft) {
      $draft->replaceOrDelete();
    }

    if ($request->isAjax()) {
      return id(new PhabricatorApplicationTransactionResponse())
        ->setViewer($user)
        ->setTransactions($xactions)
        ->setIsPreview($is_preview)
        ->setAnchorOffset($request->getStr('anchor'));
    } else {
      return id(new AphrontRedirectResponse())->setURI($mock_uri);
    }
  }

}
