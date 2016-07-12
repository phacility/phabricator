<?php

final class PhabricatorHeraldContentSource
  extends PhabricatorContentSource {

  const SOURCECONST = 'herald';

  public function getSourceName() {
    return pht('Herald');
  }

  public function getSourceDescription() {
    return pht('Changes triggered by Herald rules.');
  }

}
