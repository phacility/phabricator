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

    $curtain = $this->buildCurtain($namespace);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($namespace->getName())
      ->setPolicyObject($namespace)
      ->setHeaderIcon('fa-asterisk');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($namespace->getName());
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $namespace,
      new AlmanacNamespaceTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
        ));
  }

  private function buildCurtain(AlmanacNamespace $namespace) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $namespace,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $namespace->getID();
    $edit_uri = $this->getApplicationURI("namespace/edit/{$id}/");

    $curtain = $this->newCurtainView($namespace);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Namespace'))
        ->setHref($edit_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $curtain;
  }

}
