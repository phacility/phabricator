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

    // Figure out which repositories are editable. We only let you see remote
    // IPs if you have edit capability on a repository.
    $editable_repos = array();
    if ($logs) {
      $editable_repos = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withPHIDs(mpull($logs, 'getRepositoryPHID'))
        ->execute();
      $editable_repos = mpull($editable_repos, null, 'getPHID');
    }

    $rows = array();
    foreach ($logs as $log) {

      // Reveal this if it's valid and the user can edit the repository.
      $remote_addr = '-';
      if (isset($editable_repos[$log->getRepositoryPHID()])) {
        $remote_long = $log->getRemoteAddress();
        if ($remote_long) {
          $remote_addr = long2ip($remote_long);
        }
      }

      $callsign = $log->getRepository()->getCallsign();
      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI($callsign.'/'),
          ),
          $callsign),
        $this->getHandle($log->getPusherPHID())->renderLink(),
        $remote_addr,
        $log->getRemoteProtocol(),
        $log->getRefType(),
        $log->getRefName(),
        phutil_tag(
          'a',
          array(
            'href' => '/r'.$callsign.$log->getRefOld(),
          ),
          $log->getRefOldShort()),
        phutil_tag(
          'a',
          array(
            'href' => '/r'.$callsign.$log->getRefNew(),
          ),
          $log->getRefNewShort()),
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
