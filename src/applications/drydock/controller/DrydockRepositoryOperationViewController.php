<?php

final class DrydockRepositoryOperationViewController
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

    $status_view = id(new DrydockRepositoryOperationStatusView())
      ->setUser($viewer)
      ->setOperation($operation);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $object_box,
          $status_view,
        ));
  }

  private function buildActionListView(DrydockRepositoryOperation $operation) {
    $viewer = $this->getViewer();
    $id = $operation->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
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

    $lease_phid = $operation->getWorkingCopyLeasePHID();
    if ($lease_phid) {
      $lease_display = $viewer->renderHandle($lease_phid);
    } else {
      $lease_display = phutil_tag('em', array(), pht('None'));
    }

    $view->addProperty(pht('Working Copy'), $lease_display);

    return $view;
  }

}
