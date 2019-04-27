<?php

final class PhabricatorDashboardHomeInstallWorkflow
  extends PhabricatorDashboardApplicationInstallWorkflow {

  const WORKFLOWKEY = 'home';

  public function getOrder() {
    return 1000;
  }

  protected function newWorkflowMenuItem() {
    return $this->newMenuItem()
      ->setHeader(pht('Add to Home Page Menu'))
      ->setImageIcon('fa-home')
      ->addAttribute(
        pht(
          'Add this dashboard to the menu on the home page.'));
  }

  protected function newProfileEngine() {
    return new PhabricatorHomeProfileMenuEngine();
  }

  protected function newApplication() {
    return new PhabricatorHomeApplication();
  }

  protected function newApplicationModeDialog() {
    return $this->newDialog()
      ->setTitle(pht('Add Dashboard to Home Menu'));
  }

  protected function newPersonalMenuItem() {
    return $this->newMenuItem()
      ->setHeader(pht('Add to Personal Home Menu'))
      ->setImageIcon('fa-user')
      ->addAttribute(
        pht(
          'Add this dashboard to your list of personal home menu items, '.
          'visible to only you.'));
  }

  protected function newGlobalMenuItem() {
    return $this->newMenuItem()
      ->setHeader(pht('Add to Global Home Menu'))
      ->setImageIcon('fa-globe')
      ->addAttribute(
        pht(
          'Add this dashboard to the global home menu, visible to all '.
          'users.'));
  }

  protected function newGlobalPermissionDialog() {
    return $this->newDialog()
      ->setTitle(pht('No Permission'))
      ->appendParagraph(
        pht(
          'You do not have permission to install items on the global home '.
          'menu.'));
  }

  protected function newGlobalConfirmDialog() {
    return $this->newDialog()
      ->setTitle(pht('Add Dashboard to Global Home Page'))
      ->appendParagraph(
        pht(
          'Add dashboard %s as a global menu item on the home page?',
          $this->getDashboardDisplayName()))
      ->addSubmitButton(pht('Add to Home'));
  }

  protected function newPersonalConfirmDialog() {
    return $this->newDialog()
      ->setTitle(pht('Add Dashboard to Personal Home Page'))
      ->appendParagraph(
        pht(
          'Add dashboard %s as a personal menu item on your home page?',
          $this->getDashboardDisplayName()))
      ->addSubmitButton(pht('Add to Home'));
  }

}
