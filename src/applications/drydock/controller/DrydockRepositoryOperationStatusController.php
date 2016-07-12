<?php

final class DrydockRepositoryOperationStatusController
  extends DrydockRepositoryOperationController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $operation = id(new DrydockRepositoryOperationQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$operation) {
      return new Aphront404Response();
    }

    $id = $operation->getID();

    $status_view = id(new DrydockRepositoryOperationStatusView())
      ->setUser($viewer)
      ->setOperation($operation);

    if ($request->isAjax()) {
      $payload = array(
        'markup' => $status_view->renderUnderwayState(),
        'isUnderway' => $operation->isUnderway(),
      );

      return id(new AphrontAjaxResponse())
        ->setContent($payload);
    }

    $title = pht('Repository Operation %d', $id);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Operations'),
      $this->getApplicationURI('operation/'));
    $crumbs->addTextCrumb($title);

    return $this->newPage()
      ->setTitle(pht('Status'))
      ->setCrumbs($crumbs)
      ->appendChild($status_view);
  }

}
