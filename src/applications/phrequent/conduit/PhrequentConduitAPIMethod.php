<?php

abstract class PhrequentConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorPhrequentApplication');
  }

}
