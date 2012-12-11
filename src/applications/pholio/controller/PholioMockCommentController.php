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
    if (!strlen($comment)) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Empty Comment'))
        ->appendChild('You did not provide a comment!')
        ->addCancelButton($mock_uri);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    $xaction = id(new PholioTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PholioTransactionComment())
          ->setContent($comment));

    id(new PholioMockEditor())
      ->setActor($user)
      ->setContentSource($content_source)
      ->applyTransactions($mock, array($xaction));

    return id(new AphrontRedirectResponse())->setURI($mock_uri);
  }

}
