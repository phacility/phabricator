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

    // TODO: This recovers from a bug where blocking reviewers were serialized
    // incorrectly into the flat mail stamp list in the worker queue as arrays.
    // It can be removed some time after February 2018.
    foreach ($value as $key => $v) {
      if (is_array($v)) {
        unset($value[$key]);
      }
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
