<?php

final class PhabricatorAuditSynchronizeManagementWorkflow
  extends PhabricatorAuditManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('synchronize')
      ->setExamples('**synchronize** ...')
      ->setSynopsis(pht('Update audit status for commits.'))
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
        $commit = id(new DiffusionCommitQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($commit->getPHID()))
          ->needAuditRequests(true)
          ->executeOne();
        if (!$commit) {
          continue;
        }

        $old_status = $commit->getAuditStatusObject();
        $commit->updateAuditStatus($commit->getAudits());
        $new_status = $commit->getAuditStatusObject();

        if ($old_status->getKey() == $new_status->getKey()) {
          echo tsprintf(
            "%s\n",
            pht(
              'No changes for "%s".',
              $commit->getDisplayName()));
        } else {
          echo tsprintf(
            "%s\n",
            pht(
              'Updating "%s": "%s" -> "%s".',
              $commit->getDisplayName(),
              $old_status->getName(),
              $new_status->getName()));

          $commit->save();
        }
      }
    }
  }

}
