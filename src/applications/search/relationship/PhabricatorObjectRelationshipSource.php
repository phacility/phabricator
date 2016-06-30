<?php

abstract class PhabricatorObjectRelationshipSource extends Phobject {

  private $viewer;
  private $selectedFilter;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  abstract public function isEnabledForObject($object);
  abstract public function getResultPHIDTypes();

  protected function getDefaultFilter() {
    return 'created';
  }

  final public function setSelectedFilter($selected_filter) {
    $this->selectedFilter = $selected_filter;
    return $this;
  }

  final public function getSelectedFilter() {
    if ($this->selectedFilter === null) {
      return $this->getDefaultFilter();
    }

    return $this->selectedFilter;
  }

  public function getFilters() {
    // TODO: These are hard-coded for now, and all of this will probably be
    // rewritten when we move to ApplicationSearch.
    return array(
      'assigned' => pht('Assigned to Me'),
      'created' => pht('Created By Me'),
      'open' => pht('All Open Objects'),
      'all' => pht('All Objects'),
    );
  }

}
