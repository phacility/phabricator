<?php

final class PhabricatorApplicationTransactionDetailController
  extends PhabricatorApplicationTransactionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $phid = $request->getURIData('phid');

    $xaction = id(new PhabricatorObjectQuery())
      ->withPHIDs(array($phid))
      ->setViewer($viewer)
      ->executeOne();
    if (!$xaction) {
      return new Aphront404Response();
    }

    $details = $xaction->renderChangeDetails($viewer);
    $cancel_uri = $this->guessCancelURI($viewer, $xaction);

    return $this->newDialog()
      ->setTitle(pht('Change Details'))
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->setFlush(true)
      ->appendChild($details)
      ->addCancelButton($cancel_uri);
  }

}
