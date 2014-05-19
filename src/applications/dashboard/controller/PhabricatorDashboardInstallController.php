<?php

final class PhabricatorDashboardInstallController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }
    $dashboard_phid = $dashboard->getPHID();

    $object_phid = $request->getStr('objectPHID', $viewer->getPHID());
    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withPHIDs(array($object_phid))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $installer_phid = $viewer->getPHID();
    $application_class = $request->getStr(
      'applicationClass',
      'PhabricatorApplicationHome');

    $handles = $this->loadHandles(array(
      $object_phid,
      $installer_phid));

    if ($request->isFormPost()) {
      $dashboard_install = id(new PhabricatorDashboardInstall())
        ->loadOneWhere(
          'objectPHID = %s AND applicationClass = %s',
          $object_phid,
          $application_class);
      if (!$dashboard_install) {
        $dashboard_install = id(new PhabricatorDashboardInstall())
          ->setObjectPHID($object_phid)
          ->setApplicationClass($application_class);
      }
      $dashboard_install
        ->setInstallerPHID($installer_phid)
        ->setDashboardPHID($dashboard_phid)
        ->save();
      return id(new AphrontRedirectResponse())
        ->setURI($this->getRedirectURI($application_class, $object_phid));
    }

    $body = $this->getBodyContent(
      $application_class,
      $object_phid,
      $installer_phid);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($body);

    return $this->newDialog()
      ->setTitle(pht('Install Dashboard'))
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($this->getCancelURI(
        $application_class, $object_phid))
      ->addSubmitButton(pht('Install Dashboard'));
  }

  private function getBodyContent(
    $application_class,
    $object_phid,
    $installer_phid) {

    $body = array();
    switch ($application_class) {
      case 'PhabricatorApplicationHome':
        if ($installer_phid == $object_phid) {
          $body[] = phutil_tag(
            'p',
            array(),
            pht(
              'Are you sure you want to install this dashboard as your '.
              'home page?'));
          $body[] = phutil_tag(
            'p',
            array(),
            pht(
              'You will be re-directed to your spiffy new home page if you '.
              'choose to install this dashboard.'));
        } else {
          $body[] = phutil_tag(
            'p',
            array(),
            pht(
              'Are you sure you want to install this dashboard as the home '.
              'page for %s?',
              $this->getHandle($object_phid)->getName()));
        }
        break;
    }
    return $body;
  }

  private function getCancelURI($application_class, $object_phid) {
    $uri = null;
    switch ($application_class) {
      case 'PhabricatorApplicationHome':
        $uri = '/dashboard/view/'.$this->id.'/';
        break;
    }
    return $uri;
  }

  private function getRedirectURI($application_class, $object_phid) {
    $uri = null;
    switch ($application_class) {
      case 'PhabricatorApplicationHome':
        $uri = '/';
        break;
    }
    return $uri;
  }

}
