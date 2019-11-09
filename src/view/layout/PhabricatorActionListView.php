<?php

final class PhabricatorActionListView extends AphrontTagView {

  private $actions = array();
  private $object;

  public function setObject(PhabricatorLiskDAO $object) {
    $this->object = $object;
    return $this;
  }

  public function addAction(PhabricatorActionView $view) {
    $this->actions[] = $view;
    return $this;
  }

  protected function getTagName() {
    if (!$this->actions) {
      return null;
    }

    return 'ul';
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phabricator-action-list-view';
    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {
    $viewer = $this->getViewer();

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS,
      array(
        'object'  => $this->object,
        'actions' => $this->actions,
      ));
    $event->setUser($viewer);
    PhutilEventEngine::dispatchEvent($event);

    $actions = $event->getValue('actions');
    if (!$actions) {
      return null;
    }

    foreach ($actions as $action) {
      $action->setViewer($viewer);
    }

    $sort = array();
    foreach ($actions as $key => $action) {
      $sort[$key] = id(new PhutilSortVector())
        ->addInt($action->getOrder());
    }
    $sort = msortv($sort, 'getSelf');
    $actions = array_select_keys($actions, array_keys($sort));

    require_celerity_resource('phabricator-action-list-view-css');

    $items = array();
    foreach ($actions as $action) {
      foreach ($action->getItems() as $item) {
        $items[] = $item;
      }
    }

    return $items;
  }

  public function getDropdownMenuMetadata() {
    return array(
      'items' => (string)hsprintf('%s', $this),
    );
  }


}
