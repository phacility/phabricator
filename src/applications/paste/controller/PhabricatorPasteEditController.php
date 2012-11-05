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
          $e_text = 'Required';
          $errors[] = 'The paste may not be blank.';
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
        $paste->setTitle('Fork of '.$parent->getFullName());
        $paste->setLanguage($parent->getLanguage());
        $text = $parent->getContent();
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('A fatal omission!')
        ->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form->setFlexible(true);

    $langs = array(
      '' => '(Detect From Filename in Title)',
    ) + PhabricatorEnv::getEnvConfig('pygments.dropdown-choices');

    $form
      ->setUser($user)
      ->addHiddenInput('parent', $parent_id)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setValue($paste->getTitle())
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Language')
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
            ->setLabel('Text')
            ->setError($e_text)
            ->setValue($text)
            ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
            ->setCustomClass('PhabricatorMonospaced')
            ->setName('text'));
    } else {
      $fork_link = phutil_render_tag(
        'a',
        array(
          'href' => $this->getApplicationURI('?parent='.$paste->getID())
        ),
        'Fork'
      );
      $form
        ->appendChild(
          id(new AphrontFormMarkupControl())
          ->setLabel('Text')
          ->setValue(
            'Paste text can not be edited. '.
            $fork_link.' to create a new paste.'
          ));
    }

    $submit = new AphrontFormSubmitControl();

    if (!$is_create) {
      $submit->addCancelButton($paste->getURI());
      $submit->setValue('Save Paste');
      $title = 'Edit '.$paste->getFullName();
    } else {
      $submit->setValue('Create Paste');
      $title = 'Create Paste';
    }

    $form
      ->appendChild($submit);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('edit');
    $nav->appendChild(
      array(
        id(new PhabricatorHeaderView())->setHeader($title),
        $error_view,
        $form,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
