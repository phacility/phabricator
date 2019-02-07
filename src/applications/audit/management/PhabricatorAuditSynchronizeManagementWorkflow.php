<?php

final class PhabricatorAuditSynchronizeManagementWorkflow
  extends PhabricatorAuditManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('synchronize')
      ->setExamples(
        "**synchronize** __repository__ ...\n".
        "**synchronize** __commit__ ...\n".
        "**synchronize** --all")
      ->setSynopsis(
        pht(
          'Update commits to make their summary audit state reflect the '.
          'state of their actual audit requests. This can fix inconsistencies '.
          'in database state if audit requests have been mangled '.
          'accidentally (or on purpose).'))
      ->setArguments(
        array_merge(
          $this->getCommitConstraintArguments(),
          array()));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();
    $objects = $this->loadCommitsWithConstraints($args);

    foreach ($objects as $object) {
      $commits = $this->loadCommitsForConstraintObject($object);
      foreach ($commits as $commit) {
        $this->synchronizeCommitAuditState($commit->getPHID());
      }
    }
  }

}
