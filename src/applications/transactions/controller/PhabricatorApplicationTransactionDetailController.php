<?php

final class PhabricatorApplicationTransactionDetailController
  extends PhabricatorApplicationTransactionController {

  private $phid;

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

    // Take an educated guess at the URI where the transactions appear so we
    // can send the cancel button somewhere sensible. This won't always get the
    // best answer (for example, Diffusion's history is visible on a page other
    // than the main object view page) but should always get a reasonable one.

    $cancel_uri = '/';
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($xaction->getObjectPHID()))
      ->executeOne();
    if ($handle) {
      $cancel_uri = $handle->getURI();
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Change Details'))
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->appendChild($details)
      ->addCancelButton($cancel_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
