<?php

final class HarbormasterWorkingCopyArtifact
  extends HarbormasterDrydockLeaseArtifact {

  const ARTIFACTCONST = 'working-copy';

  public function getArtifactTypeName() {
    return pht('Drydock Working Copy');
  }

  public function getArtifactTypeDescription() {
    return pht('References a working copy lease from Drydock.');
  }

}
