<?php

final class PhluxViewController extends PhluxController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = $data['key'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $var = id(new PhluxVariableQuery())
      ->setViewer($user)
      ->withKeys(array($this->key))
      ->executeOne();

    if (!$var) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();

    $title = $var->getVariableKey();

    $crumbs->addTextCrumb($title, $request->getRequestURI());

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($user)
      ->setPolicyObject($var);

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObjectURI($request->getRequestURI())
      ->setObject($var);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $var,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Variable'))
        ->setHref($this->getApplicationURI('/edit/'.$var->getVariableKey().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $display_value = json_encode($var->getVariableValue());

    $properties = id(new PHUIPropertyListView())
      ->setUser($user)
      ->setObject($var)
      ->setActionList($actions)
      ->addProperty(pht('Value'), $display_value);

    $timeline = $this->buildTransactionTimeline(
      $var,
      new PhluxTransactionQuery());
    $timeline->setShouldTerminate(true);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $timeline,
      ),
      array(
        'title'  => $title,
      ));
  }

}
