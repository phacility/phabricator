<?php

final class PhabricatorStringMailStamp
  extends PhabricatorMailStamp {

  const STAMPTYPE = 'string';

  public function renderStamps($value) {
    if (!strlen($value)) {
      return null;
    }

    return $this->renderStamp($this->getKey(), $value);
  }

}
