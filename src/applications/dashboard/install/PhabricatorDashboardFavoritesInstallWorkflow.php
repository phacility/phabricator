<?php

final class PhabricatorDashboardFavoritesInstallWorkflow
  extends PhabricatorDashboardApplicationInstallWorkflow {

  const WORKFLOWKEY = 'favorites';

  public function getOrder() {
    return 4000;
  }

  protected function newWorkflowMenuItem() {
    return $this->newMenuItem()
      ->setHeader(pht('Add to Favorites Menu'))
      ->setImageIcon('fa-bookmark')
      ->addAttribute(
        pht(
          'Add this dashboard to the favorites menu in the main '.
          'menu bar.'));
  }

  protected function newProfileEngine() {
    return new PhabricatorFavoritesProfileMenuEngine();
  }

  protected function newApplication() {
    return new PhabricatorFavoritesApplication();
  }

  protected function newApplicationModeDialog() {
    return $this->newDialog()
      ->setTitle(pht('Add Dashboard to Favorites Menu'));
  }

  protected function newPersonalMenuItem() {
    return $this->newMenuItem()
      ->setHeader(pht('Add to Personal Favorites'))
      ->setImageIcon('fa-user')
      ->addAttribute(
        pht(
          'Add this dashboard to your list of personal favorite menu items, '.
          'visible to only you.'));
  }

  protected function newGlobalMenuItem() {
    return $this->newMenuItem()
      ->setHeader(pht('Add to Global Favorites'))
      ->setImageIcon('fa-globe')
      ->addAttribute(
        pht(
          'Add this dashboard to the global favorites menu, visible to all '.
          'users.'));
  }

  protected function newGlobalPermissionDialog() {
    return $this->newDialog()
      ->setTitle(pht('No Permission'))
      ->appendParagraph(
        pht(
          'You do not have permission to install items on the global '.
          'favorites menu.'));
  }

  protected function newGlobalConfirmDialog() {
    return $this->newDialog()
      ->setTitle(pht('Add Dashboard to Global Favorites'))
      ->appendParagraph(
        pht(
          'Add dashboard %s as a global menu item in the favorites menu?',
          $this->getDashboardDisplayName()))
      ->addSubmitButton(pht('Add to Favorites'));
  }

  protected function newPersonalConfirmDialog() {
    return $this->newDialog()
      ->setTitle(pht('Add Dashboard to Personal Favorites'))
      ->appendParagraph(
        pht(
          'Add dashboard %s as a personal menu item in the favorites menu?',
          $this->getDashboardDisplayName()))
      ->addSubmitButton(pht('Add to Favorites'));
  }


}
