<?php

final class PhabricatorPeopleProfilePictureController
  extends PhabricatorPeopleProfileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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

    $this->setUser($user);
    $name = $user->getUserName();

    $done_uri = '/p/'.$name.'/';

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
              'canCDN' => true,
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
          $xform = PhabricatorFileTransform::getTransformByKey(
            PhabricatorFileThumbnailTransform::TRANSFORM_PROFILE);
          $xformed = $xform->executeTransform($file);
        }
      }

      if (!$errors) {
        if ($is_default) {
          $user->setProfileImagePHID(null);
        } else {
          $user->setProfileImagePHID($xformed->getPHID());
          $xformed->attachToObject($user->getPHID());
        }
        $user->save();
        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }
    }

    $title = pht('Edit Profile Picture');

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

    $builtins = array(
      'user1.png',
      'user2.png',
      'user3.png',
      'user4.png',
      'user5.png',
      'user6.png',
      'user7.png',
      'user8.png',
      'user9.png',
      );
    foreach ($builtins as $builtin) {
      $file = PhabricatorFile::loadBuiltin($viewer, $builtin);
      $images[$file->getPHID()] = array(
        'uri' => $file->getBestURI(),
        'tip' => pht('Builtin Image'),
      );
    }

    // Try to add external account images for any associated external accounts.
    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($user->getPHID()))
      ->needImages(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
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
        $button,
      );

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
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
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
          ->addCancelButton($done_uri)
          ->setValue(pht('Upload Picture')));

    $upload_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Upload New Picture'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($upload_form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Profile Picture'));
    $crumbs->setBorder(true);

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorPeopleProfileMenuEngine::ITEM_MANAGE);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Edit Profile Picture'))
      ->setHeaderIcon('fa-camera');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $form_box,
        $upload_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($view);
  }
}
