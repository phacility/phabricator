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

    $rows = array();
    foreach ($transcripts as $xscript) {
      $rows[] = array(
        phabricator_date($xscript->getTime(), $viewer),
        phabricator_time($xscript->getTime(), $viewer),
        $handles[$xscript->getObjectPHID()]->renderLink(),
        $xscript->getDryRun() ? pht('Yes') : '',
        number_format((int)(1000 * $xscript->getDuration())).' ms',
        phutil_tag(
          'a',
          array(
            'href' => '/herald/transcript/'.$xscript->getID().'/',
            'class' => 'button small grey',
          ),
          pht('View Transcript')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Date'),
        pht('Time'),
        pht('Object'),
        pht('Dry Run'),
        pht('Duration'),
        pht('View'),
      ));
    $table->setColumnClasses(
      array(
        '',
        'right',
        'wide wrap',
        '',
        '',
        'action',
      ));

    // Render the whole page.
    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Herald Transcripts'));
    $panel->appendChild($table);
    $panel->setNoBackground();

    return $panel;

  }

}
