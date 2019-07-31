<?php

final class PhabricatorPeopleLogViewController
  extends PhabricatorPeopleController {

  public function shouldRequireAdmin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $log = id(new PhabricatorPeopleLogQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$log) {
      return new Aphront404Response();
    }

    $logs_uri = $this->getApplicationURI('logs/');

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Activity Logs'), $logs_uri)
      ->addTextCrumb($log->getObjectName())
      ->setBorder(true);

    $header = $this->buildHeaderView($log);
    $properties = $this->buildPropertiesView($log);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->addPropertySection(pht('Details'), $properties);

    return $this->newPage()
      ->setCrumbs($crumbs)
      ->setTitle($log->getObjectName())
      ->appendChild($view);
  }

  private function buildHeaderView(PhabricatorUserLog $log) {
    $viewer = $this->getViewer();

    $view = id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader($log->getObjectName());

    return $view;
  }

  private function buildPropertiesView(PhabricatorUserLog $log) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $type_map = PhabricatorUserLogType::getAllLogTypes();
    $type_map = mpull($type_map, 'getLogTypeName', 'getLogTypeKey');

    $action = $log->getAction();
    $type_name = idx($type_map, $action, $action);

    $view->addProperty(pht('Event Type'), $type_name);

    $view->addProperty(
      pht('Event Date'),
      phabricator_datetime($log->getDateCreated(), $viewer));

    $actor_phid = $log->getActorPHID();
    if ($actor_phid) {
      $view->addProperty(
        pht('Acting User'),
        $viewer->renderHandle($actor_phid));
    }

    $user_phid = $log->getUserPHID();
    if ($user_phid) {
      $view->addProperty(
        pht('Affected User'),
        $viewer->renderHandle($user_phid));
    }

    $remote_address = $log->getRemoteAddressForViewer($viewer);
    if ($remote_address !== null) {
      $view->addProperty(pht('Remote Address'), $remote_address);
    }

    return $view;
  }

}
