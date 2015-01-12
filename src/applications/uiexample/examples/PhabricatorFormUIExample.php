<?php

final class PhabricatorFormUIExample extends PhabricatorUIExample {

  public function getName() {
    return 'Form';
  }

  public function getDescription() {
    return hsprintf('Use <tt>AphrontFormView</tt> to render forms.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $start_time = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('start')
      ->setLabel('Start')
      ->setInitialTime(AphrontFormDateControl::TIME_START_OF_BUSINESS);

    $end_time = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('end')
      ->setLabel('End')
      ->setInitialTime(AphrontFormDateControl::TIME_END_OF_BUSINESS);

    $null_time = id(new AphrontFormDateControl())
      ->setUser($user)
      ->setName('nulltime')
      ->setLabel('Nullable')
      ->setAllowNull(true);

    if ($request->isFormPost()) {
      $start_value = $start_time->readValueFromRequest($request);
      $end_value = $end_time->readValueFromRequest($request);
      $null_value = $null_time->readValueFromRequest($request);
    }

    $divider_control = new AphrontFormDividerControl();

    $credentials = array();
    $password_control = id(new PassphraseCredentialControl())
      ->setName('credentialPHID')
      ->setLabel(pht('Password'))
      ->setCredentialType('password')
      ->setOptions($credentials);

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild($start_time)
      ->appendChild($end_time)
      ->appendChild($null_time)
      ->appendChild($divider_control)
      ->appendChild($password_control)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Submit'));

    return $form;
  }
}
