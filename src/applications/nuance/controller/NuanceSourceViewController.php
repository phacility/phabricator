<?php

final class NuanceSourceViewController extends NuanceController {

  private $sourceID;

  public function setSourceID($source_id) {
    $this->sourceID = $source_id;
    return $this;
  }
  public function getSourceID() {
    return $this->sourceID;
  }

  public function willProcessRequest(array $data) {
    $this->setSourceID($data['id']);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $source_id = $this->getSourceID();
    $source = id(new NuanceSourceQuery())
      ->setViewer($viewer)
      ->withIDs(array($source_id))
      ->executeOne();

    if (!$source) {
      return new Aphront404Response();
    }

    $source_phid = $source->getPHID();
    $xactions = id(new NuanceSourceTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($source_phid))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($source_phid)
      ->setMarkupEngine($engine)
      ->setTransactions($xactions);

    $title = pht('%s', $source->getName());
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    $header = $this->buildHeaderView($source);
    $actions = $this->buildActionView($source);
    $properties = $this->buildPropertyView($source, $actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));

  }


 private function buildHeaderView(NuanceSource $source) {
    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($source->getName())
      ->setPolicyObject($source);

    return $header;
  }

  private function buildActionView(NuanceSource $source) {
    $viewer = $this->getRequest()->getUser();
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
        ->setIcon('edit')
        ->setHref($this->getApplicationURI("source/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

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

    $definition = NuanceSourceDefinition::getDefinitionForSource($source);
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
