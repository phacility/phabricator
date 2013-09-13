<?php

final class PhabricatorFileListController extends PhabricatorFileController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $key;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->key = idx($data, 'key');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->key)
      ->setSearchEngine(new PhabricatorFileSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $files,
    PhabricatorSavedQuery $query) {

    assert_instances_of($files, 'PhabricatorFile');

    $request = $this->getRequest();
    $user = $request->getUser();

    $highlighted_ids = $request->getStrList('h');
    $this->loadHandles(mpull($files, 'getAuthorPHID'));

    $request = $this->getRequest();
    $user = $request->getUser();

    $highlighted_ids = array_fill_keys($highlighted_ids, true);

    $list_view = id(new PHUIObjectItemListView())
      ->setUser($user);

    foreach ($files as $file) {
      $id = $file->getID();
      $phid = $file->getPHID();
      $name = $file->getName();
      $file_uri = $this->getApplicationURI("/info/{$phid}/");

      $date_created = phabricator_date($file->getDateCreated(), $user);
      $author_phid = $file->getAuthorPHID();
      if ($author_phid) {
        $author_link = $this->getHandle($author_phid)->renderLink();
        $uploaded = pht('Uploaded by %s on %s', $author_link, $date_created);
      } else {
        $uploaded = pht('Uploaded on %s', $date_created);
      }

      $item = id(new PHUIObjectItemView())
        ->setObject($file)
        ->setObjectName("F{$id}")
        ->setHeader($name)
        ->setHref($file_uri)
        ->addAttribute($uploaded)
        ->addIcon('none', phabricator_format_bytes($file->getByteSize()));

      $ttl = $file->getTTL();
      if ($ttl !== null) {
        $item->addIcon('blame', pht('Temporary'));
      }

      if (isset($highlighted_ids[$id])) {
        $item->setEffect('highlighted');
      }

      $list_view->addItem($item);
    }

    $list_view->appendChild(new PhabricatorGlobalUploadTargetView());

    return $list_view;
  }

}
