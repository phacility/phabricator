<?php

final class PhabricatorUnknownContentSource
  extends PhabricatorContentSource {

  const SOURCECONST = 'unknown';

  public function getSourceName() {
    $source = $this->getSource();
    if ($source !== null && strlen($source)) {
      return pht('Unknown ("%s")', $source);
    } else {
      return pht('Unknown');
    }
  }

  public function getSourceDescription() {
    return pht('Content with no known source.');
  }

}
