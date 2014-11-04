<?php

final class PhabricatorApplicationTransactionDetailController
  extends PhabricatorApplicationTransactionController {

  private $phid;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $xaction = id(new PhabricatorObjectQuery())
      ->withPHIDs(array($this->phid))
      ->setViewer($viewer)
      ->executeOne();
    if (!$xaction) {
      return new Aphront404Response();
    }

    $details = $xaction->renderChangeDetails($viewer);

    $cancel_uri = $this->guessCancelURI($viewer, $xaction);
    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Change Details'))
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->setFlush(true)
      ->appendChild($details)
      ->addCancelButton($cancel_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
