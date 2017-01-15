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
    return id(new DiffusionCommitQuery())
      ->needCommitData(true)
      ->needAuditRequests(true);
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

    $reason = $data->getCommitDetail('autocloseReason', false);
    $reason = PhabricatorRepository::BECAUSE_AUTOCLOSE_FORCED;
    if ($reason !== false) {
      switch ($reason) {
        case PhabricatorRepository::BECAUSE_REPOSITORY_IMPORTING:
          $desc = pht('No, Repository Importing');
          break;
        case PhabricatorRepository::BECAUSE_AUTOCLOSE_DISABLED:
          $desc = pht('No, Autoclose Disabled');
          break;
        case PhabricatorRepository::BECAUSE_NOT_ON_AUTOCLOSE_BRANCH:
          $desc = pht('No, Not On Autoclose Branch');
          break;
        case PhabricatorRepository::BECAUSE_AUTOCLOSE_FORCED:
          $desc = pht('Yes, Forced Via bin/repository CLI Tool.');
          break;
        case null:
          $desc = pht('Yes');
          break;
        default:
          $desc = pht('Unknown');
          break;
      }

      $doc_href = PhabricatorEnv::getDoclink('Diffusion User Guide: Autoclose');
      $doc_link = phutil_tag(
        'a',
        array(
          'href' => $doc_href,
          'target' => '_blank',
        ),
        pht('Learn More'));

        $fields[] = id(new PhabricatorStaticEditField())
          ->setLabel(pht('Autoclose?'))
          ->setValue(array($desc, " \xC2\xB7 ", $doc_link));
    }

    $actions = DiffusionCommitActionTransaction::loadAllActions();
    $actions = msortv($actions, 'getCommitActionOrderVector');

    foreach ($actions as $key => $action) {
      $fields[] = $action->newEditField($object, $viewer);
    }

    return $fields;
  }

  protected function newAutomaticCommentTransactions($object) {
    $viewer = $this->getViewer();
    $xactions = array();

    $inlines = PhabricatorAuditInlineComment::loadDraftComments(
      $viewer,
      $object->getPHID(),
      $raw = true);
    $inlines = msort($inlines, 'getID');

    foreach ($inlines as $inline) {
      $xactions[] = $object->getApplicationTransactionTemplate()
        ->setTransactionType(PhabricatorAuditActionConstants::INLINE)
        ->attachComment($inline);
    }

    $viewer_phid = $viewer->getPHID();
    $viewer_is_author = ($object->getAuthorPHID() == $viewer_phid);
    if ($viewer_is_author) {
      $state_map = PhabricatorTransactions::getInlineStateMap();

      $inlines = id(new DiffusionDiffInlineCommentQuery())
        ->setViewer($viewer)
        ->withCommitPHIDs(array($object->getPHID()))
        ->withFixedStates(array_keys($state_map))
        ->execute();
      if ($inlines) {
        $old_value = mpull($inlines, 'getFixedState', 'getPHID');
        $new_value = array();
        foreach ($old_value as $key => $state) {
          $new_value[$key] = $state_map[$state];
        }

        $xactions[] = $object->getApplicationTransactionTemplate()
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
