<?php

/**
 * @group pholio
 */
final class PholioMockEditController extends PholioController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();


    if ($this->id) {
      $mock = id(new PholioMockQuery())
        ->setViewer($user)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withIDs(array($this->id))
        ->executeOne();

      if (!$mock) {
        return new Aphront404Response();
      }

      $title = pht('Edit Mock');

      $is_new = false;
    } else {
      $mock = new PholioMock();
      $mock->setAuthorPHID($user->getPHID());
      $mock->setViewPolicy(PhabricatorPolicies::POLICY_USER);

      $title = pht('Create Mock');

      $is_new = true;
    }

    $e_name = true;
    $e_images = true;
    $errors = array();

    $v_name = $mock->getName();
    $v_desc = $mock->getDescription();
    $v_view = $mock->getViewPolicy();
    $v_cc = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $mock->getPHID());
    $files = array();

    if ($request->isFormPost()) {
      $xactions = array();

      $type_name = PholioTransactionType::TYPE_NAME;
      $type_desc = PholioTransactionType::TYPE_DESCRIPTION;
      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_cc   = PhabricatorTransactions::TYPE_SUBSCRIBERS;

      $v_name = $request->getStr('name');
      $v_desc = $request->getStr('description');
      $v_view = $request->getStr('can_view');
      $v_cc   = $request->getArr('cc');

      $xactions[$type_name] = $v_name;
      $xactions[$type_desc] = $v_desc;
      $xactions[$type_view] = $v_view;
      $xactions[$type_cc]   = array('=' => $v_cc);

      if (!strlen($request->getStr('name'))) {
        $e_name = 'Required';
        $errors[] = pht('You must name the mock.');
      }

      $images = array();
      if ($is_new) {
        // TODO: Make this transactional and allow edits?


        $file_phids = $request->getArr('file_phids');
        if ($file_phids) {
          $files = id(new PhabricatorFileQuery())
            ->setViewer($user)
            ->withPHIDs($file_phids)
            ->execute();
        }

        if (!$files) {
          $e_images = pht('Required');
          $errors[] = pht('You must add at least one image to the mock.');
        } else {
          $mock->setCoverPHID(head($files)->getPHID());
        }

        $sequence = 0;

        foreach ($files as $file) {
          $image = new PholioImage();
          $image->setFilePHID($file->getPHID());
          $image->setSequence($sequence++);

          $images[] = $image;
        }
      }

      if (!$errors) {
        $content_source = PhabricatorContentSource::newForSource(
          PhabricatorContentSource::SOURCE_WEB,
          array(
            'ip' => $request->getRemoteAddr(),
          ));

        foreach ($xactions as $type => $value) {
          $xactions[$type] = id(new PholioTransaction())
            ->setTransactionType($type)
            ->setNewValue($value);
        }

        $mock->openTransaction();
          $editor = id(new PholioMockEditor())
            ->setContentSource($content_source)
            ->setContinueOnNoEffect(true)
            ->setActor($user);

          $xactions = $editor->applyTransactions($mock, $xactions);

          if ($images) {
            foreach ($images as $image) {
              // TODO: Move into editor?
              $image->setMockID($mock->getID());
              $image->save();
            }
          }
        $mock->saveTransaction();

        return id(new AphrontRedirectResponse())
          ->setURI('/M'.$mock->getID());
      }
    }

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Form Errors'))
        ->setErrors($errors);
    } else {
      $error_view = null;
    }

    if ($this->id) {
      $submit = id(new AphrontFormSubmitControl())
        ->addCancelButton('/M'.$this->id)
        ->setValue(pht('Save'));
    } else {
      $submit = id(new AphrontFormSubmitControl())
        ->addCancelButton($this->getApplicationURI())
        ->setValue(pht('Create'));
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($mock)
      ->execute();

    // NOTE: Make this show up correctly on the rendered form.
    $mock->setViewPolicy($v_view);

    $handles = id(new PhabricatorObjectHandleData($v_cc))
      ->setViewer($user)
      ->loadHandles();

    $cc_tokens = mpull($handles, 'getFullName', 'getPHID');

    $images_controller = '';
    if ($is_new) {
      $images_controller =
          id(new AphrontFormDragAndDropUploadControl($request))
            ->setValue($files)
            ->setName('file_phids')
            ->setLabel(pht('Images'))
            ->setActivatedClass('aphront-textarea-drag-and-drop')
            ->setError($e_images);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFlexible(true)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setValue($v_name)
          ->setLabel(pht('Name'))
          ->setError($e_name))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('description')
          ->setValue($v_desc)
          ->setLabel(pht('Description'))
          ->setUser($user))
      ->appendChild($images_controller)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('CC'))
          ->setName('cc')
          ->setValue($cc_tokens)
          ->setUser($user)
          ->setDatasource('/typeahead/common/mailable/'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicyObject($mock)
          ->setPolicies($policies)
          ->setName('can_view'))
      ->appendChild($submit);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNav());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($this->getApplicationURI()));

    $content = array(
      $crumbs,
      $error_view,
      $form,
    );

    $nav = $this->buildSideNav();
    $nav->selectFilter(null);
    $nav->appendChild($content);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
