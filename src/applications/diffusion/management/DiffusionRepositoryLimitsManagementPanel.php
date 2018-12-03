<?php

final class DiffusionRepositoryLimitsManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'limits';

  public function getManagementPanelLabel() {
    return pht('Limits');
  }

  public function getManagementPanelOrder() {
    return 700;
  }

  public function shouldEnableForRepository(
    PhabricatorRepository $repository) {
    return $repository->isGit();
  }

  public function getManagementPanelIcon() {
    $repository = $this->getRepository();

    $any_limit = false;

    if ($repository->getFilesizeLimit()) {
      $any_limit = true;
    }

    if ($any_limit) {
      return 'fa-signal';
    } else {
      return 'fa-signal grey';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'filesizeLimit',
      'copyTimeLimit',
      'touchLimit',
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

    $limits_uri = $this->getEditPageURI();

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Limits'))
        ->setHref($limits_uri)
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

    $byte_limit = $repository->getFilesizeLimit();
    if ($byte_limit) {
      $filesize_display = pht('%s Bytes', new PhutilNumber($byte_limit));
    } else {
      $filesize_display = pht('Unlimited');
      $filesize_display = phutil_tag('em', array(), $filesize_display);
    }

    $view->addProperty(pht('Filesize Limit'), $filesize_display);

    $copy_limit = $repository->getCopyTimeLimit();
    if ($copy_limit) {
      $copy_display = pht('%s Seconds', new PhutilNumber($copy_limit));
    } else {
      $copy_default = $repository->getDefaultCopyTimeLimit();
      $copy_display = pht(
        'Default (%s Seconds)',
        new PhutilNumber($copy_default));
      $copy_display = phutil_tag('em', array(), $copy_display);
    }

    $view->addProperty(pht('Clone/Fetch Timeout'), $copy_display);

    $touch_limit = $repository->getTouchLimit();
    if ($touch_limit) {
      $touch_display = pht('%s Paths', new PhutilNumber($touch_limit));
    } else {
      $touch_display = pht('Unlimited');
      $touch_display = phutil_tag('em', array(), $touch_display);
    }

    $view->addProperty(pht('Touched Paths Limit'), $touch_display);

    return $this->newBox(pht('Limits'), $view);
  }

}
