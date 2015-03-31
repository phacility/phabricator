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
      $paste->attachRawContent('');
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
        ->needRawContent(true)
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
      $v_text = $paste->getRawContent();
    }
    $v_view_policy = $paste->getViewPolicy();
    $v_edit_policy = $paste->getEditPolicy();

    if ($is_create) {
      $v_projects = array();
    } else {
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $paste->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
    }

    if ($request->isFormPost()) {
      $xactions = array();

      $v_text = $request->getStr('text');
      if (!strlen($v_text)) {
        $e_text = pht('Required');
        $errors[] = pht('The paste may not be blank.');
      } else {
        $e_text = null;
      }

      $v_title = $request->getStr('title');
      $v_language = $request->getStr('language');
      $v_view_policy = $request->getStr('can_view');
      $v_edit_policy = $request->getStr('can_edit');
      $v_projects = $request->getArr('projects');

      // NOTE: The author is the only editor and can always view the paste,
      // so it's impossible for them to choose an invalid policy.

      if (!$errors) {
        if ($is_create || ($v_text !== $paste->getRawContent())) {
          $file = PhabricatorPasteEditor::initializeFileForPaste(
            $user,
            $v_title,
            $v_text);

          $xactions[] = id(new PhabricatorPasteTransaction())
            ->setTransactionType(PhabricatorPasteTransaction::TYPE_CONTENT)
            ->setNewValue($file->getPHID());
        }

        $xactions[] = id(new PhabricatorPasteTransaction())
          ->setTransactionType(PhabricatorPasteTransaction::TYPE_TITLE)
          ->setNewValue($v_title);
        $xactions[] = id(new PhabricatorPasteTransaction())
          ->setTransactionType(PhabricatorPasteTransaction::TYPE_LANGUAGE)
          ->setNewValue($v_language);
        $xactions[] = id(new PhabricatorPasteTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
          ->setNewValue($v_view_policy);
        $xactions[] = id(new PhabricatorPasteTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
          ->setNewValue($v_edit_policy);

        $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
        $xactions[] = id(new PhabricatorPasteTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
          ->setMetadataValue('edge:type', $proj_edge_type)
          ->setNewValue(array('=' => array_fuse($v_projects)));

        $editor = id(new PhabricatorPasteEditor())
          ->setActor($user)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true);
        $xactions = $editor->applyTransactions($paste, $xactions);
        return id(new AphrontRedirectResponse())->setURI($paste->getURI());
      } else {
        // make sure we update policy so its correctly populated to what
        // the user chose
        $paste->setViewPolicy($v_view_policy);
        $paste->setEditPolicy($v_edit_policy);
      }
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

    $form->appendChild(
      id(new AphrontFormPolicyControl())
        ->setUser($user)
        ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
        ->setPolicyObject($paste)
        ->setPolicies($policies)
        ->setName('can_edit'));

    $form->appendControl(
      id(new AphrontFormTokenizerControl())
        ->setLabel(pht('Projects'))
        ->setName('projects')
        ->setValue($v_projects)
        ->setDatasource(new PhabricatorProjectDatasource()));

    $form
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Text'))
          ->setError($e_text)
          ->setValue($v_text)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setCustomClass('PhabricatorMonospaced')
          ->setName('text'));

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
      ->setFormErrors($errors)
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
      ));
  }

}
