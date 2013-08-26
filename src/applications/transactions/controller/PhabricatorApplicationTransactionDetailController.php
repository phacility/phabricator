<?php

final class PhabricatorApplicationTransactionDetailController
  extends PhabricatorApplicationTransactionController {

  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $xaction = id(new PhabricatorObjectQuery())
      ->withPHIDs(array($this->phid))
      ->setViewer($user)
      ->executeOne();

    if (!$xaction) {
      // future proofing for the day visibility of transactions can change
      return new Aphront404Response();
    }

    return id(new PhabricatorApplicationTransactionResponse())
      ->setViewer($user)
      ->setTransactions(array($xaction))
      ->setIsDetailView(true)
      ->setAnchorOffset($request->getStr('anchor'));
  }

}
