<?php

final class PhabricatorPasteEditController extends PhabricatorPasteController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }


  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $parent = null;
    $parent_id = null;
    if (!$this->id) {
      $is_create = true;

      $paste = new PhabricatorPaste();

      $parent_id = $request->getStr('parent');
      if ($parent_id) {
        // NOTE: If the Paste is forked from a paste which the user no longer
        // has permission to see, we still let them edit it.
        $parent = id(new PhabricatorPasteQuery())
          ->setViewer($user)
          ->withIDs(array($parent_id))
          ->needContent(true)
          ->needRawContent(true)
          ->execute();
        $parent = head($parent);

        if ($parent) {
          $paste->setParentPHID($parent->getPHID());
          $paste->setViewPolicy($parent->getViewPolicy());
        }
      }

      $paste->setAuthorPHID($user->getPHID());
    } else {
      $is_create = false;

      $paste = id(new PhabricatorPasteQuery())
        ->setViewer($user)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withIDs(array($this->id))
        ->executeOne();
      if (!$paste) {
        return new Aphront404Response();
      }
    }

    $text = null;
    $e_text = true;
    $errors = array();
    if ($request->isFormPost()) {

      if ($is_create) {
        $text = $request->getStr('text');
        if (!strlen($text)) {
          $e_text = pht('Required');
          $errors[] = pht('The paste may not be blank.');
        } else {
          $e_text = null;
        }
      }

      $paste->setTitle($request->getStr('title'));
      $paste->setLanguage($request->getStr('language'));
      $paste->setViewPolicy($request->getStr('can_view'));

      // NOTE: The author is the only editor and can always view the paste,
      // so it's impossible for them to choose an invalid policy.

      if (!$errors) {
        if ($is_create) {
          $paste_file = PhabricatorFile::newFromFileData(
            $text,
            array(
              'name' => $paste->getTitle(),
              'mime-type' => 'text/plain; charset=utf-8',
              'authorPHID' => $user->getPHID(),
            ));
          $paste->setFilePHID($paste_file->getPHID());
        }
        $paste->save();
        return id(new AphrontRedirectResponse())->setURI($paste->getURI());
      }
    } else {
      if ($is_create && $parent) {
        $paste->setTitle(pht('Fork of %s', $parent->getFullName()));
        $paste->setLanguage($parent->getLanguage());
        $text = $parent->getRawContent();
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('A Fatal Omission!'))
        ->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form->setFlexible(true);

    $langs = array(
      '' => pht('(Detect From Filename in Title)'),
    ) + PhabricatorEnv::getEnvConfig('pygments.dropdown-choices');

    $form
      ->setUser($user)
      ->addHiddenInput('parent', $parent_id)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Title'))
          ->setValue($paste->getTitle())
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Language'))
          ->setName('language')
          ->setValue($paste->getLanguage())
          ->setOptions($langs));

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($paste)
      ->execute();

    $form->appendChild(
      id(new AphrontFormPolicyControl())
        ->setUser($user)
        ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
        ->setPolicyObject($paste)
        ->setPolicies($policies)
        ->setName('can_view'));

    if ($is_create) {
      $form
        ->appendChild(
          id(new AphrontFormTextAreaControl())
            ->setLabel(pht('Text'))
            ->setError($e_text)
            ->setValue($text)
            ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
            ->setCustomClass('PhabricatorMonospaced')
            ->setName('text'));
    } else {
      $fork_link = phutil_tag(
        'a',
        array(
          'href' => $this->getApplicationURI('?parent='.$paste->getID())
        ),
        pht('Fork'));
      $form
        ->appendChild(
          id(new AphrontFormMarkupControl())
          ->setLabel(pht('Text'))
          ->setValue(pht(
            'Paste text can not be edited. %s to create a new paste.',
            $fork_link)));
    }

    $submit = new AphrontFormSubmitControl();

    if (!$is_create) {
      $submit->addCancelButton($paste->getURI());
      $submit->setValue(pht('Save Paste'));
      $title = pht('Edit %s', $paste->getFullName());
      $short = pht('Edit');
    } else {
      $submit->setValue(pht('Create Paste'));
      $title = pht('Create Paste');
      $short = pht('Create');
    }

    $form
      ->appendChild($submit);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    if (!$is_create) {
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName('P'.$paste->getID())
          ->setHref('/P'.$paste->getID()));
    }
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())->setName($short));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        id(new PhabricatorHeaderView())->setHeader($title),
        $error_view,
        $form,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
