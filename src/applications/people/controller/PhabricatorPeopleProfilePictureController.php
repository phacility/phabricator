<?php

final class PhabricatorPeopleProfilePictureController
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

    $supported_formats = PhabricatorFile::getTransformableImageFormats();
    $e_file = true;
    $errors = array();

    if ($request->isFormPost()) {
      $phid = $request->getStr('phid');
      $is_default = false;
      if ($phid == PhabricatorPHIDConstants::PHID_VOID) {
        $phid = null;
        $is_default = true;
      } else if ($phid) {
        $file = id(new PhabricatorFileQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($phid))
          ->executeOne();
      } else {
        if ($request->getFileExists('picture')) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['picture'],
            array(
              'authorPHID' => $viewer->getPHID(),
            ));
        } else {
          $e_file = pht('Required');
          $errors[] = pht(
            'You must choose a file when uploading a new profile picture.');
        }
      }

      if (!$errors && !$is_default) {
        if (!$file->isTransformableImage()) {
          $e_file = pht('Not Supported');
          $errors[] = pht(
            'This server only supports these image formats: %s.',
            implode(', ', $supported_formats));
        } else {
          $xformer = new PhabricatorImageTransformer();
          $xformed = $xformer->executeProfileTransform(
            $file,
            $width = 50,
            $min_height = 50,
            $max_height = 50);
        }
      }

      if (!$errors) {
        if ($is_default) {
          $user->setProfileImagePHID(null);
        } else {
          $user->setProfileImagePHID($xformed->getPHID());
          $xformed->attachToObject($viewer, $user->getPHID());
        }
        $user->save();
        return id(new AphrontRedirectResponse())->setURI($profile_uri);
      }
    }

    $title = pht('Edit Profile Picture');
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($user->getUsername(), $profile_uri);
    $crumbs->addTextCrumb($title);

    $form = id(new PHUIFormLayoutView())
      ->setUser($viewer);

    $default_image = PhabricatorFile::loadBuiltin($viewer, 'profile.png');

    $images = array();

    $current = $user->getProfileImagePHID();
    $has_current = false;
    if ($current) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($current))
        ->execute();
      if ($files) {
        $file = head($files);
        if ($file->isTransformableImage()) {
          $has_current = true;
          $images[$current] = array(
            'uri' => $file->getBestURI(),
            'tip' => pht('Current Picture'),
          );
        }
      }
    }

    // Try to add external account images for any associated external accounts.
    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($user->getPHID()))
      ->needImages(true)
      ->execute();

    foreach ($accounts as $account) {
      $file = $account->getProfileImageFile();
      if ($account->getProfileImagePHID() != $file->getPHID()) {
        // This is a default image, just skip it.
        continue;
      }

      $provider = PhabricatorAuthProvider::getEnabledProviderByKey(
        $account->getProviderKey());
      if ($provider) {
        $tip = pht('Picture From %s', $provider->getProviderName());
      } else {
        $tip = pht('Picture From External Account');
      }

      if ($file->isTransformableImage()) {
        $images[$file->getPHID()] = array(
          'uri' => $file->getBestURI(),
          'tip' => $tip,
        );
      }
    }

    // Try to add Gravatar images for any email addresses associated with the
    // account.
    if (PhabricatorEnv::getEnvConfig('security.allow-outbound-http')) {
      $emails = id(new PhabricatorUserEmail())->loadAllWhere(
        'userPHID = %s ORDER BY address',
        $user->getPHID());

      $futures = array();
      foreach ($emails as $email_object) {
        $email = $email_object->getAddress();

        $hash = md5(strtolower(trim($email)));
        $uri = id(new PhutilURI("https://secure.gravatar.com/avatar/{$hash}"))
          ->setQueryParams(
            array(
              'size' => 200,
              'default' => '404',
              'rating' => 'x',
            ));
        $futures[$email] = new HTTPSFuture($uri);
      }

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      foreach (Futures($futures) as $email => $future) {
        try {
          list($body) = $future->resolvex();
          $file = PhabricatorFile::newFromFileData(
            $body,
            array(
              'name' => 'profile-gravatar',
              'ttl'  => (60 * 60 * 4),
            ));
          if ($file->isTransformableImage()) {
            $images[$file->getPHID()] = array(
              'uri' => $file->getBestURI(),
              'tip' => pht('Gravatar for %s', $email),
            );
          }
        } catch (Exception $ex) {
          // Just continue.
        }
      }
      unset($unguarded);
    }

    $images[PhabricatorPHIDConstants::PHID_VOID] = array(
      'uri' => $default_image->getBestURI(),
      'tip' => pht('Default Picture'),
    );

    require_celerity_resource('people-profile-css');
    Javelin::initBehavior('phabricator-tooltips', array());

    $buttons = array();
    foreach ($images as $phid => $spec) {
      $button = javelin_tag(
        'button',
        array(
          'class' => 'grey profile-image-button',
          'sigil' => 'has-tooltip',
          'meta' => array(
            'tip' => $spec['tip'],
            'size' => 300,
          ),
        ),
        phutil_tag(
          'img',
          array(
            'height' => 50,
            'width' => 50,
            'src' => $spec['uri'],
          )));

      $button = array(
        phutil_tag(
          'input',
          array(
            'type'  => 'hidden',
            'name'  => 'phid',
            'value' => $phid,
          )),
        $button);

      $button = phabricator_form(
        $viewer,
        array(
          'class' => 'profile-image-form',
          'method' => 'POST',
        ),
        $button);

      $buttons[] = $button;
    }

    if ($has_current) {
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Current Picture'))
          ->setValue(array_shift($buttons)));
    }

    $form->appendChild(
      id(new AphrontFormMarkupControl())
        ->setLabel(pht('Use Picture'))
        ->setValue($buttons));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    $upload_form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setName('picture')
          ->setLabel(pht('Upload Picture'))
          ->setError($e_file)
          ->setCaption(
            pht('Supported formats: %s', implode(', ', $supported_formats))))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($profile_uri)
          ->setValue(pht('Upload Picture')));

    $upload_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Upload New Picture'))
      ->setForm($upload_form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $upload_box,
      ),
      array(
        'title' => $title,
      ));
  }
}
