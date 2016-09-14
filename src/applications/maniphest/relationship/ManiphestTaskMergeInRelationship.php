<?php

final class ManiphestTaskMergeInRelationship
  extends ManiphestTaskRelationship {

  const RELATIONSHIPKEY = 'task.merge-in';

  public function getEdgeConstant() {
    return ManiphestTaskHasDuplicateTaskEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('Merge Duplicates In');
  }

  protected function getActionIcon() {
    return 'fa-compress';
  }

  public function canRelateObjects($src, $dst) {
    return ($dst instanceof ManiphestTask);
  }

  public function shouldAppearInActionMenu() {
    return false;
  }

  public function getDialogTitleText() {
    return pht('Merge Duplicates Into This Task');
  }

  public function getDialogHeaderText() {
    return pht('Tasks to Close and Merge');
  }

  public function getDialogButtonText() {
    return pht('Close and Merge Selected Tasks');
  }

  protected function newRelationshipSource() {
    return id(new ManiphestTaskRelationshipSource())
      ->setSelectedFilter('open');
  }

  public function getRequiredRelationshipCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function canUndoRelationship() {
    return false;
  }

  public function willUpdateRelationships($object, array $add, array $rem) {
    return $this->newMergeFromTransactions($add);
  }

  public function didUpdateRelationships($object, array $add, array $rem) {
    $viewer = $this->getViewer();
    $content_source = $this->getContentSource();

    foreach ($add as $task) {
      $xactions = $this->newMergeIntoTransactions($object);

      $task->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSource($content_source)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->applyTransactions($task, $xactions);
    }
  }

}
