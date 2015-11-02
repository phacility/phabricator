<?php

final class DrydockLeaseViewController extends DrydockLeaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needUnconsumedCommands(true)
      ->executeOne();
    if (!$lease) {
      return new Aphront404Response();
    }

    $id = $lease->getID();
    $lease_uri = $this->getApplicationURI("lease/{$id}/");

    $title = pht('Lease %d', $lease->getID());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    if ($lease->isReleasing()) {
      $header->setStatus('fa-exclamation-triangle', 'red', pht('Releasing'));
    }

    $actions = $this->buildActionListView($lease);
    $properties = $this->buildPropertyListView($lease, $actions);

    $log_query = id(new DrydockLogQuery())
      ->withLeasePHIDs(array($lease->getPHID()));

    $log_box = $this->buildLogBox(
      $log_query,
      $this->getApplicationURI("lease/{$id}/logs/query/all/"));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title, $lease_uri);

    $locks = $this->buildLocksTab($lease->getPHID());
    $commands = $this->buildCommandsTab($lease->getPHID());

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties, pht('Properties'))
      ->addPropertyList($locks, pht('Slot Locks'))
      ->addPropertyList($commands, pht('Commands'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $log_box,
      ),
      array(
        'title' => $title,
      ));

  }

  private function buildActionListView(DrydockLease $lease) {
    $viewer = $this->getViewer();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($lease);

    $id = $lease->getID();

    $can_release = $lease->canRelease();
    if ($lease->isReleasing()) {
      $can_release = false;
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $lease,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Release Lease'))
        ->setIcon('fa-times')
        ->setHref($this->getApplicationURI("/lease/{$id}/release/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_release || !$can_edit));

    return $view;
  }

  private function buildPropertyListView(
    DrydockLease $lease,
    PhabricatorActionListView $actions) {
    $viewer = $this->getViewer();

    $view = new PHUIPropertyListView();
    $view->setActionList($actions);

    $view->addProperty(
      pht('Status'),
      DrydockLeaseStatus::getNameForStatus($lease->getStatus()));

    $view->addProperty(
      pht('Resource Type'),
      $lease->getResourceType());

    $owner_phid = $lease->getOwnerPHID();
    if ($owner_phid) {
      $owner_display = $viewer->renderHandle($owner_phid);
    } else {
      $owner_display = phutil_tag('em', array(), pht('No Owner'));
    }
    $view->addProperty(pht('Owner'), $owner_display);

    $authorizing_phid = $lease->getAuthorizingPHID();
    if ($authorizing_phid) {
      $authorizing_display = $viewer->renderHandle($authorizing_phid);
    } else {
      $authorizing_display = phutil_tag('em', array(), pht('None'));
    }
    $view->addProperty(pht('Authorized By'), $authorizing_display);

    $resource_phid = $lease->getResourcePHID();
    if ($resource_phid) {
      $resource_display = $viewer->renderHandle($resource_phid);
    } else {
      $resource_display = phutil_tag('em', array(), pht('No Resource'));
    }
    $view->addProperty(pht('Resource'), $resource_display);

    $until = $lease->getUntil();
    if ($until) {
      $until_display = phabricator_datetime($until, $viewer);
    } else {
      $until_display = phutil_tag('em', array(), pht('Never'));
    }
    $view->addProperty(pht('Expires'), $until_display);

    $attributes = $lease->getAttributes();
    if ($attributes) {
      $view->addSectionHeader(
        pht('Attributes'), 'fa-list-ul');
      foreach ($attributes as $key => $value) {
        $view->addProperty($key, $value);
      }
    }

    return $view;
  }

}
