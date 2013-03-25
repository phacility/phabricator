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

    $profile = $user->loadUserProfile();

    $supported_formats = PhabricatorFile::getTransformableImageFormats();

    $e_image = null;
    $errors = array();
    if ($request->isFormPost()) {
      $profile->setTitle($request->getStr('title'));
      $profile->setBlurb($request->getStr('blurb'));

      $sex = $request->getStr('sex');
      $sexes = array(PhutilPerson::SEX_MALE, PhutilPerson::SEX_FEMALE);
      if (in_array($sex, $sexes)) {
        $user->setSex($sex);
      } else {
        $user->setSex(null);
      }

      // Checked in runtime.
      $user->setTranslation($request->getStr('translation'));

      $default_image = $request->getExists('default_image');
      $gravatar_email = $request->getStr('gravatar');
      if ($default_image) {
        $profile->setProfileImagePHID(null);
        $user->setProfileImagePHID(null);
      } else if (!empty($gravatar_email) || $request->getFileExists('image')) {
        $file = null;
        if (!empty($gravatar_email)) {
          // These steps recommended by:
          // https://en.gravatar.com/site/implement/hash/
          $trimmed = trim($gravatar_email);
          $lower_cased = strtolower($trimmed);
          $hash = md5($lower_cased);
          $url = 'http://www.gravatar.com/avatar/'.($hash).'?s=200';
          $file = PhabricatorFile::newFromFileDownload(
            $url,
            array(
              'name' => 'gravatar',
              'authorPHID' => $user->getPHID(),
            ));
        } else if ($request->getFileExists('image')) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['image'],
            array(
              'authorPHID' => $user->getPHID(),
            ));
        }

        $okay = $file->isTransformableImage();
        if ($okay) {
          $xformer = new PhabricatorImageTransformer();

          // Generate the large picture for the profile page.
          $large_xformed = $xformer->executeProfileTransform(
            $file,
            $width = 280,
            $min_height = 140,
            $max_height = 420);
          $profile->setProfileImagePHID($large_xformed->getPHID());

          // Generate the small picture for comments, etc.
          $small_xformed = $xformer->executeProfileTransform(
            $file,
            $width = 50,
            $min_height = 50,
            $max_height = 50);
          $user->setProfileImagePHID($small_xformed->getPHID());
        } else {
          $e_image = pht('Not Supported');
          $errors[] =
            pht('This server only supports these image formats:').
              ' ' .implode(', ', $supported_formats);
        }
      }

      if (!$errors) {
        $user->save();
        $profile->save();
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

    $img_src = $user->loadProfileImageURI();
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
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Title'))
          ->setName('title')
          ->setValue($profile->getTitle())
          ->setCaption(pht('Serious business title.')))
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
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Profile URI'))
          ->setValue(
            phutil_tag(
              'a',
              array(
                'href' => $profile_uri,
              ),
              $profile_uri)))
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>',
        pht('Write something about yourself! Make sure to include important ' .
          'information like your favorite Pokemon and which Starcraft race ' .
          'you play.')))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Blurb'))
          ->setName('blurb')
          ->setValue($profile->getBlurb()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Profile Image'))
          ->setValue(
            phutil_tag(
              'img',
              array(
                'src' => $img_src,
              ))))
      ->appendChild(
        id(new AphrontFormImageControl())
          ->setLabel(pht('Change Image'))
          ->setName('image')
          ->setError($e_image)
          ->setCaption(
            pht('Supported formats: %s', implode(', ', $supported_formats))));

    if (PhabricatorEnv::getEnvConfig('security.allow-outbound-http')) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Import Gravatar'))
          ->setName('gravatar')
          ->setError($e_image)
          ->setCaption(pht('Enter gravatar email address')));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save'))
        ->addCancelButton('/p/'.$user->getUsername().'/'));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Edit Profile Details'));
    $panel->appendChild($form);
    $panel->setNoBackground();

    return array(
      $error_view,
      $panel,
    );
  }

}
