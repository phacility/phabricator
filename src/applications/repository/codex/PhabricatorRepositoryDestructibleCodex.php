<?php

final class PhabricatorRepositoryDestructibleCodex
  extends PhabricatorDestructibleCodex {

  public function getDestructionNotes() {
    $repository = $this->getObject();

    $notes = array();

    if ($repository->hasLocalWorkingCopy()) {
      $notes[] = pht(
        'Database records for repository "%s" were destroyed, but this '.
        'script does not remove working copies on disk. If you also want to '.
        'destroy the repository working copy, manually remove "%s".',
        $repository->getDisplayName(),
        $repository->getLocalPath());
    }

    return $notes;
  }

}
