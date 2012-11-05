<?php

final class PhabricatorActionListView extends AphrontView {

  private $actions = array();
  private $object;
  private $user;

  public function setObject(PhabricatorLiskDAO $object) {
    $this->object = $object;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function addAction(PhabricatorActionView $view) {
    $this->actions[] = $view;
    return $this;
  }

  public function render() {
    if (!$this->user) {
      throw new Exception("Call setUser() before render()!");
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

    require_celerity_resource('phabricator-action-list-view-css');
    return phutil_render_tag(
      'ul',
      array(
        'class' => 'phabricator-action-list-view',
      ),
      $this->renderSingleView($actions));
  }


}
