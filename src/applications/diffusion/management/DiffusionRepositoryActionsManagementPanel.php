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

  public function getManagementPanelIcon() {
    return 'fa-flash';
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'publish',
      'autoclose',
    );
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

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

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions_uri = $this->getEditPageURI();

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-pencil')
      ->setText(pht('Edit'))
      ->setHref($actions_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    return $this->newBox(pht('Actions'), $view, array($button));
  }

}
