<?php

final class DifferentialRevisionEditEngine
  extends PhabricatorEditEngine {

  private $diff;

  const ENGINECONST = 'differential.revision';

  const ACTIONGROUP_REVIEW = 'review';
  const ACTIONGROUP_REVISION = 'revision';

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

  public function isEngineConfigurable() {
    return false;
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return DifferentialRevision::initializeNewRevision($viewer);
  }

  protected function newObjectQuery() {
    return id(new DifferentialRevisionQuery())
      ->needActiveDiffs(true)
      ->needReviewers(true)
      ->needReviewerAuthority(true);
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

  public function getCreateURI($form_key) {
    return '/differential/diff/create/';
  }

  protected function getObjectCreateShortText() {
    return pht('Create Revision');
  }

  protected function getObjectName() {
    return pht('Revision');
  }

  protected function getCommentViewButtonText($object) {
    if ($object->isDraft()) {
      return pht('Submit Quietly');
    }

    return parent::getCommentViewButtonText($object);
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('revision/edit/');
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function getDiff() {
    return $this->diff;
  }

  protected function newCommentActionGroups() {
    return array(
      id(new PhabricatorEditEngineCommentActionGroup())
        ->setKey(self::ACTIONGROUP_REVIEW)
        ->setLabel(pht('Review Actions')),
      id(new PhabricatorEditEngineCommentActionGroup())
        ->setKey(self::ACTIONGROUP_REVISION)
        ->setLabel(pht('Revision Actions')),
    );
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

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

    $is_create = $this->getIsCreate();
    $is_update = ($diff && !$is_create);

    $fields = array();

    $fields[] = id(new PhabricatorHandlesEditField())
      ->setKey(DifferentialRevisionUpdateTransaction::EDITKEY)
      ->setLabel(pht('Update Diff'))
      ->setDescription(pht('New diff to create or update the revision with.'))
      ->setConduitDescription(pht('Create or update a revision with a diff.'))
      ->setConduitTypeDescription(pht('PHID of the diff.'))
      ->setTransactionType(
        DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE)
      ->setHandleParameterType(new AphrontPHIDListHTTPParameterType())
      ->setSingleValue($diff_phid)
      ->setIsFormField((bool)$diff)
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
      ->setKey(DifferentialRevisionTitleTransaction::EDITKEY)
      ->setLabel(pht('Title'))
      ->setIsRequired(true)
      ->setTransactionType(
        DifferentialRevisionTitleTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('The title of the revision.'))
      ->setConduitDescription(pht('Retitle the revision.'))
      ->setConduitTypeDescription(pht('New revision title.'))
      ->setValue($object->getTitle());

    $author_field = id(new PhabricatorDatasourceEditField())
      ->setKey(DifferentialRevisionAuthorTransaction::EDITKEY)
      ->setLabel(pht('Author'))
      ->setDatasource(new PhabricatorPeopleDatasource())
      ->setTransactionType(
        DifferentialRevisionAuthorTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('Foist this revision upon someone else.'))
      ->setConduitDescription(pht('Foist this revision upon another user.'))
      ->setConduitTypeDescription(pht('New author.'))
      ->setSingleValue($object->getAuthorPHID());

    // Don't show the "Author" field when creating a revision using the web
    // workflow, since it adds more noise than signal to this workflow.
    if ($this->getIsCreate()) {
      $author_field->setIsHidden(true);
    }

    // Only show the "Foist Upon" comment action to the current revision
    // author. Other users can use "Edit Revision", it's just very unlikley
    // that they're interested in this action.
    if ($viewer->getPHID() === $object->getAuthorPHID()) {
      $author_field->setCommentActionLabel(pht('Foist Upon'));
    }

    $fields[] = $author_field;

    $fields[] = id(new PhabricatorRemarkupEditField())
      ->setKey(DifferentialRevisionSummaryTransaction::EDITKEY)
      ->setLabel(pht('Summary'))
      ->setTransactionType(
        DifferentialRevisionSummaryTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('The summary of the revision.'))
      ->setConduitDescription(pht('Change the revision summary.'))
      ->setConduitTypeDescription(pht('New revision summary.'))
      ->setValue($object->getSummary());

    if ($plan_enabled) {
      $fields[] = id(new PhabricatorRemarkupEditField())
        ->setKey(DifferentialRevisionTestPlanTransaction::EDITKEY)
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
      ->setKey(DifferentialRevisionReviewersTransaction::EDITKEY)
      ->setLabel(pht('Reviewers'))
      ->setDatasource(new DifferentialReviewerDatasource())
      ->setUseEdgeTransactions(true)
      ->setTransactionType(
        DifferentialRevisionReviewersTransaction::TRANSACTIONTYPE)
      ->setCommentActionLabel(pht('Change Reviewers'))
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

    // This is a little flimsy, but allows "Maniphest Tasks: ..." to continue
    // working properly in commit messages until we fully sort out T5873.
    $fields[] = id(new PhabricatorHandlesEditField())
      ->setKey('tasks')
      ->setUseEdgeTransactions(true)
      ->setIsFormField(false)
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        DifferentialRevisionHasTaskEdgeType::EDGECONST)
      ->setDescription(pht('Tasks associated with this revision.'))
      ->setConduitDescription(pht('Change associated tasks.'))
      ->setConduitTypeDescription(pht('List of tasks.'))
      ->setValue(array());

    $fields[] = id(new PhabricatorHandlesEditField())
      ->setKey('parents')
      ->setUseEdgeTransactions(true)
      ->setIsFormField(false)
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST)
      ->setDescription(pht('Parent revisions of this revision.'))
      ->setConduitDescription(pht('Change associated parent revisions.'))
      ->setConduitTypeDescription(pht('List of revisions.'))
      ->setValue(array());

    $fields[] = id(new PhabricatorHandlesEditField())
      ->setKey('children')
      ->setUseEdgeTransactions(true)
      ->setIsFormField(false)
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        DifferentialRevisionDependedOnByRevisionEdgeType::EDGECONST)
      ->setDescription(pht('Child revisions of this revision.'))
      ->setConduitDescription(pht('Change associated child revisions.'))
      ->setConduitTypeDescription(pht('List of revisions.'))
      ->setValue(array());

    $actions = DifferentialRevisionActionTransaction::loadAllActions();
    $actions = msortv($actions, 'getRevisionActionOrderVector');

    foreach ($actions as $key => $action) {
      $fields[] = $action->newEditField($object, $viewer);
    }

    $fields[] = id(new PhabricatorBoolEditField())
      ->setKey('draft')
      ->setLabel(pht('Hold as Draft'))
      ->setIsFormField(false)
      ->setOptions(
        pht('Autosubmit Once Builds Finish'),
        pht('Hold as Draft'))
      ->setTransactionType(
        DifferentialRevisionHoldDraftTransaction::TRANSACTIONTYPE)
      ->setDescription(pht('Hold revision as as draft.'))
      ->setConduitDescription(
        pht(
          'Change autosubmission from draft state after builds finish.'))
      ->setConduitTypeDescription(pht('New "Hold as Draft" setting.'))
      ->setValue($object->getHoldAsDraft());

    return $fields;
  }

  private function isCustomFieldEnabled(DifferentialRevision $revision, $key) {
    $field_list = PhabricatorCustomField::getObjectFields(
      $revision,
      PhabricatorCustomField::ROLE_VIEW);

    $fields = $field_list->getFields();
    return isset($fields[$key]);
  }

  protected function newAutomaticCommentTransactions($object) {
    $viewer = $this->getViewer();

    $editor = $object->getApplicationTransactionEditor()
      ->setActor($viewer);

    $xactions = $editor->newAutomaticInlineTransactions(
      $object,
      DifferentialTransaction::TYPE_INLINE,
      new DifferentialDiffInlineCommentQuery());

    return $xactions;
  }

  protected function newCommentPreviewContent($object, array $xactions) {
    $viewer = $this->getViewer();
    $type_inline = DifferentialTransaction::TYPE_INLINE;

    $inlines = array();
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() === $type_inline) {
        $inlines[] = $xaction->getComment();
      }
    }

    $content = array();

    if ($inlines) {
      // Reload inlines to get inline context.
      $inlines = id(new DifferentialDiffInlineCommentQuery())
        ->setViewer($viewer)
        ->withIDs(mpull($inlines, 'getID'))
        ->needInlineContext(true)
        ->execute();

      $inline_preview = id(new PHUIDiffInlineCommentPreviewListView())
        ->setViewer($viewer)
        ->setInlineComments($inlines);

      $content[] = phutil_tag(
        'div',
        array(
          'id' => 'inline-comment-preview',
        ),
        $inline_preview);
    }

    return $content;
  }


}
