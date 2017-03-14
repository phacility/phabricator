<?php

final class PhabricatorDashboardInstallController
  extends PhabricatorDashboardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }

    $cancel_uri = $this->getApplicationURI(
      'view/'.$dashboard->getID().'/');

    $home_app = new PhabricatorHomeApplication();

    $options = array();
    $options['home'] = array(
      'personal' =>
        array(
          'capability' => PhabricatorPolicyCapability::CAN_VIEW,
          'application' => $home_app,
          'name' => pht('Personal Dashboard'),
          'value' => 'personal',
          'description' => pht('Places this dashboard as a menu item on home '.
            'as a personal menu item. It will only be on your personal '.
            'home.'),
        ),
      'global' =>
        array(
          'capability' => PhabricatorPolicyCapability::CAN_EDIT,
          'application' => $home_app,
          'name' => pht('Global Dashboard'),
          'value' => 'global',
          'description' => pht('Places this dashboard as a menu item on home '.
            'as a global menu item. It will be available to all users.'),
        ),
    );


    $errors = array();
    $v_name = null;
    if ($request->isFormPost()) {
      $menuitem = new PhabricatorDashboardProfileMenuItem();
      $dashboard_phid = $dashboard->getPHID();
      $home = new PhabricatorHomeApplication();
      $v_name = $request->getStr('name');
      $v_home = $request->getStr('home');

      if ($v_home) {
        $application = $options['home'][$v_home]['application'];
        $capability = $options['home'][$v_home]['capability'];

        $can_edit_home = PhabricatorPolicyFilter::hasCapability(
          $viewer,
          $application,
          $capability);

        if (!$can_edit_home) {
          $errors[] = pht(
            'You do not have permission to install a dashboard on home.');
        }
      } else {
          $errors[] = pht(
            'You must select a destination to install this dashboard.');
      }

      $v_phid = $viewer->getPHID();
      if ($v_home == 'global') {
        $v_phid = null;
      }

      if (!$errors) {
        $install = PhabricatorProfileMenuItemConfiguration::initializeNewItem(
          $home,
          $menuitem,
          $v_phid);

        $install->setMenuItemProperty('dashboardPHID', $dashboard_phid);
        $install->setMenuItemProperty('name', $v_name);
        $install->setMenuItemOrder(1);

        $xactions = array();

        $editor = id(new PhabricatorProfileMenuEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true)
          ->setContentSourceFromRequest($request);

        $editor->applyTransactions($install, $xactions);

        $view_uri = '/home/menu/view/'.$install->getID().'/';

        return id(new AphrontRedirectResponse())->setURI($view_uri);
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Menu Label'))
          ->setName('name')
          ->setValue($v_name));

    $radio = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Home Menu'))
      ->setName('home');

    foreach ($options['home'] as $type => $option) {
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $option['application'],
        $option['capability']);
      if ($can_edit) {
        $radio->addButton(
          $option['value'],
          $option['name'],
          $option['description']);
      }
    }

    $form->appendChild($radio);

    return $this->newDialog()
      ->setTitle(pht('Install Dashboard'))
      ->setErrors($errors)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Install Dashboard'));
  }

}
