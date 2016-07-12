<?php

final class NuanceSourceViewController
  extends NuanceSourceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $source = id(new NuanceSourceQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$source) {
      return new Aphront404Response();
    }

    $source_id = $source->getID();

    $header = $this->buildHeaderView($source);
    $curtain = $this->buildCurtain($source);
    $properties = $this->buildPropertyView($source);

    $title = $source->getName();

    $routing_list = id(new PHUIPropertyListView())
      ->addProperty(
        pht('Default Queue'),
        $viewer->renderHandle($source->getDefaultQueuePHID()));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Sources'), $this->getApplicationURI('source/'));
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $source,
      new NuanceSourceTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $properties)
      ->addPropertySection(pht('Routing'), $routing_list)
      ->setMainColumn($timeline);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeaderView(NuanceSource $source) {
    $viewer = $this->getViewer();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($source->getName())
      ->setPolicyObject($source);

    return $header;
  }

  private function buildCurtain(NuanceSource $source) {
    $viewer = $this->getViewer();
    $id = $source->getID();

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $source,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($source);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Source'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("source/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $request = $this->getRequest();
    $definition = $source->getDefinition();

    $definition
      ->setViewer($viewer)
      ->setSource($source);

    $source_actions = $definition->getSourceViewActions($request);
    foreach ($source_actions as $source_action) {
      $curtain->addAction($source_action);
    }

    return $curtain;
  }

  private function buildPropertyView(
    NuanceSource $source) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $definition = $source->getDefinition();

    $properties->addProperty(
      pht('Source Type'),
      $definition->getName());

    return $properties;
  }
}
