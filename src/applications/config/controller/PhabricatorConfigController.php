<?php

abstract class PhabricatorConfigController extends PhabricatorController {

  public function shouldRequireAdmin() {
    return true;
  }

  public function buildSideNavView($filter = null, $for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    $nav->addFilter('/', pht('Configuration'));
    $nav->addFilter('issue/', pht('Setup Issues'));

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(null, true)->getMenu();
  }

  /**
   * Properly format a JSON value.
   *
   * @param wild Any value, but should be a raw value, not a string of JSON.
   * @return string
   */
  public function prettyPrintJSON($value) {
    // Check not only that it's an array, but that it's an "unnatural" array
    // meaning that the keys aren't 0 -> size_of_array.
    if (is_array($value) && array_keys($value) != range(0, count($value) - 1)) {
      return id(new PhutilJSON())->encodeFormatted($value);
    } else {
      return json_encode($value);
    }
  }

}
