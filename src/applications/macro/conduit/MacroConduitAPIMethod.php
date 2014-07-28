<?php

abstract class MacroConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorMacroApplication');
  }

}
