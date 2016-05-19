<?php

final class AlmanacDeviceViewController
  extends AlmanacDeviceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $name = $request->getURIData('name');

    $device = id(new AlmanacDeviceQuery())
      ->setViewer($viewer)
      ->withNames(array($name))
      ->needProperties(true)
      ->executeOne();
    if (!$device) {
      return new Aphront404Response();
    }

    $title = pht('Device %s', $device->getName());

    $curtain = $this->buildCurtain($device);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($device->getName())
      ->setPolicyObject($device)
      ->setHeaderIcon('fa-server');

    $issue = null;
    if ($device->isClusterDevice()) {
      $issue = $this->addClusterMessage(
        pht('This device is bound to a cluster service.'),
        pht(
          'This device is bound to a cluster service. You do not have '.
          'permission to manage cluster services, so the device can not '.
          'be edited.'));
    }

    $interfaces = $this->buildInterfaceList($device);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($device->getName());
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $device,
      new AlmanacDeviceTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
          $issue,
          $interfaces,
          $this->buildAlmanacPropertiesTable($device),
          $this->buildSSHKeysTable($device),
          $this->buildServicesTable($device),
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
        ));
  }

  private function buildCurtain(AlmanacDevice $device) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $device,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $device->getID();
    $edit_uri = $this->getApplicationURI("device/edit/{$id}/");

    $curtain = $this->newCurtainView($device);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Device'))
        ->setHref($edit_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $curtain;
  }

  private function buildInterfaceList(AlmanacDevice $device) {
    $viewer = $this->getViewer();
    $id = $device->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $device,
      PhabricatorPolicyCapability::CAN_EDIT);

    $interfaces = id(new AlmanacInterfaceQuery())
      ->setViewer($viewer)
      ->withDevicePHIDs(array($device->getPHID()))
      ->execute();

    $table = id(new AlmanacInterfaceTableView())
      ->setUser($viewer)
      ->setInterfaces($interfaces)
      ->setCanEdit($can_edit);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Device Interfaces'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($this->getApplicationURI("interface/edit/?deviceID={$id}"))
          ->setWorkflow(!$can_edit)
          ->setDisabled(!$can_edit)
          ->setText(pht('Add Interface'))
          ->setIcon('fa-plus'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

  private function buildSSHKeysTable(AlmanacDevice $device) {
    $viewer = $this->getViewer();
    $id = $device->getID();
    $device_phid = $device->getPHID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $device,
      PhabricatorPolicyCapability::CAN_EDIT);

    $keys = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($device_phid))
      ->withIsActive(true)
      ->execute();

    $table = id(new PhabricatorAuthSSHKeyTableView())
      ->setUser($viewer)
      ->setKeys($keys)
      ->setCanEdit($can_edit)
      ->setShowID(true)
      ->setShowTrusted(true)
      ->setNoDataString(pht('This device has no associated SSH public keys.'));

    $menu_button = PhabricatorAuthSSHKeyTableView::newKeyActionsMenu(
      $viewer,
      $device);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('SSH Public Keys'))
      ->addActionLink($menu_button);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

  private function buildServicesTable(AlmanacDevice $device) {
    $viewer = $this->getViewer();

    // NOTE: We're loading all services so we can show hidden, locked services.
    // In general, we let you know about all the things the device is bound to,
    // even if you don't have permission to see their details. This is similar
    // to exposing the existence of edges in other applications, with the
    // addition of always letting you see that locks exist.

    $services = id(new AlmanacServiceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withDevicePHIDs(array($device->getPHID()))
      ->execute();

    $handles = $viewer->loadHandles(mpull($services, 'getPHID'));

    $icon_cluster = id(new PHUIIconView())
      ->setIcon('fa-sitemap');

    $rows = array();
    foreach ($services as $service) {
      $rows[] = array(
        ($service->isClusterService()
          ? $icon_cluster
          : null),
        $handles->renderHandle($service->getPHID()),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No services are bound to this device.'))
      ->setHeaders(
        array(
          null,
          pht('Service'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Bound Services'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }


}
