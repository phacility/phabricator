<?php

final class AlmanacNamespaceViewController
  extends AlmanacNamespaceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');
    $namespace = id(new AlmanacNamespaceQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$namespace) {
      return new Aphront404Response();
    }

    $title = pht('Namespace %s', $namespace->getName());

    $property_list = $this->buildPropertyList($namespace);
    $action_list = $this->buildActionList($namespace);
    $property_list->setActionList($action_list);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($namespace->getName())
      ->setPolicyObject($namespace);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($property_list);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($namespace->getName());

    $timeline = $this->buildTransactionTimeline(
      $namespace,
      new AlmanacNamespaceTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $box,
          $timeline,
      ));
  }

  private function buildPropertyList(AlmanacNamespace $namespace) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    return $properties;
  }

  private function buildActionList(AlmanacNamespace $namespace) {
    $viewer = $this->getViewer();
    $id = $namespace->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $namespace,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Namespace'))
        ->setHref($this->getApplicationURI("namespace/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $actions;
  }

}
