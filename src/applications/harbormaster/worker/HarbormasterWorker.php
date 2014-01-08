<?php

abstract class HarbormasterWorker extends PhabricatorWorker {

  public function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

}
