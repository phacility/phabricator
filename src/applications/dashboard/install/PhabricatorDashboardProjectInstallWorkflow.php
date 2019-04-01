<?php

final class PhabricatorDashboardProjectInstallWorkflow
  extends PhabricatorDashboardObjectInstallWorkflow {

  const WORKFLOWKEY = 'project';

  public function getOrder() {
    return 3000;
  }

  protected function newWorkflowMenuItem() {
    return $this->newMenuItem()
      ->setHeader(pht('Add to Project Menu'))
      ->setImageIcon('fa-briefcase')
      ->addAttribute(
        pht('Add this dashboard to the menu for a project.'));
  }

  protected function newProfileEngine() {
    return new PhabricatorProjectProfileMenuEngine();
  }

  protected function newQuery() {
    return new PhabricatorProjectQuery();
  }

  protected function newConfirmDialog($object) {
    return $this->newDialog()
      ->setTitle(pht('Add Dashboard to Project Menu'))
      ->appendParagraph(
        pht(
          'Add the dashboard %s to the menu for project %s?',
          $this->getDashboardDisplayName(),
          phutil_tag('strong', array(), $object->getName())))
      ->addSubmitButton(pht('Add to Project'));
  }

  protected function newObjectSelectionForm($object) {
    $viewer = $this->getViewer();

    if ($object) {
      $tokenizer_value = array($object->getPHID());
    } else {
      $tokenizer_value = array();
    }

    return id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendInstructions(
        pht(
          'Select which project menu you want to add the dashboard %s to.',
          $this->getDashboardDisplayName()))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setName('target')
          ->setLimit(1)
          ->setLabel(pht('Add to Project'))
          ->setValue($tokenizer_value)
          ->setDatasource(new PhabricatorProjectDatasource()));
  }

}
