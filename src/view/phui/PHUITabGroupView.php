<?php

final class PHUITabGroupView extends AphrontTagView {

  private $tabs = array();

  protected function canAppendChild() {
    return false;
  }

  public function addTab(PHUITabView $tab) {
    $key = $tab->getKey();
    $tab->lockKey();

    if (isset($this->tabs[$key])) {
      throw new Exception(
        pht(
          'Each tab in a tab group must have a unique key; attempting to add '.
          'a second tab with a duplicate key ("%s").',
          $key));
    }

    $this->tabs[$key] = $tab;

    return $this;
  }

  public function getSelectedTab() {
    if (!$this->tabs) {
      return null;
    }

    return head($this->tabs)->getKey();
  }

  protected function getTagAttributes() {
    $tab_map = mpull($this->tabs, 'getContentID', 'getKey');

    return array(
      'sigil' => 'phui-object-box',
      'meta' => array(
        'tabMap' => $tab_map,
      ),
    );
  }

  protected function getTagContent() {
    Javelin::initBehavior('phui-object-box-tabs');

    $tabs = id(new PHUIListView())
      ->setType(PHUIListView::NAVBAR_LIST);
    $content = array();

    $selected_tab = $this->getSelectedTab();
    foreach ($this->tabs as $tab) {
      $item = $tab->newMenuItem();
      $tab_key = $tab->getKey();

      if ($tab_key == $selected_tab) {
        $item->setSelected(true);
        $style = null;
      } else {
        $style = 'display: none;';
      }

      $tabs->addMenuItem($item);

      $content[] = javelin_tag(
        'div',
        array(
          'style' => $style,
          'id' => $tab->getContentID(),
        ),
        $tab);
    }

    return array(
      $tabs,
      $content,
    );
  }

}
