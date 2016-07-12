<?php

final class PhabricatorDaemonContentSource
  extends PhabricatorContentSource {

  const SOURCECONST = 'daemon';

  public function getSourceName() {
    return pht('Daemon');
  }

  public function getSourceDescription() {
    return pht('Updates from background processing in daemons.');
  }

}
