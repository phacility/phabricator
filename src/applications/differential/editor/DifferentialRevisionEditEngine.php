<?php

final class DifferentialRevisionEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'differential.revision';

  public function getEngineName() {
    return pht('Revisions');
  }

  public function getSummaryHeader() {
    return pht('Configure Revision Forms');
  }

  public function getSummaryText() {
    return pht(
      'Configure creation and editing revision forms in Differential.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  protected function supportsEditEngineConfiguration() {
    return false;
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return DifferentialRevision::initializeNewRevision($viewer);
  }

  protected function newObjectQuery() {
    return id(new DifferentialRevisionQuery())
      ->needReviewerStatus(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Revision');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Revision: %s', $object->getTitle());
  }

  protected function getObjectEditShortText($object) {
    return $object->getMonogram();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Revision');
  }

  protected function getObjectName() {
    return pht('Revision');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {

    $plan_required = PhabricatorEnv::getEnvConfig(
      'differential.require-test-plan-field');
    $plan_enabled = $this->isCustomFieldEnabled(
      $object,
      'differential:test-plan');

    $fields = array();
    $fields[] = id(new PhabricatorTextEditField())
      ->setKey('title')
      ->setLabel(pht('Title'))
      ->setIsRequired(true)
      ->setTransactionType(
        DifferentialRevisionTitleTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('The title of the revision.'))
      ->setConduitDescription(pht('Retitle the revision.'))
      ->setConduitTypeDescription(pht('New revision title.'))
      ->setValue($object->getTitle());

    $fields[] = id(new PhabricatorRemarkupEditField())
      ->setKey('summary')
      ->setLabel(pht('Summary'))
      ->setTransactionType(
        DifferentialRevisionSummaryTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('The summary of the revision.'))
      ->setConduitDescription(pht('Change the revision summary.'))
      ->setConduitTypeDescription(pht('New revision summary.'))
      ->setValue($object->getSummary());

    if ($plan_enabled) {
      $fields[] = id(new PhabricatorRemarkupEditField())
        ->setKey('testPlan')
        ->setLabel(pht('Test Plan'))
        ->setIsRequired($plan_required)
        ->setTransactionType(
          DifferentialRevisionTestPlanTransaction::TRANSACTIONTYPE)
        ->setDescription(
          pht('Actions performed to verify the behavior of the change.'))
        ->setConduitDescription(pht('Update the revision test plan.'))
        ->setConduitTypeDescription(pht('New test plan.'))
        ->setValue($object->getTestPlan());
    }

    $fields[] = id(new PhabricatorDatasourceEditField())
      ->setKey('reviewerPHIDs')
      ->setLabel(pht('Reviewers'))
      ->setDatasource(new DifferentialReviewerDatasource())
      ->setUseEdgeTransactions(true)
      ->setTransactionType(
        DifferentialRevisionReviewersTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('Reviewers for this revision.'))
      ->setConduitDescription(pht('Change the reviewers for this revision.'))
      ->setConduitTypeDescription(pht('New reviewers.'))
      ->setValue($object->getReviewerPHIDsForEdit());

    $fields[] = id(new PhabricatorDatasourceEditField())
      ->setKey('repositoryPHID')
      ->setLabel(pht('Repository'))
      ->setDatasource(new DiffusionRepositoryDatasource())
      ->setTransactionType(
        DifferentialRevisionRepositoryTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('The repository the revision belongs to.'))
      ->setConduitDescription(pht('Change the repository for this revision.'))
      ->setConduitTypeDescription(pht('New repository.'))
      ->setSingleValue($object->getRepositoryPHID());

    return $fields;
  }

  private function isCustomFieldEnabled(DifferentialRevision $revision, $key) {
    $field_list = PhabricatorCustomField::getObjectFields(
      $revision,
      PhabricatorCustomField::ROLE_EDIT);

    $fields = $field_list->getFields();
    return isset($fields[$key]);
  }

}
