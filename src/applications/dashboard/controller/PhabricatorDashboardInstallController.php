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
    switch ($object_phid) {
      case PhabricatorHomeApplication::DASHBOARD_DEFAULT:
        if (!$viewer->getIsAdmin()) {
          return new Aphront404Response();
        }
        break;
      default:
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
        break;
    }

    $installer_phid = $viewer->getPHID();
    $application_class = $request->getStr(
      'applicationClass',
      'PhabricatorHomeApplication');

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

    $dialog = $this->newDialog()
      ->setTitle(pht('Install Dashboard'))
      ->addHiddenInput('objectPHID', $object_phid)
      ->addCancelButton($this->getCancelURI($application_class, $object_phid))
      ->addSubmitButton(pht('Install Dashboard'));

    switch ($application_class) {
      case 'PhabricatorHomeApplication':
        if ($viewer->getPHID() == $object_phid) {
          if ($viewer->getIsAdmin()) {
            $dialog->setWidth(AphrontDialogView::WIDTH_FORM);

            $form = id(new AphrontFormView())
              ->setUser($viewer)
              ->appendRemarkupInstructions(
                pht('Choose where to install this dashboard.'))
              ->appendChild(
                id(new AphrontFormRadioButtonControl())
                  ->setName('objectPHID')
                  ->setValue(PhabricatorHomeApplication::DASHBOARD_DEFAULT)
                  ->addButton(
                    PhabricatorHomeApplication::DASHBOARD_DEFAULT,
                    pht('Default Dashboard for All Users'),
                    pht(
                      'Install this dashboard as the global default dashboard '.
                      'for all users. Users can install a personal dashboard '.
                      'to replace it. All users who have not configured '.
                      'a personal dashboard will be affected by this change.'))
                  ->addButton(
                    $viewer->getPHID(),
                    pht('Personal Home Page Dashboard'),
                    pht(
                      'Install this dashboard as your personal home page '.
                      'dashboard. Only you will be affected by this change.')));

            $dialog->appendChild($form->buildLayoutView());
          } else {
            $dialog->appendParagraph(
              pht('Install this dashboard on your home page?'));
          }
        } else {
          $dialog->appendParagraph(
            pht(
              'Install this dashboard as the home page dashboard for %s?',
              phutil_tag(
                'strong',
                array(),
                $viewer->renderHandle($object_phid))));
        }
        break;
      default:
        throw new Exception(
          pht(
            'Unknown dashboard application class "%s"!',
            $application_class));
    }

    return $dialog;
  }

  private function getCancelURI($application_class, $object_phid) {
    $uri = null;
    switch ($application_class) {
      case 'PhabricatorHomeApplication':
        $uri = '/dashboard/view/'.$this->id.'/';
        break;
    }
    return $uri;
  }

  private function getRedirectURI($application_class, $object_phid) {
    $uri = null;
    switch ($application_class) {
      case 'PhabricatorHomeApplication':
        $uri = '/';
        break;
    }
    return $uri;
  }

}
