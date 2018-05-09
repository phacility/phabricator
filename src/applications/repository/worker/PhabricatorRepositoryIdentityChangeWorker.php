<?php

final class PhabricatorRepositoryIdentityChangeWorker
extends PhabricatorWorker {

  protected function doWork() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $task_data = $this->getTaskData();
    $user_phid = idx($task_data, 'userPHID');

    $user = id(new PhabricatorPeopleQuery())
      ->withPHIDs(array($user_phid))
      ->setViewer($viewer)
      ->executeOne();

    $emails = id(new PhabricatorUserEmail())->loadAllWhere(
      'userPHID = %s ORDER BY address',
      $user->getPHID());

    foreach ($emails as $email) {
      $identities = id(new PhabricatorRepositoryIdentityQuery())
        ->setViewer($viewer)
        ->withEmailAddress($email->getAddress())
        ->execute();

      foreach ($identities as $identity) {
        $identity->setAutomaticGuessedUserPHID($user->getPHID())
          ->save();
      }
    }
  }

}
