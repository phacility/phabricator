<?php

final class DiffusionRepositoryPoliciesManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'policies';

  public function getManagementPanelLabel() {
    return pht('Policies');
  }

  public function getManagementPanelOrder() {
    return 300;
  }

  public function getManagementPanelIcon() {
    $viewer = $this->getViewer();
    $repository = $this->getRepository();

    $can_view = PhabricatorPolicyCapability::CAN_VIEW;
    $can_edit = PhabricatorPolicyCapability::CAN_EDIT;
    $can_push = DiffusionPushCapability::CAPABILITY;

    $actual_values = array(
      'spacePHID' => $repository->getSpacePHID(),
      'view' => $repository->getPolicy($can_view),
      'edit' => $repository->getPolicy($can_edit),
      'push' => $repository->getPolicy($can_push),
    );

    $default = PhabricatorRepository::initializeNewRepository(
      $viewer);

    $default_values = array(
      'spacePHID' => $default->getSpacePHID(),
      'view' => $default->getPolicy($can_view),
      'edit' => $default->getPolicy($can_edit),
      'push' => $default->getPolicy($can_push),
    );

    if ($actual_values === $default_values) {
      return 'fa-lock grey';
    } else {
      return 'fa-lock';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'policy.view',
      'policy.edit',
      'spacePHID',
      'policy.push',
    );
  }

  protected function buildManagementPanelActions() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getEditPageURI();

    return array(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Policies'))
        ->setHref($edit_uri)
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

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $repository);

    $view_parts = array();
    if (PhabricatorSpacesNamespaceQuery::getViewerSpacesExist($viewer)) {
      $space_phid = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID(
        $repository);
      $view_parts[] = $viewer->renderHandle($space_phid);
    }
    $view_parts[] = $descriptions[PhabricatorPolicyCapability::CAN_VIEW];

    $view->addProperty(
      pht('Visible To'),
      phutil_implode_html(" \xC2\xB7 ", $view_parts));

    $view->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $pushable = $repository->isHosted()
      ? $descriptions[DiffusionPushCapability::CAPABILITY]
      : phutil_tag('em', array(), pht('Not a Hosted Repository'));
    $view->addProperty(pht('Pushable By'), $pushable);

    return $this->newBox(pht('Policies'), $view);
  }

}
