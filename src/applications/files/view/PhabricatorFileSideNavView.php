<?php

final class PhabricatorFileSideNavView extends AphrontView {
  private $selectedFilter;

  public function setSelectedFilter($selected_filter) {
    $this->selectedFilter = $selected_filter;
    return $this;
  }
  private function getSelectedFilter() {
    return $this->selectedFilter;
  }

  public function render() {
    $selected_filter = $this->getSelectedFilter();
    if (!$selected_filter) {
      throw new Exception("Call setFilter() before render()!");
    }

    $filters = array(
      'Files' => array(),
      'upload' => array(
        'name' => 'Upload File',
      ),
      'my' => array(
        'name' => 'My Files',
      ),
      'all' => array(
        'name' => 'All Files',
      ),
    );

    $side_nav = new AphrontSideNavFilterView();
    $side_nav->setBaseURI(new PhutilURI('/file/filter/'));
    foreach ($filters as $filter_key => $filter) {
      // more of a label than a filter
      if (empty($filter)) {
        $side_nav->addLabel($filter_key);
        continue;
      }
      $side_nav->addFilter($filter_key, $filter['name']);
    }
    $side_nav->selectFilter($selected_filter, null);
    $side_nav->appendChild($this->renderChildren());

    return $side_nav->render();
  }
}
