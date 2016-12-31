<?php

final class DifferentialRevisionEditEngine
  extends PhabricatorEditEngine {

  private $diff;

  const ENGINECONST = 'differential.revision';

  const KEY_UPDATE = 'update';

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
      ->needReviewerStatus(true)
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

  protected function getObjectCreateShortText() {
    return pht('Create Revision');
  }

  protected function getObjectName() {
    return pht('Revision');
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
      ->setKey(self::KEY_UPDATE)
      ->setLabel(pht('Update Diff'))
      ->setDescription(pht('New diff to create or update the revision with.'))
      ->setConduitDescription(pht('Create or update a revision with a diff.'))
      ->setConduitTypeDescription(pht('PHID of the diff.'))
      ->setTransactionType(DifferentialTransaction::TYPE_UPDATE)
      ->setHandleParameterType(new AphrontPHIDListHTTPParameterType())
      ->setSingleValue($diff_phid)
      ->setIsConduitOnly(!$diff)
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
      ->setIsConduitOnly(true)
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        DifferentialRevisionHasTaskEdgeType::EDGECONST)
      ->setDescription(pht('Tasks associated with this revision.'))
      ->setConduitDescription(pht('Change associated tasks.'))
      ->setConduitTypeDescription(pht('List of tasks.'))
      ->setValue(array());

    $actions = DifferentialRevisionActionTransaction::loadAllActions();
    $actions = msortv($actions, 'getRevisionActionOrderVector');

    foreach ($actions as $key => $action) {
      $fields[] = $action->newEditField($object, $viewer);
    }

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
    $xactions = array();

    $inlines = DifferentialTransactionQuery::loadUnsubmittedInlineComments(
      $viewer,
      $object);
    $inlines = msort($inlines, 'getID');

    foreach ($inlines as $inline) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType(DifferentialTransaction::TYPE_INLINE)
        ->attachComment($inline);
    }

    $viewer_phid = $viewer->getPHID();
    $viewer_is_author = ($object->getAuthorPHID() == $viewer_phid);
    if ($viewer_is_author) {
      $state_map = PhabricatorTransactions::getInlineStateMap();

      $inlines = id(new DifferentialDiffInlineCommentQuery())
        ->setViewer($viewer)
        ->withRevisionPHIDs(array($object->getPHID()))
        ->withFixedStates(array_keys($state_map))
        ->execute();
      if ($inlines) {
        $old_value = mpull($inlines, 'getFixedState', 'getPHID');
        $new_value = array();
        foreach ($old_value as $key => $state) {
          $new_value[$key] = $state_map[$state];
        }

        $xactions[] = id(new DifferentialTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_INLINESTATE)
          ->setIgnoreOnNoEffect(true)
          ->setOldValue($old_value)
          ->setNewValue($new_value);
      }
    }

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
