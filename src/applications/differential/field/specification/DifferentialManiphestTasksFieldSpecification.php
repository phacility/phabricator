<?php

final class DifferentialManiphestTasksFieldSpecification
  extends DifferentialFieldSpecification {

  private $maniphestTasks = array();

  public function shouldAppearOnRevisionView() {
    return PhabricatorEnv::getEnvConfig('maniphest.enabled');
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getManiphestTaskPHIDs();
  }

  public function renderLabelForRevisionView() {
    return 'Maniphest Tasks:';
  }

  public function renderValueForRevisionView() {
    $task_phids = $this->getManiphestTaskPHIDs();
    if (!$task_phids) {
      return null;
    }

    $links = array();
    foreach ($task_phids as $task_phid) {
      $links[] = $this->getHandle($task_phid)->renderLink();
    }

    return implode('<br />', $links);
  }

  private function getManiphestTaskPHIDs() {
    $revision = $this->getRevision();
    if (!$revision->getPHID()) {
      return array();
    }
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision->getPHID(),
      PhabricatorEdgeConfig::TYPE_DREV_HAS_RELATED_TASK);
  }

  /**
   * Attach the revision to the task(s) and the task(s) to the revision.
   *
   * @return void
   */
  public function didWriteRevision(DifferentialRevisionEditor $editor) {
    $revision = $editor->getRevision();
    $revision_phid = $revision->getPHID();
    $edge_type = PhabricatorEdgeConfig::TYPE_DREV_HAS_RELATED_TASK;

    $old_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision_phid,
      $edge_type);
    $add_phids = $this->maniphestTasks;
    $rem_phids = array_diff($old_phids, $add_phids);

    $edge_editor = id(new PhabricatorEdgeEditor())
      ->setActor($this->getUser());

    foreach ($add_phids as $phid) {
      $edge_editor->addEdge($revision_phid, $edge_type, $phid);
    }

    foreach ($rem_phids as $phid) {
      $edge_editor->removeEdge($revision_phid, $edge_type, $phid);
    }

    $edge_editor->save();
  }

  protected function didSetRevision() {
    $this->maniphestTasks = $this->getManiphestTaskPHIDs();
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->maniphestTasks;
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return PhabricatorEnv::getEnvConfig('maniphest.enabled');
  }

  public function shouldAppearOnCommitMessage() {
    return PhabricatorEnv::getEnvConfig('maniphest.enabled');
  }

  public function getCommitMessageKey() {
    return 'maniphestTaskPHIDs';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->maniphestTasks = nonempty($value, array());
    return $this;
  }

  public function renderLabelForCommitMessage() {
    return 'Maniphest Tasks';
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'Maniphest Task',
      'Maniphest Tasks',
    );
  }

  public function renderValueForCommitMessage($is_edit) {
    if (!$this->maniphestTasks) {
      return null;
    }

    $names = array();
    foreach ($this->maniphestTasks as $phid) {
      $handle = $this->getHandle($phid);
      $names[] = 'T'.$handle->getAlternateID();
    }
    return implode(', ', $names);
  }

  public function parseValueFromCommitMessage($value) {
    $matches = null;
    preg_match_all('/T(\d+)/', $value, $matches);
    if (empty($matches[0])) {
      return array();
    }


    $task_ids = $matches[1];
    $tasks = id(new ManiphestTask())
      ->loadAllWhere('id in (%Ld)', $task_ids);

    $task_phids = array();
    $invalid = array();
    foreach ($task_ids as $task_id) {
      $task = idx($tasks, $task_id);
      if (empty($task)) {
        $invalid[] = 'T'.$task_id;
      } else {
        $task_phids[] = $task->getPHID();
      }
    }

    if ($invalid) {
      $what = pht('Maniphest Task(s)', count($invalid));
      $invalid = implode(', ', $invalid);
      throw new DifferentialFieldParseException(
        "Commit message references nonexistent {$what}: {$invalid}.");
    }

    return $task_phids;
  }

  public function renderValueForMail($phase) {
    if ($phase == DifferentialMailPhase::COMMENT) {
      return null;
    }

    if (!$this->maniphestTasks) {
      return null;
    }

    $handles = id(new PhabricatorObjectHandleData($this->maniphestTasks))
      ->loadHandles();
    $body = array();
    $body[] = 'MANIPHEST TASKS';
    foreach ($handles as $handle) {
      $body[] = '  '.PhabricatorEnv::getProductionURI($handle->getURI());
    }
    return implode("\n", $body);
  }

}
