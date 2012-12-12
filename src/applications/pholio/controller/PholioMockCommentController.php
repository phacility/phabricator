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

    $mock = id(new PholioMockQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();

    if (!$mock) {
      return new Aphront404Response();
    }

    $mock_uri = '/M'.$mock->getID();

    $comment = $request->getStr('comment');

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    $xactions = array();
    $xactions[] = id(new PholioTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PholioTransactionComment())
          ->setContent($comment));

    $editor = id(new PholioMockEditor())
      ->setActor($user)
      ->setContentSource($content_source)
      ->setContinueOnException($request->isContinueRequest());

    try {
      $xactions = $editor->applyTransactions($mock, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($mock_uri)
        ->setException($ex);
    }

    if ($request->isAjax()) {
      return id(new PhabricatorApplicationTransactionResponse())
        ->setViewer($user)
        ->setTransactions($xactions)
        ->setAnchorOffset($request->getStr('anchor'));
    } else {
      return id(new AphrontRedirectResponse())->setURI($mock_uri);
    }
  }

}
