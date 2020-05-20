<?php

final class DiffusionCommitEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'diffusion.commit';

  const ACTIONGROUP_AUDIT = 'audit';
  const ACTIONGROUP_COMMIT = 'commit';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Commits');
  }

  public function getSummaryHeader() {
    return pht('Edit Commits');
  }

  public function getSummaryText() {
    return pht('Edit commits.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function newEditableObject() {
    // NOTE: We must return a valid object here so that things like Conduit
    // documentation generation work. You can't actually create commits via
    // EditEngine. This is enforced with a "No One" creation policy.

    $repository = new PhabricatorRepository();
    $data = new PhabricatorRepositoryCommitData();

    return id(new PhabricatorRepositoryCommit())
      ->attachRepository($repository)
      ->attachCommitData($data)
      ->attachAudits(array());
  }

  protected function newObjectQuery() {
    $viewer = $this->getViewer();

    return id(new DiffusionCommitQuery())
      ->needCommitData(true)
      ->needAuditRequests(true)
      ->needAuditAuthority(array($viewer))
      ->needIdentities(true);
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('commit/edit/');
  }

  protected function newCommentActionGroups() {
    return array(
      id(new PhabricatorEditEngineCommentActionGroup())
        ->setKey(self::ACTIONGROUP_AUDIT)
        ->setLabel(pht('Audit Actions')),
      id(new PhabricatorEditEngineCommentActionGroup())
        ->setKey(self::ACTIONGROUP_COMMIT)
        ->setLabel(pht('Commit Actions')),
    );
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Commit');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Commit');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Commit: %s', $object->getDisplayName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getDisplayName();
  }

  protected function getObjectName() {
    return pht('Commit');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getCreateNewObjectPolicy() {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();
    $data = $object->getCommitData();

    $fields = array();

    $fields[] = id(new PhabricatorDatasourceEditField())
      ->setKey('auditors')
      ->setLabel(pht('Auditors'))
      ->setDatasource(new DiffusionAuditorDatasource())
      ->setUseEdgeTransactions(true)
      ->setTransactionType(
        DiffusionCommitAuditorsTransaction::TRANSACTIONTYPE)
      ->setCommentActionLabel(pht('Change Auditors'))
      ->setDescription(pht('Auditors for this commit.'))
      ->setConduitDescription(pht('Change the auditors for this commit.'))
      ->setConduitTypeDescription(pht('New auditors.'))
      ->setValue($object->getAuditorPHIDsForEdit());

    $actions = DiffusionCommitActionTransaction::loadAllActions();
    $actions = msortv($actions, 'getCommitActionOrderVector');

    foreach ($actions as $key => $action) {
      $fields[] = $action->newEditField($object, $viewer);
    }

    return $fields;
  }

  protected function newAutomaticCommentTransactions($object) {
    $viewer = $this->getViewer();

    $editor = $object->getApplicationTransactionEditor()
      ->setActor($viewer);

    $xactions = $editor->newAutomaticInlineTransactions(
      $object,
      PhabricatorAuditActionConstants::INLINE,
      new DiffusionDiffInlineCommentQuery());

    return $xactions;
  }

  protected function newCommentPreviewContent($object, array $xactions) {
    $viewer = $this->getViewer();
    $type_inline = PhabricatorAuditActionConstants::INLINE;

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
