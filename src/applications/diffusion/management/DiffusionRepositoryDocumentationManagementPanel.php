<?php

final class DiffusionRepositoryDocumentationManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'documentation';

  public function getManagementPanelLabel() {
    return pht('Documentation');
  }

  public function getManagementPanelOrder() {
    return 3000;
  }

  public function getManagementPanelIcon() {
    return 'fa-book';
  }

  public function buildManagementPanelContent() {
    return null;
  }

  public function getPanelNavigationURI() {
    return PhabricatorEnv::getDoclink(
      'Diffusion User Guide: Managing Repositories');
  }

}
