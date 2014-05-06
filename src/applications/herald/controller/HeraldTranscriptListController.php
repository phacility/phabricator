<?php

final class HeraldTranscriptListController extends HeraldController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('new', pht('Create Rule'));
    }

    id(new HeraldTranscriptSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Transcripts'),
      $this->getApplicationURI('transcript/'));
    return $crumbs;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new HeraldTranscriptSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }


  public function renderResultsList(
    array $transcripts,
    PhabricatorSavedQuery $query) {
    assert_instances_of($transcripts, 'HeraldTranscript');

    $viewer = $this->getRequest()->getUser();

    // Render the table.
    $handles = array();
    if ($transcripts) {
      $phids = mpull($transcripts, 'getObjectPHID', 'getObjectPHID');
      $handles = $this->loadViewerHandles($phids);
    }

    $list = new PHUIObjectItemListView();
    $list->setCards(true);
    foreach ($transcripts as $xscript) {
      $view_href = phutil_tag(
        'a',
        array(
          'href' => '/herald/transcript/'.$xscript->getID().'/',
        ),
        pht('View Full Transcript'));

      $item = new PHUIObjectItemView();
      $item->setObjectName($xscript->getID());
      $item->setHeader($view_href);
      if ($xscript->getDryRun()) {
        $item->addAttribute(pht('Dry Run'));
      }
      $item->addAttribute($handles[$xscript->getObjectPHID()]->renderLink());
      $item->addAttribute(
        number_format((int)(1000 * $xscript->getDuration())).' ms');
      $item->addIcon(
        'none',
        phabricator_datetime($xscript->getTime(), $viewer));

      $list->addItem($item);
    }

    return $list;

  }

}
