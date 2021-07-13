<?php


class EmailAPIAuthorization {
  /**
   * @throws ConduitException
   */
  public static function assert(PhabricatorUser $user) {
    $emailProject = (new PhabricatorProjectQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->needMembers(true)
      ->withNames(["mozilla-phabricator-emails"])
      ->executeOne();

    if ($emailProject && in_array($user->getPHID(), $emailProject->getMemberPHIDs())) {
      return;
    }

    if (PhabricatorEnv::getEnvConfig('bugzilla.url') == "http://bmo.test") {
      // When running in the local development environment, allow using the API as any user
      return;
    }

    throw (new ConduitException('ERR-INVALID-AUTH'))
      ->setErrorDescription('Must be part of the "mozilla-phabricator-emails" security group');
  }
}