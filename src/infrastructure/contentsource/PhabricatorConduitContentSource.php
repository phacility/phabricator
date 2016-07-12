<?php

final class PhabricatorConduitContentSource
  extends PhabricatorContentSource {

  const SOURCECONST = 'conduit';

  public function getSourceName() {
    return pht('Conduit');
  }

  public function getSourceDescription() {
    return pht('Content from the Conduit API.');
  }

}
