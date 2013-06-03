<?php

final class PhabricatorMacroListController extends PhabricatorMacroController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $key;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->key = idx($data, 'key', 'active');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->key)
      ->setSearchEngine(new PhabricatorMacroSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(array $macros) {
    assert_instances_of($macros, 'PhabricatorFileImageMacro');
    $viewer = $this->getRequest()->getUser();

    $author_phids = mpull($macros, 'getAuthorPHID', 'getAuthorPHID');
    $this->loadHandles($author_phids);
    $author_handles = array_select_keys(
      $this->getLoadedHandles(),
      $author_phids);

    $pinboard = new PhabricatorPinboardView();
    foreach ($macros as $macro) {
      $file = $macro->getFile();

      $item = new PhabricatorPinboardItemView();
      if ($file) {
        $item->setImageURI($file->getThumb280x210URI());
        $item->setImageSize(280, 210);
      }

      if ($macro->getDateCreated()) {
        $datetime = phabricator_date($macro->getDateCreated(), $viewer);
        $item->appendChild(
          phutil_tag(
            'div',
            array(),
            pht('Created on %s', $datetime)));
      } else {
        // Very old macros don't have a creation date. Rendering something
        // keeps all the pins at the same height and avoids flow issues.
        $item->appendChild(
          phutil_tag(
            'div',
            array(),
            pht('Created in ages long past')));
      }

      if ($macro->getAuthorPHID()) {
        $author_handle = $this->getHandle($macro->getAuthorPHID());
        $item->appendChild(
          pht('Created by %s', $author_handle->renderLink()));
      }

      $item->setURI($this->getApplicationURI('/view/'.$macro->getID().'/'));

      $name = $macro->getName();
      if ($macro->getIsDisabled()) {
        $name = pht('%s (Disabled)', $name);
      }
      $item->setHeader($name);

      $pinboard->addItem($item);
    }

    return $pinboard;

  }
}
