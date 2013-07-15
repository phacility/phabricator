<?php

final class PhabricatorSettingsPanelAccount
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'account';
  }

  public function getPanelName() {
    return pht('Account');
  }

  public function getPanelGroup() {
    return pht('Account Information');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $errors = array();
    if ($request->isFormPost()) {
      $new_timezone = $request->getStr('timezone');
      if (in_array($new_timezone, DateTimeZone::listIdentifiers(), true)) {
        $user->setTimezoneIdentifier($new_timezone);
      } else {
        $errors[] = pht('The selected timezone is not a valid timezone.');
      }

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

    $notice = null;
    if (!$errors) {
      if ($request->getStr('saved')) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle(pht('Changes Saved'));
        $notice->appendChild(
          phutil_tag('p', array(), pht('Your changes have been saved.')));
        $notice = $notice->render();
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setErrors($errors);
    }

    $timezone_ids = DateTimeZone::listIdentifiers();
    $timezone_id_map = array_fuse($timezone_ids);

    $sexes = array(
      PhutilPerson::SEX_UNKNOWN => pht('Unspecified'),
      PhutilPerson::SEX_MALE => pht('Male'),
      PhutilPerson::SEX_FEMALE => pht('Female'),
    );

    $translations = array();
    $symbols = id(new PhutilSymbolLoader())
      ->setType('class')
      ->setAncestorClass('PhabricatorTranslation')
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();
    foreach ($symbols as $symbol) {
      $class = $symbol['name'];
      $translations[$class] = newv($class, array())->getName();
    }
    asort($translations);
    $default = PhabricatorEnv::newObjectFromConfig('translation.provider');
    $translations = array(
      '' => pht('Server Default (%s)', $default->getName()),
    ) + $translations;


    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setFlexible(true)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Timezone'))
          ->setName('timezone')
          ->setOptions($timezone_id_map)
          ->setValue($user->getTimezoneIdentifier()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setOptions($sexes)
          ->setLabel(pht('Sex'))
          ->setName('sex')
          ->setValue($user->getSex()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setOptions($translations)
          ->setLabel(pht('Translation'))
          ->setName('translation')
          ->setValue($user->getTranslation()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Account Settings')));

    $header = new PhabricatorHeaderView();
    $header->setHeader(pht('Account Settings'));

    return array(
      $notice,
      $header,
      $form,
    );
  }
}
