<?php

final class DiffusionRepositoryActionsManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'actions';

  public function getManagementPanelLabel() {
    return pht('Actions');
  }

  public function getManagementPanelOrder() {
    return 1100;
  }

  protected function buildManagementPanelActions() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions_uri = $repository->getPathURI('edit/actions/');

    return array(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Actions'))
        ->setHref($actions_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit),
    );
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer)
      ->setActionList($this->newActions());

    $notify = $repository->getDetail('herald-disabled')
      ? pht('Off')
      : pht('On');
    $notify = phutil_tag('em', array(), $notify);
    $view->addProperty(pht('Publish/Notify'), $notify);

    $autoclose = $repository->getDetail('disable-autoclose')
      ? pht('Off')
      : pht('On');
    $autoclose = phutil_tag('em', array(), $autoclose);
    $view->addProperty(pht('Autoclose'), $autoclose);

    return $this->newBox(pht('Branches'), $view);
  }

}
