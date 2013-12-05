<?php

final class DiffusionPushLogListController extends DiffusionController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorRepositoryPushLogSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $logs,
    PhabricatorSavedQuery $query) {
    $viewer = $this->getRequest()->getUser();

    $this->loadHandles(mpull($logs, 'getPusherPHID'));

    $rows = array();
    foreach ($logs as $log) {
      $callsign = $log->getRepository()->getCallsign();
      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI($callsign.'/'),
          ),
          $callsign),
        $this->getHandle($log->getPusherPHID())->renderLink(),
        $log->getRemoteAddress()
          ? long2ip($log->getRemoteAddress())
          : null,
        $log->getRemoteProtocol(),
        $log->getRefType(),
        $log->getRefName(),
        $log->getRefOldShort(),
        $log->getRefNewShort(),
        phabricator_datetime($log->getEpoch(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Repository'),
          pht('Pusher'),
          pht('From'),
          pht('Via'),
          pht('Type'),
          pht('Name'),
          pht('Old'),
          pht('New'),
          pht('Date'),
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          '',
          '',
          '',
          'wide',
          'n',
          'n',
          'date',
        ));

    $box = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->appendChild($table);

    return $box;
  }

  public function buildSideNavView($for_app = false) {
    $viewer = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorRepositoryPushLogSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

}
