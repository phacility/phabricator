<?php

final class PhabricatorFlagEditController extends PhabricatorFlagController {

  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phid = $this->phid;
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($phid))
      ->executeOne();

    if (!$handle->isComplete()) {
      return new Aphront404Response();
    }

    $flag = PhabricatorFlagQuery::loadUserFlag($user, $phid);

    if (!$flag) {
      $flag = new PhabricatorFlag();
      $flag->setOwnerPHID($user->getPHID());
      $flag->setType($handle->getType());
      $flag->setObjectPHID($handle->getPHID());
      $flag->setReasonPHID($user->getPHID());
    }

    if ($request->isDialogFormPost()) {
      $flag->setColor($request->getInt('color'));
      $flag->setNote($request->getStr('note'));
      $flag->save();

      return id(new AphrontReloadResponse())->setURI('/flag/');
    }

    $type_name = $handle->getTypeName();

    $dialog = new AphrontDialogView();
    $dialog->setUser($user);

    $dialog->setTitle(pht('Flag %s', $type_name));

    require_celerity_resource('phabricator-flag-css');

    $form = new PHUIFormLayoutView();

    $is_new = !$flag->getID();

    if ($is_new) {
      $form
        ->appendChild(hsprintf(
          '<p>%s</p><br />',
          pht('You can flag this %s if you want to remember to look '.
            'at it later.',
            $type_name)));
    }

    $radio = new AphrontFormRadioButtonControl();
    foreach (PhabricatorFlagColor::getColorNameMap() as $color => $text) {
      $class = 'phabricator-flag-radio phabricator-flag-color-'.$color;
      $radio->addButton($color, $text, '', $class);
    }

    $form
      ->appendChild(
        $radio
          ->setName('color')
          ->setLabel(pht('Flag Color'))
          ->setValue($flag->getColor()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setName('note')
          ->setLabel(pht('Note'))
          ->setValue($flag->getNote()));

    $dialog->appendChild($form);

    $dialog->addCancelButton($handle->getURI());
    $dialog->addSubmitButton(
      $is_new ? pht('Create Flag') : pht('Save'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
