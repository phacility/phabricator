<?php

final class PhabricatorOldWorldContentSource
  extends PhabricatorContentSource {

  const SOURCECONST = 'legacy';

  public function getSourceName() {
    return pht('Old World');
  }

  public function getSourceDescription() {
    return pht(
      'Content from the distant past, before content sources existed.');
  }

}
