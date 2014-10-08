<?php

abstract class PassphraseConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorPassphraseApplication');
  }

}
