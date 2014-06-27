<?php

final class PhabricatorPeopleProfileEditController
  extends PhabricatorPeopleController {

  private $id;

  public function shouldRequireAdmin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $profile_uri = '/p/'.$user->getUsername().'/';

    $field_list = PhabricatorCustomField::getObjectFields(
      $user,
      PhabricatorCustomField::ROLE_EDIT);
    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($user);

    $validation_exception = null;
    if ($request->isFormPost()) {
      $xactions = $field_list->buildFieldTransactionsFromRequest(
        new PhabricatorUserTransaction(),
        $request);

      $editor = id(new PhabricatorUserProfileEditor())
        ->setActor($viewer)
        ->setContentSource(
          PhabricatorContentSource::newFromRequest($request))
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($user, $xactions);
        return id(new AphrontRedirectResponse())->setURI($profile_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }

    $title = pht('Edit Profile');
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($user->getUsername(), $profile_uri);
    $crumbs->addTextCrumb($title);

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $field_list->appendFieldsToForm($form);

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($profile_uri)
          ->setValue(pht('Save Profile')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edit Profile'))
      ->setValidationException($validation_exception)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $title,
      ));
  }
}
