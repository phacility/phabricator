<?php

final class NuanceSourceViewController extends NuanceController {

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

    $timeline = $this->buildTransactionTimeline(
      $source,
      new NuanceSourceTransactionQuery());
    $timeline->setShouldTerminate(true);

    $header = $this->buildHeaderView($source);
    $actions = $this->buildActionView($source);
    $properties = $this->buildPropertyView($source, $actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $title = $source->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Sources'), $this->getApplicationURI('source/'));

    $crumbs->addTextCrumb($title);


    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $source,
      PhabricatorPolicyCapability::CAN_EDIT);

    $routing_list = id(new PHUIPropertyListView())
      ->addProperty(
        pht('Default Queue'),
        $viewer->renderHandle($source->getDefaultQueuePHID()));

    $routing_header = id(new PHUIHeaderView())
      ->setHeader(pht('Routing Rules'));

    $routing = id(new PHUIObjectBoxView())
      ->setHeader($routing_header)
      ->addPropertyList($routing_list);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $routing,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildHeaderView(NuanceSource $source) {
    $viewer = $this->getViewer();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($source->getName())
      ->setPolicyObject($source);

    return $header;
  }

  private function buildActionView(NuanceSource $source) {
    $viewer = $this->getViewer();
    $id = $source->getID();

    $actions = id(new PhabricatorActionListView())
      ->setObjectURI($source->getURI())
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $source,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Source'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("source/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $request = $this->getRequest();
    $definition = $source->requireDefinition();
    $source_actions = $definition->getSourceViewActions($request);
    foreach ($source_actions as $source_action) {
      $actions->addAction($source_action);
    }

    return $actions;
  }

  private function buildPropertyView(
    NuanceSource $source,
    PhabricatorActionListView $actions) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($source)
      ->setActionList($actions);

    $definition = $source->requireDefinition();
    $properties->addProperty(
      pht('Source Type'),
      $definition->getName());

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $source);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    return $properties;
  }
}
