<?php

final class DiffusionRepositoryPublishingManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'publishing';

  public function getManagementPanelLabel() {
    return pht('Publishing');
  }

  public function getManagementPanelOrder() {
    return 1100;
  }

  public function getManagementPanelIcon() {
    $repository = $this->getRepository();

    $has_any = $repository->isPublishingDisabled();

    if ($has_any) {
      return 'fa-flash';
    } else {
      return 'fa-flash grey';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'publish',
    );
  }

  public function buildManagementPanelCurtain() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();
    $action_list = $this->newActionList();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $publishing_uri = $this->getEditPageURI();

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Publishing'))
        ->setHref($publishing_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $this->newCurtainView()
      ->setActionList($action_list);
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $notify = $repository->isPublishingDisabled()
      ? pht('Off')
      : pht('On');
    $notify = phutil_tag('em', array(), $notify);
    $view->addProperty(pht('Publishing'), $notify);

    return $this->newBox(pht('Publishing'), $view);
  }

}
