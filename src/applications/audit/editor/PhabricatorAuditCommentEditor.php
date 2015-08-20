<?php

final class PhabricatorAuditCommentEditor extends PhabricatorEditor {

  /**
   * Load the PHIDs for all objects the user has the authority to act as an
   * audit for. This includes themselves, and any packages they are an owner
   * of.
   */
  public static function loadAuditPHIDsForUser(PhabricatorUser $user) {
    $phids = array();

    // TODO: This method doesn't really use the right viewer, but in practice we
    // never issue this query of this type on behalf of another user and are
    // unlikely to do so in the future. This entire method should be refactored
    // into a Query class, however, and then we should use a proper viewer.

    // The user can audit on their own behalf.
    $phids[$user->getPHID()] = true;

    $owned_packages = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($user)
      ->withAuthorityPHIDs(array($user->getPHID()))
      ->execute();
    foreach ($owned_packages as $package) {
      $phids[$package->getPHID()] = true;
    }

    // The user can audit on behalf of all projects they are a member of.
    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withMemberPHIDs(array($user->getPHID()))
      ->execute();
    foreach ($projects as $project) {
      $phids[$project->getPHID()] = true;
    }

    return array_keys($phids);
  }

  public static function getMailThreading(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    return array(
      'diffusion-audit-'.$commit->getPHID(),
      pht(
        'Commit %s',
        'r'.$repository->getCallsign().$commit->getCommitIdentifier()),
    );
  }

}
