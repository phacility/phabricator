<?php

final class PhabricatorAuditCommentEditor extends PhabricatorEditor {

  public static function getMailThreading(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    return array(
      'diffusion-audit-'.$commit->getPHID(),
      pht(
        'Commit %s',
        $commit->getMonogram()),
    );
  }

}
