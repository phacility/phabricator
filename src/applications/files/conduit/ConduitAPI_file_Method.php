<?php

abstract class ConduitAPI_file_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationFiles');
  }

}
