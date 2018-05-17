<?php

final class PhabricatorBoolMailStamp
  extends PhabricatorMailStamp {

  const STAMPTYPE = 'bool';

  public function renderStamps($value) {
    if (!$value) {
      return null;
    }

    return $this->renderStamp($this->getKey());
  }

}
