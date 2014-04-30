<?php

final class PhabricatorDashboardPanelCreateController
  extends PhabricatorDashboardController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $types = PhabricatorDashboardPanelType::getAllPanelTypes();

    $v_type = null;
    $errors = array();
    if ($request->isFormPost()) {
      $v_type = $request->getStr('type');
      if (!isset($types[$v_type])) {
        $errors[] = pht('You must select a type of panel to create.');
      }

      if (!$errors) {
        return id(new AphrontRedirectResponse())->setURI(
          $this->getApplicationURI('panel/edit/?type='.$v_type));
      }
    }

    $cancel_uri = $this->getApplicationURI('panel/');

    if (!$v_type) {
      $v_type = key($types);
    }

    $panel_types = id(new AphrontFormRadioButtonControl())
      ->setName('type')
      ->setValue($v_type);

    foreach ($types as $key => $type) {
      $panel_types->addButton(
        $key,
        $type->getPanelTypeName(),
        $type->getPanelTypeDescription());
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($panel_types)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Continue'))
          ->addCancelButton($cancel_uri));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Panels'),
      $this->getApplicationURI('panel/'));
    $crumbs->addTextCrumb(pht('New Panel'));

    $title = pht('Create Dashboard Panel');

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
