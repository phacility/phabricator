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
    return 'fa-lock';
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'policy.view',
      'policy.edit',
      'spacePHID',
      'policy.push',
    );
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

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

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getEditPageURI();

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-pencil')
      ->setText(pht('Edit'))
      ->setHref($edit_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    return $this->newBox(pht('Policies'), $view, array($button));
  }

}
