<?php

final class DiffusionRepositoryIdentityEngine
  extends Phobject {

  private $viewer;
  private $sourcePHID;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setSourcePHID($source_phid) {
    $this->sourcePHID = $source_phid;
    return $this;
  }

  public function getSourcePHID() {
    if (!$this->sourcePHID) {
      throw new PhutilInvalidStateException('setSourcePHID');
    }

    return $this->sourcePHID;
  }

  public function newResolvedIdentity($raw_identity) {
    $identity = $this->loadRawIdentity($raw_identity);

    if (!$identity) {
      $identity = $this->newIdentity($raw_identity);
    }

    return $this->updateIdentity($identity);
  }

  public function newUpdatedIdentity(PhabricatorRepositoryIdentity $identity) {
    return $this->updateIdentity($identity);
  }

  private function loadRawIdentity($raw_identity) {
    $viewer = $this->getViewer();

    return id(new PhabricatorRepositoryIdentityQuery())
      ->setViewer($viewer)
      ->withIdentityNames(array($raw_identity))
      ->executeOne();
  }

  private function newIdentity($raw_identity) {
    $source_phid = $this->getSourcePHID();

    return id(new PhabricatorRepositoryIdentity())
      ->setAuthorPHID($source_phid)
      ->setIdentityName($raw_identity);
  }

  private function resolveIdentity(PhabricatorRepositoryIdentity $identity) {
    $raw_identity = $identity->getIdentityName();

    return id(new DiffusionResolveUserQuery())
      ->withName($raw_identity)
      ->execute();
  }

  private function updateIdentity(PhabricatorRepositoryIdentity $identity) {
    $resolved_phid = $this->resolveIdentity($identity);

    $identity
      ->setAutomaticGuessedUserPHID($resolved_phid)
      ->save();

    return $identity;
  }

}
