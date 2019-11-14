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
      'userPHID = %s',
      $user->getPHID());

    $identity_engine = id(new DiffusionRepositoryIdentityEngine())
      ->setViewer($viewer);

    foreach ($emails as $email) {
      $identities = id(new PhabricatorRepositoryIdentityQuery())
        ->setViewer($viewer)
        ->withEmailAddresses(array($email->getAddress()))
        ->execute();

      foreach ($identities as $identity) {
        $identity_engine->newUpdatedIdentity($identity);
      }
    }
  }

}
