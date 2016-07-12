<?php

final class PhluxViewController extends PhluxController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $key = $request->getURIData('key');

    $var = id(new PhluxVariableQuery())
      ->setViewer($viewer)
      ->withKeys(array($key))
      ->executeOne();

    if (!$var) {
      return new Aphront404Response();
    }

    $title = $var->getVariableKey();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title, $request->getRequestURI());
    $crumbs->setBorder(true);

    $curtain = $this->buildCurtainView($var);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($var)
      ->setHeaderIcon('fa-copy');

    $display_value = json_encode($var->getVariableValue());

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->addProperty(pht('Value'), $display_value);

    $timeline = $this->buildTransactionTimeline(
      $var,
      new PhluxTransactionQuery());
    $timeline->setShouldTerminate(true);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($properties);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $object_box,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildCurtainView(PhluxVariable $var) {
    $viewer = $this->getViewer();

    $curtain = $this->newCurtainView($var);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $var,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Variable'))
        ->setHref($this->getApplicationURI('/edit/'.$var->getVariableKey().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $curtain;
  }

}
