<?php

abstract class ConduitAPI_phrequent_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationPhrequent');
  }


}
