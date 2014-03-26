<?php

final class DiffusionPushEventViewController
  extends DiffusionPushLogController {

  private $id;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $event = id(new PhabricatorRepositoryPushEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needLogs(true)
      ->executeOne();
    if (!$event) {
      return new Aphront404Response();
    }

    $repository = $event->getRepository();
    $title = pht('Push %d', $event->getID());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $repository->getName(),
      $this->getApplicationURI($repository->getCallsign().'/'));
    $crumbs->addTextCrumb(
      pht('Push Logs'),
      $this->getApplicationURI(
        'pushlog/?repositories='.$repository->getMonogram()));
    $crumbs->addTextCrumb($title);

    $event_properties = $this->buildPropertyList($event);

    $detail_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->addPropertyList($event_properties);

    $commits = $this->loadCommits($event);
    $commits_table = $this->renderCommitsTable($event, $commits);

    $commits_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Pushed Commits'))
      ->appendChild($commits_table);

    $updates_table = $this->renderPushLogTable($event->getLogs());

    $update_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('All Pushed Updates'))
      ->appendChild($updates_table);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $detail_box,
        $commits_box,
        $update_box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildPropertyList(PhabricatorRepositoryPushEvent $event) {
    $viewer = $this->getRequest()->getUser();

    $this->loadHandles(array($event->getPusherPHID()));

    $view = new PHUIPropertyListView();

    $view->addProperty(
      pht('Pushed At'),
      phabricator_datetime($event->getEpoch(), $viewer));

    $view->addProperty(
      pht('Pushed By'),
      $this->getHandle($event->getPusherPHID())->renderLink());

    $view->addProperty(
      pht('Pushed Via'),
      $event->getRemoteProtocol());

    return $view;
  }

  private function loadCommits(PhabricatorRepositoryPushEvent $event) {
    $viewer = $this->getRequest()->getUser();

    $identifiers = array();
    foreach ($event->getLogs() as $log) {
      if ($log->getRefType() == PhabricatorRepositoryPushLog::REFTYPE_COMMIT) {
        $identifiers[] = $log->getRefNew();
      }
    }

    if (!$identifiers) {
      return array();
    }

    // NOTE: Commits may not have been parsed/discovered yet. We need to return
    // the identifiers no matter what. If possible, we'll also return the
    // corresponding commits.

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withRepository($event->getRepository())
      ->withIdentifiers($identifiers)
      ->execute();

    $commits = mpull($commits, null, 'getCommitIdentifier');

    $results = array();
    foreach ($identifiers as $identifier) {
      $results[$identifier] = idx($commits, $identifier);
    }

    return $results;
  }

  private function renderCommitsTable(
    PhabricatorRepositoryPushEvent $event,
    array $commits) {

    $viewer = $this->getRequest()->getUser();
    $repository = $event->getRepository();

    $rows = array();
    foreach ($commits as $identifier => $commit) {
      if ($commit) {
        $partial_import = PhabricatorRepositoryCommit::IMPORTED_MESSAGE |
                          PhabricatorRepositoryCommit::IMPORTED_CHANGE;
        if ($commit->isPartiallyImported($partial_import)) {
          $summary = AphrontTableView::renderSingleDisplayLine(
            $commit->getSummary());
        } else {
          $summary = phutil_tag('em', array(), pht('Importing...'));
        }
      } else {
        $summary = phutil_tag('em', array(), pht('Discovering...'));
      }

      $commit_name = $repository->formatCommitName($identifier);
      if ($commit) {
        $commit_name = phutil_tag(
          'a',
          array(
            'href' => '/'.$commit_name,
          ),
          $commit_name);
      }

      $rows[] = array(
        $commit_name,
        $summary,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht("This push didn't push any new commits."))
      ->setHeaders(
        array(
          pht('Commit'),
          pht('Summary'),
        ))
      ->setColumnClasses(
        array(
          'n',
          'wide',
        ));

    return $table;
  }

}
