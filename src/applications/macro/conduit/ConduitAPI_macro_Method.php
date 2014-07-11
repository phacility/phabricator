<?php

abstract class ConduitAPI_macro_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationMacro');
  }

}
