<?php

abstract class PhabricatorSMSWorker
  extends PhabricatorWorker {

  public function renderForDisplay(PhabricatorUser $viewer) {
    // This data has some sensitive stuff, so don't show it.
    return null;
  }

}
