<?php

final class PhabricatorSettingsPanelProfile
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'profile';
  }

  public function getPanelName() {
    return pht('Profile');
  }

  public function getPanelGroup() {
    return pht('Account Information');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();

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
        $response = id(new AphrontRedirectResponse())
          ->setURI($this->getPanelURI('?saved=true'));
        return $response;
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle(pht('Form Errors'));
      $error_view->setErrors($errors);
    } else {
      if ($request->getStr('saved')) {
        $error_view = new AphrontErrorView();
        $error_view->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $error_view->setTitle(pht('Changes Saved'));
        $error_view->appendChild(
          phutil_tag('p', array(), pht('Your changes have been saved.')));
        $error_view = $error_view->render();
      }
    }

    $profile_uri = PhabricatorEnv::getURI('/p/'.$user->getUsername().'/');

    $sexes = array(
      PhutilPerson::SEX_UNKNOWN => pht('Unknown'),
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
      ->setUser($request->getUser())
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
          ->setValue($user->getTranslation()));

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save')));

    $header = new PhabricatorHeaderView();
    $header->setHeader(pht('Edit Profile Details'));

    return array(
      $error_view,
      $header,
      $form,
    );
  }

}
