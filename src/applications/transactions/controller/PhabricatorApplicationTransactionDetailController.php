<?php

final class PhabricatorApplicationTransactionDetailController
  extends PhabricatorApplicationTransactionController {

  private $objectHandle;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    // Users can end up on this page directly by following links in email,
    // so we try to make it somewhat reasonable as a standalone page.

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

    $object_phid = $xaction->getObjectPHID();
    $handles = $viewer->loadHandles(array($object_phid));
    $handle = $handles[$object_phid];
    $this->objectHandle = $handle;

    $cancel_uri = $handle->getURI();

    if ($request->isAjax()) {
      $button_text = pht('Done');
    } else {
      $button_text = pht('Continue');
    }

    return $this->newDialog()
      ->setTitle(pht('Change Details'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($details)
      ->addCancelButton($cancel_uri, $button_text);
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $handle = $this->objectHandle;
    if ($handle) {
      $crumbs->addTextCrumb(
        $handle->getObjectName(),
        $handle->getURI());
    }

    return $crumbs;
  }


}
