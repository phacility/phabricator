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

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($request->getRequestURI()));

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($var);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $var,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setName(pht('Edit Variable'))
        ->setHref($this->getApplicationURI('/edit/'.$var->getVariableKey().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $display_value = json_encode($var->getVariableValue());

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $var);

    $properties = id(new PhabricatorPropertyListView())
      ->setUser($user)
      ->setObject($var)
      ->addProperty(pht('Value'), $display_value)
      ->addProperty(
        pht('Visible To'),
        $descriptions[PhabricatorPolicyCapability::CAN_VIEW])
      ->addProperty(
        pht('Editable By'),
        $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);


    $xactions = id(new PhluxTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($var->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $xaction_view,
      ),
      array(
        'title'  => $title,
        'device' => true,
        'dust'   => true,
      ));
  }

}
