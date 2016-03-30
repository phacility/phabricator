<?php

final class NuanceContentSource
  extends PhabricatorContentSource {

  const SOURCECONST = 'nuance';

  public function getSourceName() {
    return pht('Nuance');
  }

  public function getSourceDescription() {
    return pht('Content imported via Nuance.');
  }

}
