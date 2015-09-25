<?php

abstract class HarbormasterDrydockLeaseArtifact
  extends HarbormasterArtifact {

  public function getArtifactParameterSpecification() {
    return array(
      'drydockLeasePHID' => 'string',
    );
  }

  public function getArtifactParameterDescriptions() {
    return array(
      'drydockLeasePHID' => pht(
        'Drydock working copy lease to create an artifact from.'),
    );
  }

  public function getArtifactDataExample() {
    return array(
      'drydockLeasePHID' => 'PHID-DRYL-abcdefghijklmnopqrst',
    );
  }

  public function renderArtifactSummary(PhabricatorUser $viewer) {
    $artifact = $this->getBuildArtifact();
    $lease_phid = $artifact->getProperty('drydockLeasePHID');
    return $viewer->renderHandle($lease_phid);
  }

  public function willCreateArtifact(PhabricatorUser $actor) {
    $this->loadArtifactLease($actor);
  }

  public function loadArtifactLease(PhabricatorUser $viewer) {
    $artifact = $this->getBuildArtifact();
    $lease_phid = $artifact->getProperty('drydockLeasePHID');

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($lease_phid))
      ->executeOne();
    if (!$lease) {
      throw new Exception(
        pht(
          'Drydock lease PHID "%s" does not correspond to a valid lease.',
          $lease_phid));
    }

    return $lease;
  }

  public function releaseArtifact(PhabricatorUser $actor) {
    $lease = $this->loadArtifactLease($actor);
    if (!$lease->canRelease()) {
      return;
    }

    $author_phid = $actor->getPHID();
    if (!$author_phid) {
      $author_phid = id(new PhabricatorHarbormasterApplication())->getPHID();
    }

    $command = DrydockCommand::initializeNewCommand($actor)
      ->setTargetPHID($lease->getPHID())
      ->setAuthorPHID($author_phid)
      ->setCommand(DrydockCommand::COMMAND_RELEASE)
      ->save();

    $lease->scheduleUpdate();
  }

}
