<?php

/**
 * @group paste
 */
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

      $paste = PhabricatorPaste::initializeNewPaste($user);

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
    if ($is_create && $parent) {
      $v_title = pht('Fork of %s', $parent->getFullName());
      $v_language = $parent->getLanguage();
      $v_text = $parent->getRawContent();
    } else {
      $v_title = $paste->getTitle();
      $v_language = $paste->getLanguage();
      $v_text = '';
    }
    $v_policy = $paste->getViewPolicy();

    if ($request->isFormPost()) {
      $xactions = array();

      if ($is_create) {
        $v_text = $request->getStr('text');
        if (!strlen($v_text)) {
          $e_text = pht('Required');
          $errors[] = pht('The paste may not be blank.');
        } else {
          $e_text = null;
        }
     }

      $v_title = $request->getStr('title');
      $v_language = $request->getStr('language');
      $v_policy = $request->getStr('can_view');

      // NOTE: The author is the only editor and can always view the paste,
      // so it's impossible for them to choose an invalid policy.

      if (!$errors) {
        if ($is_create) {
          $xactions[] = id(new PhabricatorPasteTransaction())
            ->setTransactionType(PhabricatorPasteTransaction::TYPE_CREATE)
            ->setNewValue(array(
              'title' => $v_title,
              'text' => $v_text));
        }
        $xactions[] = id(new PhabricatorPasteTransaction())
          ->setTransactionType(PhabricatorPasteTransaction::TYPE_TITLE)
          ->setNewValue($v_title);
        $xactions[] = id(new PhabricatorPasteTransaction())
          ->setTransactionType(PhabricatorPasteTransaction::TYPE_LANGUAGE)
          ->setNewValue($v_language);
        $xactions[] = id(new PhabricatorPasteTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
          ->setNewValue($v_policy);
        $editor = id(new PhabricatorPasteEditor())
          ->setActor($user)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true);
        $xactions = $editor->applyTransactions($paste, $xactions);
        return id(new AphrontRedirectResponse())->setURI($paste->getURI());
      } else {
        // make sure we update policy so its correctly populated to what
        // the user chose
        $paste->setViewPolicy($v_policy);
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('A Fatal Omission!'))
        ->setErrors($errors);
    }

    $form = new AphrontFormView();

    $langs = array(
      '' => pht('(Detect From Filename in Title)'),
    ) + PhabricatorEnv::getEnvConfig('pygments.dropdown-choices');

    $form
      ->setUser($user)
      ->addHiddenInput('parent', $parent_id)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Title'))
          ->setValue($v_title)
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Language'))
          ->setName('language')
          ->setValue($v_language)
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
            ->setValue($v_text)
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
      $title = pht('Create New Paste');
      $short = pht('Create');
    }

    $form->appendChild($submit);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormError($error_view)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    if (!$is_create) {
      $crumbs->addTextCrumb('P'.$paste->getID(), '/P'.$paste->getID());
    }
    $crumbs->addTextCrumb($short);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
