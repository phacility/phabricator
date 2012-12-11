<?php

final class PhabricatorMacroCommentController
  extends PhabricatorMacroController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $macro = id(new PhabricatorFileImageMacro())->load($this->id);
    if (!$macro) {
      return new Aphront404Response();
    }

    $view_uri = $this->getApplicationURI('/view/'.$macro->getID().'/');

    $xactions = array();

    $xactions[] = id(new PhabricatorMacroTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PhabricatorMacroTransactionComment())
          ->setContent($request->getStr('comment')));

    $editor = id(new PhabricatorMacroEditor())
      ->setActor($user)
      ->setContentSource(
        PhabricatorContentSource::newForSource(
          PhabricatorContentSource::SOURCE_WEB,
          array(
            'ip' => $request->getRemoteAddr(),
          )))
      ->applyTransactions($macro, $xactions);

    return id(new AphrontRedirectResponse())
      ->setURI($view_uri);
  }

}
