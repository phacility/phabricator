<?php

final class HarbormasterHostArtifact extends HarbormasterArtifact {

  const ARTIFACTCONST = 'host';

  public function getArtifactTypeName() {
    return pht('Drydock Host');
  }

  public function getArtifactTypeDescription() {
    return pht('References a host lease from Drydock.');
  }


  public function getArtifactParameterSpecification() {
    return array(
      'drydockLeasePHID' => 'string',
    );
  }

  public function getArtifactParameterDescriptions() {
    return array(
      'drydockLeasePHID' => pht(
        'Drydock host lease to create an artifact from.'),
    );
  }

  public function getArtifactDataExample() {
    return array(
      'drydockLeasePHID' => 'PHID-DRYL-abcdefghijklmnopqrst',
    );
  }

  public function renderArtifactSummary(PhabricatorUser $viewer) {
    $artifact = $this->getBuildArtifact();
    $file_phid = $artifact->getProperty('drydockLeasePHID');
    return $viewer->renderHandle($file_phid);
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
    $resource = $lease->getResource();
    $blueprint = $resource->getBlueprint();

    if ($lease->isActive()) {
      $blueprint->releaseLease($resource, $lease);
    }
  }


}
