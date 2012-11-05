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

    $profile = id(new PhabricatorUserProfile())->loadOneWhere(
      'userPHID = %s',
      $user->getPHID());
    if (!$profile) {
      $profile = new PhabricatorUserProfile();
      $profile->setUserPHID($user->getPHID());
    }

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
      if ($default_image) {
        $profile->setProfileImagePHID(null);
        $user->setProfileImagePHID(null);
      } else if (!empty($_FILES['image'])) {
        $err = idx($_FILES['image'], 'error');
        if ($err != UPLOAD_ERR_NO_FILE) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['image'],
            array(
              'authorPHID' => $user->getPHID(),
            ));
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
            $e_image = 'Not Supported';
            $errors[] =
              'This server only supports these image formats: '.
              implode(', ', $supported_formats).'.';
          }
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
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    } else {
      if ($request->getStr('saved')) {
        $error_view = new AphrontErrorView();
        $error_view->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $error_view->setTitle('Changes Saved');
        $error_view->appendChild('<p>Your changes have been saved.</p>');
        $error_view = $error_view->render();
      }
    }

    $img_src = $user->loadProfileImageURI();
    $profile_uri = PhabricatorEnv::getURI('/p/'.$user->getUsername().'/');

    $sexes = array(
      PhutilPerson::SEX_UNKNOWN => 'Unknown',
      PhutilPerson::SEX_MALE => 'Male',
      PhutilPerson::SEX_FEMALE => 'Female',
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
      '' => 'Server Default ('.$default->getName().')',
    ) + $translations;

    $form = new AphrontFormView();
    $form
      ->setUser($request->getUser())
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setName('title')
          ->setValue($profile->getTitle())
          ->setCaption('Serious business title.'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setOptions($sexes)
          ->setLabel('Sex')
          ->setName('sex')
          ->setValue($user->getSex()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setOptions($translations)
          ->setLabel('Translation')
          ->setName('translation')
          ->setValue($user->getTranslation()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Profile URI')
          ->setValue(
            phutil_render_tag(
              'a',
              array(
                'href' => $profile_uri,
              ),
              phutil_escape_html($profile_uri))))
      ->appendChild(
        '<p class="aphront-form-instructions">Write something about yourself! '.
        'Make sure to include <strong>important information</strong> like '.
        'your favorite Pokemon and which Starcraft race you play.</p>')
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Blurb')
          ->setName('blurb')
          ->setValue($profile->getBlurb()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Profile Image')
          ->setValue(
            phutil_render_tag(
              'img',
              array(
                'src' => $img_src,
              ))))
      ->appendChild(
        id(new AphrontFormImageControl())
          ->setLabel('Change Image')
          ->setName('image')
          ->setError($e_image)
          ->setCaption('Supported formats: '.implode(', ', $supported_formats)))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton('/p/'.$user->getUsername().'/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit Profile Details');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return array(
      $error_view,
      $panel,
    );
  }

}
