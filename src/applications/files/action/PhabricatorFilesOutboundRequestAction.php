<?php

final class PhabricatorFilesOutboundRequestAction
  extends PhabricatorSystemAction {

  const TYPECONST = 'files.outbound';

  public function getScoreThreshold() {
    return 60 / phutil_units('1 hour in seconds');
  }

  public function getLimitExplanation() {
    return pht(
      'You have initiated too many outbound requests to fetch remote URIs '.
      'recently.');
  }

}
