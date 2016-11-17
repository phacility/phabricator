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

  public function getManagementPanelIcon() {
    $repository = $this->getRepository();

    if (!$repository->isTracked()) {
      return 'fa-ban indigo';
    } else {
      return 'fa-code';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'name',
      'callsign',
      'shortName',
      'description',
      'projectPHIDs',
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
    $activate_uri = $repository->getPathURI('edit/activate/');
    $delete_uri = $repository->getPathURI('edit/delete/');
    $encoding_uri = $this->getEditPageURI('encoding');
    $dangerous_uri = $repository->getPathURI('edit/dangerous/');

    if ($repository->isTracked()) {
      $activate_icon = 'fa-pause';
      $activate_label = pht('Deactivate Repository');
    } else {
      $activate_icon = 'fa-play';
      $activate_label = pht('Activate Repository');
    }

    $should_dangerous = $repository->shouldAllowDangerousChanges();
    if ($should_dangerous) {
      $dangerous_icon = 'fa-shield';
      $dangerous_name = pht('Prevent Dangerous Changes');
      $can_dangerous = $can_edit;
    } else {
      $dangerous_icon = 'fa-bullseye';
      $dangerous_name = pht('Allow Dangerous Changes');
      $can_dangerous = ($can_edit && $repository->canAllowDangerousChanges());
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
        ->setIcon($dangerous_icon)
        ->setName($dangerous_name)
        ->setHref($dangerous_uri)
        ->setDisabled(!$can_dangerous)
        ->setWorkflow(true),
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

    $basics = $this->newBox(pht('Repository Basics'), $this->buildBasics());

    $repository = $this->getRepository();
    $is_new = $repository->isNewlyInitialized();
    if ($is_new) {
      $messages = array();

      $messages[] = pht(
        'This newly created repository is not active yet. Configure policies, '.
        'options, and URIs. When ready, %s the repository.',
        phutil_tag('strong', array(), pht('Activate')));

      if ($repository->isHosted()) {
        $messages[] = pht(
          'If activated now, this repository will become a new hosted '.
          'repository. To observe an existing repository instead, configure '.
          'it in the %s panel.',
          phutil_tag('strong', array(), pht('URIs')));
      } else {
        $messages[] = pht(
          'If activated now, this repository will observe an existing remote '.
          'repository and begin importing changes.');
      }

      $info_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->setErrors($messages);

      $basics->setInfoView($info_view);
    }

    $result[] = $basics;

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
      $short_name = phutil_tag('em', array(), pht('No Short Name'));
    }
    $view->addProperty(pht('Short Name'), $short_name);

    $encoding = $repository->getDetail('encoding');
    if (!$encoding) {
      $encoding = phutil_tag('em', array(), pht('Use Default (UTF-8)'));
    }
    $view->addProperty(pht('Encoding'), $encoding);

    $can_dangerous = $repository->canAllowDangerousChanges();
    if (!$can_dangerous) {
      $dangerous = phutil_tag('em', array(), pht('Not Preventable'));
    } else {
      $should_dangerous = $repository->shouldAllowDangerousChanges();
      if ($should_dangerous) {
        $dangerous = pht('Allowed');
      } else {
        $dangerous = pht('Not Allowed');
      }
    }

    $view->addProperty(pht('Dangerous Changes'), $dangerous);

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
