<?php

abstract class ReleephUserView extends AphrontView {

  /**
   * This function should bulk load everything you need to render all the given
   * user phids.
   *
   * Many parts of Releeph load users for rendering.  Accordingly, this
   * function will be called multiple times for each part of the UI that
   * renders users, so you should accumulate your results on each call.
   *
   * You should also implement render() (from AphrontView) to render each
   * user's PHID.
   */
  protected function loadInner(array $phids) {
    // This is a hook!
  }

  final public static function getNewInstance() {
    $key = 'releeph.user-view';
    $class = PhabricatorEnv::getEnvConfig($key);
    return newv($class, array());
  }

  private static $handles = array();
  private static $seen = array();

  final public function load(array $phids) {
    $todo = array();

    foreach ($phids as $key => $phid) {
      if (!idx(self::$seen, $phid)) {
        $todo[$key] = $phid;
        self::$seen[$phid] = true;
      }
    }

    if ($todo) {
      self::$handles = array_merge(
        self::$handles,
        id(new PhabricatorObjectHandleData($todo))
          ->setViewer($this->getUser())
          ->loadHandles());
      $this->loadInner($todo);
    }
  }

  private $phid;
  private $releephProject;

  final public function setRenderUserPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  final public function setReleephProject(ReleephProject $project) {
    $this->releephProject = $project;
    return $this;
  }

  final protected function getRenderUserPHID() {
    return $this->phid;
  }

  final protected function getReleephProject() {
    return $this->releephProject;
  }

  final protected function getHandle() {
    return self::$handles[$this->phid];
  }

}
