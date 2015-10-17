<?php

final class DrydockRepositoryOperationViewController
  extends DrydockController {

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
    $title = pht('Repository Operation %d', $id);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($operation);

    $state = $operation->getOperationState();
    $icon = DrydockRepositoryOperation::getOperationStateIcon($state);
    $name = DrydockRepositoryOperation::getOperationStateName($state);
    $header->setStatus($icon, null, $name);

    $actions = $this->buildActionListView($operation);
    $properties = $this->buildPropertyListView($operation);
    $properties->setActionList($actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Operations'),
      $this->getApplicationURI('operation/'));
    $crumbs->addTextCrumb($title);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
      ));

  }

  private function buildActionListView(DrydockRepositoryOperation $operation) {
    $viewer = $this->getViewer();
    $id = $operation->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($operation);

    return $view;
  }

  private function buildPropertyListView(
    DrydockRepositoryOperation $operation) {

    $viewer = $this->getViewer();

    $view = new PHUIPropertyListView();
    $view->addProperty(
      pht('Repository'),
      $viewer->renderHandle($operation->getRepositoryPHID()));

    $view->addProperty(
      pht('Object'),
      $viewer->renderHandle($operation->getObjectPHID()));

    return $view;
  }

}
