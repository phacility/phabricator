<?php

final class PhabricatorAccountSettingsPanel extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'account';
  }

  public function getPanelName() {
    return pht('Account');
  }

  public function getPanelGroup() {
    return pht('Account Information');
  }

  public function isEditableByAdministrators() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $user = $this->getUser();
    $username = $user->getUsername();

    $errors = array();
    if ($request->isFormPost()) {
      $sex = $request->getStr('sex');
      $sexes = array(PhutilPerson::SEX_MALE, PhutilPerson::SEX_FEMALE);
      if (in_array($sex, $sexes)) {
        $user->setSex($sex);
      } else {
        $user->setSex(null);
      }

      // Checked in runtime.
      $user->setTranslation($request->getStr('translation'));

      if (!$errors) {
        $user->save();
        return id(new AphrontRedirectResponse())
          ->setURI($this->getPanelURI('?saved=true'));
      }
    }

    $label_unknown = pht('%s updated their profile', $username);
    $label_her = pht('%s updated her profile', $username);
    $label_his = pht('%s updated his profile', $username);

    $sexes = array(
      PhutilPerson::SEX_UNKNOWN => $label_unknown,
      PhutilPerson::SEX_MALE => $label_his,
      PhutilPerson::SEX_FEMALE => $label_her,
    );

    $locales = PhutilLocale::loadAllLocales();
    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    $is_dev = PhabricatorEnv::getEnvConfig('phabricator.developer-mode');

    $translations = array();
    foreach ($locales as $locale) {
      if ($is_serious && $locale->isSillyLocale()) {
        // Omit silly locales on serious business installs.
        continue;
      }
      if (!$is_dev && $locale->isTestLocale()) {
        // Omit test locales on installs which aren't in development mode.
        continue;
      }
      $translations[$locale->getLocaleCode()] = $locale->getLocaleName();
    }

    asort($translations);
    // TODO: Implement "locale.default" and use it here.
    $default = 'en_US';
    $translations = array(
      '' => pht('Server Default: %s', $locales[$default]->getLocaleName()),
    ) + $translations;

    $form = new AphrontFormView();
    $form
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setOptions($translations)
          ->setLabel(pht('Translation'))
          ->setName('translation')
          ->setValue($user->getTranslation()))
      ->appendRemarkupInstructions(pht('**Choose the pronoun you prefer:**'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setOptions($sexes)
          ->setLabel(pht('Pronoun'))
          ->setName('sex')
          ->setValue($user->getSex()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Account Settings')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Account Settings'))
      ->setFormSaved($request->getStr('saved'))
      ->setFormErrors($errors)
      ->setForm($form);

    return array(
      $form_box,
    );
  }
}
