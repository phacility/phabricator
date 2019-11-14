<?php

final class PhabricatorRepositoryIdentityChangeWorker
  extends PhabricatorWorker {

  protected function doWork() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $related_phids = $this->getTaskDataValue('relatedPHIDs');
    $email_addresses = $this->getTaskDataValue('emailAddresses');

    // Retain backward compatibility with older tasks which may still be in
    // queue. Previously, this worker accepted a single "userPHID". See
    // T13444. This can be removed in some future version of Phabricator once
    // these tasks have likely flushed out of queue.
    $legacy_phid = $this->getTaskDataValue('userPHID');
    if ($legacy_phid) {
      if (!is_array($related_phids)) {
        $related_phids = array();
      }
      $related_phids[] = $legacy_phid;
    }

    // Note that we may arrive in this worker after the associated objects
    // have already been destroyed, so we can't (and shouldn't) verify that
    // PHIDs correspond to real objects. If you "bin/remove destroy" a user,
    // we'll end up here with a now-bogus user PHID that we should
    // disassociate from identities.

    $identity_map = array();

    if ($related_phids) {
      $identities = id(new PhabricatorRepositoryIdentityQuery())
        ->setViewer($viewer)
        ->withRelatedPHIDs($related_phids)
        ->execute();
      $identity_map += mpull($identities, null, 'getPHID');
    }

    if ($email_addresses) {
      $identities = id(new PhabricatorRepositoryIdentityQuery())
        ->setViewer($viewer)
        ->withEmailAddresses($email_addresses)
        ->execute();
      $identity_map += mpull($identities, null, 'getPHID');
    }

    // If we didn't find any related identities, we're all set.
    if (!$identity_map) {
      return;
    }

    $identity_engine = id(new DiffusionRepositoryIdentityEngine())
      ->setViewer($viewer);
    foreach ($identity_map as $identity) {
      $identity_engine->newUpdatedIdentity($identity);
    }
  }

}
