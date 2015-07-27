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
      ->needProfileImage(true)
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

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $field_list->appendFieldsToForm($form);
    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($profile_uri)
          ->setValue(pht('Save Profile')));

    $allow_public = PhabricatorEnv::getEnvConfig('policy.allow-public');
    $note = null;
    if ($allow_public) {
      $note = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->appendChild(pht(
          'Information on user profiles on this install is publicly '.
          'visible.'));
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edit Profile'))
      ->setValidationException($validation_exception)
      ->setForm($form);

    if ($note) {
      $form_box->setInfoView($note);
    }

    $nav = $this->buildIconNavView($user);
    $nav->selectFilter('/');
    $nav->appendChild($form_box);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }
}
