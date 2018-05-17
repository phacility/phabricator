<?php

abstract class DrydockLogType extends Phobject {

  private $viewer;
  private $log;
  private $renderingMode = 'text';

  abstract public function getLogTypeName();
  abstract public function getLogTypeIcon(array $data);
  abstract public function renderLog(array $data);

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setLog(DrydockLog $log) {
    $this->log = $log;
    return $this;
  }

  final public function getLog() {
    return $this->log;
  }

  final public function getLogTypeConstant() {
    return $this->getPhobjectClassConstant('LOGCONST', 64);
  }

  final public static function getAllLogTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getLogTypeConstant')
      ->execute();
  }

  final public function renderLogForText($data) {
    $this->renderingMode = 'text';
    return $this->renderLog($data);
  }

  final public function renderLogForHTML($data) {
    $this->renderingMode = 'html';
    return $this->renderLog($data);
  }

  final protected function renderHandle($phid) {
    $viewer = $this->getViewer();
    $handle = $viewer->renderHandle($phid);

    if ($this->renderingMode == 'html') {
      return $handle->render();
    } else {
      return $handle->setAsText(true)->render();
    }
  }

  final protected function renderHandleList(array $phids) {
    $viewer = $this->getViewer();
    $handle_list = $viewer->renderHandleList($phids);

    if ($this->renderingMode == 'html') {
      return $handle_list->render();
    } else {
      return $handle_list->setAsText(true)->render();
    }
  }

}
