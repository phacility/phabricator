<?php

final class PhabricatorPHIDMailStamp
  extends PhabricatorMailStamp {

  const STAMPTYPE = 'phid';

  public function renderStamps($value) {
    if ($value === null) {
      return null;
    }

    $value = (array)$value;
    if (!$value) {
      return null;
    }

    $viewer = $this->getViewer();
    $handles = $viewer->loadHandles($value);

    $results = array();
    foreach ($value as $phid) {
      $handle = $handles[$phid];

      $mail_name = $handle->getMailStampName();
      if ($mail_name === null) {
        $mail_name = $handle->getPHID();
      }

      $results[] = $this->renderStamp($this->getKey(), $mail_name);
    }

    return $results;
  }

}
