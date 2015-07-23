<?php

final class PhabricatorActionListView extends AphrontView {

  private $actions = array();
  private $object;
  private $objectURI;
  private $id = null;

  public function setObject(PhabricatorLiskDAO $object) {
    $this->object = $object;
    return $this;
  }

  public function setObjectURI($uri) {
    $this->objectURI = $uri;
    return $this;
  }

  public function addAction(PhabricatorActionView $view) {
    $this->actions[] = $view;
    return $this;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function render() {
    if (!$this->user) {
      throw new PhutilInvalidStateException('setUser');
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS,
      array(
        'object'  => $this->object,
        'actions' => $this->actions,
      ));
    $event->setUser($this->user);
    PhutilEventEngine::dispatchEvent($event);

    $actions = $event->getValue('actions');
    if (!$actions) {
      return null;
    }

    foreach ($actions as $action) {
      $action->setObjectURI($this->objectURI);
      $action->setUser($this->user);
    }

    require_celerity_resource('phabricator-action-list-view-css');

    return phutil_tag(
      'ul',
      array(
        'class' => 'phabricator-action-list-view',
        'id' => $this->id,
      ),
      $actions);
  }


}
