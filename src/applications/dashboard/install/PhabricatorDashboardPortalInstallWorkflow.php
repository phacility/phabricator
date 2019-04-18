<?php

final class PhabricatorDashboardPortalInstallWorkflow
  extends PhabricatorDashboardObjectInstallWorkflow {

  const WORKFLOWKEY = 'portal';

  public function getOrder() {
    return 2000;
  }

  protected function newWorkflowMenuItem() {
    return $this->newMenuItem()
      ->setHeader(pht('Add to Portal Menu'))
      ->setImageIcon('fa-compass')
      ->addAttribute(
        pht('Add this dashboard to the menu on a portal.'));
  }

  protected function newProfileEngine() {
    return new PhabricatorDashboardPortalProfileMenuEngine();
  }

  protected function newQuery() {
    return new PhabricatorDashboardPortalQuery();
  }

  protected function newConfirmDialog($object) {
    return $this->newDialog()
      ->setTitle(pht('Add Dashboard to Portal Menu'))
      ->appendParagraph(
        pht(
          'Add the dashboard %s to portal %s?',
          $this->getDashboardDisplayName(),
          phutil_tag('strong', array(), $object->getName())))
      ->addSubmitButton(pht('Add to Portal'));
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
          'Select which portal you want to add the dashboard %s to.',
          $this->getDashboardDisplayName()))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setName('target')
          ->setLimit(1)
          ->setLabel(pht('Add to Portal'))
          ->setValue($tokenizer_value)
          ->setDatasource(new PhabricatorDashboardPortalDatasource()));
  }

}
