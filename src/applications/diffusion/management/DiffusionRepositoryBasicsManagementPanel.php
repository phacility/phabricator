<?php

final class DiffusionRepositoryBasicsManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'basics';

  public function getManagementPanelLabel() {
    return pht('Basics');
  }

  public function getManagementPanelOrder() {
    return 100;
  }

  protected function buildManagementPanelActions() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $repository->getPathURI('manage/');
    $activate_uri = $repository->getPathURI('edit/activate/');
    $delete_uri = $repository->getPathURI('edit/delete/');
    $encoding_uri = $repository->getPathURI('edit/encoding/');

    if ($repository->isTracked()) {
      $activate_icon = 'fa-pause';
      $activate_label = pht('Deactivate Repository');
    } else {
      $activate_icon = 'fa-play';
      $activate_label = pht('Activate Repository');
    }

    return array(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Basic Information'))
        ->setHref($edit_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit),
      id(new PhabricatorActionView())
        ->setIcon('fa-text-width')
        ->setName(pht('Edit Text Encoding'))
        ->setHref($encoding_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit),
      id(new PhabricatorActionView())
        ->setHref($activate_uri)
        ->setIcon($activate_icon)
        ->setName($activate_label)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true),
      id(new PhabricatorActionView())
        ->setName(pht('Delete Repository'))
        ->setIcon('fa-times')
        ->setHref($delete_uri)
        ->setDisabled(true)
        ->setWorkflow(true),
    );
  }

  public function buildManagementPanelContent() {
    $result = array();

    $result[] = $this->newBox(pht('Repository Basics'), $this->buildBasics());

    $description = $this->buildDescription();
    if ($description) {
      $result[] = $this->newBox(pht('Description'), $description);
    }

    return $result;
  }

  private function buildBasics() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer)
      ->setActionList($this->newActions());

    $name = $repository->getName();
    $view->addProperty(pht('Name'), $name);

    $type = PhabricatorRepositoryType::getNameForRepositoryType(
      $repository->getVersionControlSystem());
    $view->addProperty(pht('Type'), $type);

    $callsign = $repository->getCallsign();
    if (!strlen($callsign)) {
      $callsign = phutil_tag('em', array(), pht('No Callsign'));
    }
    $view->addProperty(pht('Callsign'), $callsign);

    $short_name = $repository->getRepositorySlug();
    if ($short_name === null) {
      $short_name = $repository->getCloneName();
      $short_name = phutil_tag('em', array(), $short_name);
    }
    $view->addProperty(pht('Short Name'), $short_name);

    $encoding = $repository->getDetail('encoding');
    if (!$encoding) {
      $encoding = phutil_tag('em', array(), pht('Use Default (UTF-8)'));
    }
    $view->addProperty(pht('Encoding'), $encoding);

    return $view;
  }


  private function buildDescription() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $description = $repository->getDetail('description');

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);
    if (!strlen($description)) {
      $description = phutil_tag('em', array(), pht('No description provided.'));
    } else {
      $description = new PHUIRemarkupView($viewer, $description);
    }
    $view->addTextContent($description);

    return $view;
  }

}
