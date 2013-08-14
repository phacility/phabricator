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

    $fields = PhabricatorCustomField::getObjectFields(
      $user,
      PhabricatorCustomField::ROLE_EDIT);
    $field_list = new PhabricatorCustomFieldList($fields);

    if ($request->isFormPost()) {
      $xactions = array();
      foreach ($fields as $field) {
        $field->readValueFromRequest($request);
        $xactions[] = id(new PhabricatorUserTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_CUSTOMFIELD)
          ->setMetadataValue('customfield:key', $field->getFieldKey())
          ->setNewValue($field->getNewValueForApplicationTransactions());
      }

      $editor = id(new PhabricatorUserProfileEditor())
        ->setActor($viewer)
        ->setContentSource(
          PhabricatorContentSource::newFromRequest($request))
        ->setContinueOnNoEffect(true);

      $editor->applyTransactions($user, $xactions);

      return id(new AphrontRedirectResponse())->setURI($profile_uri);
    } else {
      $field_list->readFieldsFromStorage($user);
    }

    $title = pht('Edit Profile');
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($user->getUsername())
        ->setHref($profile_uri));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title));

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $field_list->appendFieldsToForm($form);

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($profile_uri)
          ->setValue(pht('Save Profile')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form,
      ),
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }
}
