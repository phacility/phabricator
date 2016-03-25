<?php

final class PhabricatorWebContentSource
  extends PhabricatorContentSource {

  const SOURCECONST = 'web';

  public function getSourceName() {
    return pht('Web');
  }

  public function getSourceDescription() {
    return pht('Content created from the web UI.');
  }

}
