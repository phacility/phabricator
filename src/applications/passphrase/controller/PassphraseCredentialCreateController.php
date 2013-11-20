<?php

final class PassphraseCredentialCreateController extends PassphraseController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $types = PassphraseCredentialType::getAllTypes();
    $types = mpull($types, null, 'getCredentialType');
    $types = msort($types, 'getCredentialTypeName');

    $errors = array();
    $e_type = null;

    if ($request->isFormPost()) {
      $type = $request->getStr('type');
      if (empty($types[$type])) {
        $errors[] = pht('You must choose a credential type.');
        $e_type = pht('Required');
      }

      if (!$errors) {
        $uri = $this->getApplicationURI('edit/?type='.$type);
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setErrors($errors);
    }

    $types_control = id(new AphrontFormRadioButtonControl())
      ->setName('type')
      ->setLabel(pht('Credential Type'))
      ->setError($e_type);

    foreach ($types as $type) {
      $types_control->addButton(
        $type->getCredentialType(),
        $type->getCredentialTypeName(),
        $type->getCredentialTypeDescription());
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($types_control)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Continue'))
          ->addCancelButton($this->getApplicationURI()));

    $title = pht('New Credential');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Create')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create New Credential'))
      ->setFormError($error_view)
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
