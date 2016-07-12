<?php

final class PhabricatorFaxContentSource
  extends PhabricatorContentSource {

  const SOURCECONST = 'fax';

  public function getSourceName() {
    return pht('Fax');
  }

  public function getSourceDescription() {
    return pht('Content received via fax (telefacsimile).');
  }

}
