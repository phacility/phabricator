<?php

final class PHUITabGroupView extends AphrontTagView {

  private $tabs = array();
  private $selectedTab;
  private $vertical;

  private $hideSingleTab;

  protected function canAppendChild() {
    return false;
  }

  public function setVertical($vertical) {
    $this->vertical = $vertical;
    return $this;
  }

  public function getVertical() {
    return $this->vertical;
  }

  public function setHideSingleTab($hide_single_tab) {
    $this->hideSingleTab = $hide_single_tab;
    return $this;
  }

  public function getHideSingleTab() {
    return $this->hideSingleTab;
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

  public function selectTab($key) {
    if (empty($this->tabs[$key])) {
      throw new Exception(
        pht(
          'Unable to select tab ("%s") which does not exist.',
          $key));
    }

    $this->selectedTab = $key;

    return $this;
  }

  public function getSelectedTabKey() {
    if (!$this->tabs) {
      return null;
    }

    if ($this->selectedTab !== null) {
      return $this->selectedTab;
    }

    return head($this->tabs)->getKey();
  }

  protected function getTagAttributes() {
    $tab_map = mpull($this->tabs, 'getContentID', 'getKey');

    $classes = array();
    if ($this->getVertical()) {
      $classes[] = 'phui-tab-group-view-vertical';
    }

    return array(
      'class' => $classes,
      'sigil' => 'phui-tab-group-view',
      'meta' => array(
        'tabMap' => $tab_map,
      ),
    );
  }

  protected function getTagContent() {
    Javelin::initBehavior('phui-tab-group');

    $tabs = new PHUIListView();

    if ($this->getVertical()) {
      $tabs->setType(PHUIListView::NAVBAR_VERTICAL);
    } else {
      $tabs->setType(PHUIListView::NAVBAR_LIST);
    }

    $content = array();

    $selected_tab = $this->getSelectedTabKey();
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

    if ($this->hideSingleTab && (count($this->tabs) == 1)) {
      $tabs = null;
    }

    if ($tabs && $this->getVertical()) {
      $content = phutil_tag(
        'table',
        array(
          'style' => 'width: 100%',
        ),
        phutil_tag(
          'tbody',
          array(),
          phutil_tag(
            'tr',
            array(),
            array(
              phutil_tag(
                'td',
                array(
                  'class' => 'phui-tab-group-view-tab-column',
                ),
                $tabs),
              phutil_tag(
                'td',
                array(),
                $content),
            ))));
      $tabs = null;
    }

    return array(
      $tabs,
      $content,
    );
  }

}
