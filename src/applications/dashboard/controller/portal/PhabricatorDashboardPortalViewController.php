<?php

final class PhabricatorDashboardPortalViewController
  extends PhabricatorDashboardPortalController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $portal = id(new PhabricatorDashboardPortalQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$portal) {
      return new Aphront404Response();
    }

    $content = $portal->getObjectName();

    return $this->newPage()
      ->setTitle(
        array(
          pht('Portal'),
          $portal->getName(),
        ))
      ->setPageObjectPHIDs(array($portal->getPHID()))
      ->appendChild($content);
  }

}
