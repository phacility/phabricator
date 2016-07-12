<?php

final class HarbormasterHostArtifact
  extends HarbormasterDrydockLeaseArtifact {

  const ARTIFACTCONST = 'host';

  public function getArtifactTypeName() {
    return pht('Drydock Host');
  }

  public function getArtifactTypeDescription() {
    return pht('References a host lease from Drydock.');
  }

}
