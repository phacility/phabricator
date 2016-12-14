<?php

final class DifferentialRevisionEditEngine
  extends PhabricatorEditEngine {

  private $diff;

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
      ->needActiveDiffs(true)
      ->needReviewerStatus(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Revision');
  }

  protected function getObjectEditTitleText($object) {
    $monogram = $object->getMonogram();
    $title = $object->getTitle();

    $diff = $this->getDiff();
    if ($diff) {
      return pht('Update Revision %s: %s', $monogram, $title);
    } else {
      return pht('Edit Revision %s: %s', $monogram, $title);
    }
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

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function getDiff() {
    return $this->diff;
  }

  protected function buildCustomEditFields($object) {

    $plan_required = PhabricatorEnv::getEnvConfig(
      'differential.require-test-plan-field');
    $plan_enabled = $this->isCustomFieldEnabled(
      $object,
      'differential:test-plan');

    $diff = $this->getDiff();
    if ($diff) {
      $diff_phid = $diff->getPHID();
    } else {
      $diff_phid = null;
    }

    $is_update = ($diff && $object->getID());

    $fields = array();

    $fields[] = id(new PhabricatorHandlesEditField())
      ->setKey('update')
      ->setLabel(pht('Update Diff'))
      ->setDescription(pht('New diff to create or update the revision with.'))
      ->setConduitDescription(pht('Create or update a revision with a diff.'))
      ->setConduitTypeDescription(pht('PHID of the diff.'))
      ->setTransactionType(DifferentialTransaction::TYPE_UPDATE)
      ->setHandleParameterType(new AphrontPHIDListHTTPParameterType())
      ->setSingleValue($diff_phid)
      ->setIsReorderable(false)
      ->setIsDefaultable(false)
      ->setIsInvisible(true)
      ->setIsLockable(false);

    if ($is_update) {
      $fields[] = id(new PhabricatorInstructionsEditField())
        ->setKey('update.help')
        ->setValue(pht('Describe the updates you have made to the diff.'));
      $fields[] = id(new PhabricatorCommentEditField())
        ->setKey('update.comment')
        ->setLabel(pht('Comment'))
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->setIsWebOnly(true)
        ->setDescription(pht('Comments providing context for the update.'));
      $fields[] = id(new PhabricatorSubmitEditField())
        ->setKey('update.submit')
        ->setValue($this->getObjectEditButtonText($object));
      $fields[] = id(new PhabricatorDividerEditField())
        ->setKey('update.note');
    }

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
