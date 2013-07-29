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
        ->needImages(true)
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
      $mock_images = $mock->getImages();
      $files = mpull($mock_images, 'getFile');
      $mock_images = mpull($mock_images, null, 'getFilePHID');
    } else {
      $mock = id(new PholioMock())
        ->setAuthorPHID($user->getPHID())
        ->attachImages(array())
        ->setViewPolicy(PhabricatorPolicies::POLICY_USER);

      $title = pht('Create Mock');

      $is_new = true;
      $files = array();
      $mock_images = array();
    }

    $e_name = true;
    $e_images = true;
    $errors = array();

    $v_name = $mock->getName();
    $v_desc = $mock->getDescription();
    $v_view = $mock->getViewPolicy();
    $v_cc = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $mock->getPHID());

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

      $mock_xactions = array();
      $mock_xactions[$type_name] = $v_name;
      $mock_xactions[$type_desc] = $v_desc;
      $mock_xactions[$type_view] = $v_view;
      $mock_xactions[$type_cc]   = array('=' => $v_cc);

      if (!strlen($request->getStr('name'))) {
        $e_name = 'Required';
        $errors[] = pht('You must give the mock a name.');
      }

      $file_phids = $request->getArr('file_phids');
      if ($file_phids) {
        $files = id(new PhabricatorFileQuery())
          ->setViewer($user)
          ->withPHIDs($file_phids)
          ->execute();
        $files = mpull($files, null, 'getPHID');
        $files = array_select_keys($files, $file_phids);
      } else {
        $files = array();
      }

      if (!$files) {
        $e_images = pht('Required');
        $errors[] = pht('You must add at least one image to the mock.');
      } else {
        $mock->setCoverPHID(head($files)->getPHID());
      }

      if (!$errors) {
        foreach ($mock_xactions as $type => $value) {
          $xactions[$type] = id(new PholioTransaction())
            ->setTransactionType($type)
            ->setNewValue($value);
        }

        $sequence = 0;
        $replaces = $request->getArr('replaces');
        $replaces_map = array_flip($replaces);

        /**
         * Foreach file posted, check to see whether we are replacing an image,
         * adding an image, or simply updating image metadata. Create
         * transactions for these cases as appropos.
         */
        foreach ($files as $file_phid => $file) {
          $replaces_image_phid = null;
          if (isset($replaces_map[$file_phid])) {
            $old_file_phid = $replaces_map[$file_phid];
            $old_image = idx($mock_images, $old_file_phid);
            if ($old_image) {
              $replaces_image_phid = $old_image->getPHID();
            }
          }

          $existing_image = idx($mock_images, $file_phid);

          $title = (string)$request->getStr('title_'.$file_phid);
          $description = (string)$request->getStr('description_'.$file_phid);

          if ($replaces_image_phid) {
            $replace_image = id(new PholioImage())
              ->setReplacesImagePHID($replaces_image_phid)
              ->setFilePhid($file_phid)
              ->setName(strlen($title) ? $title : $file->getName())
              ->setDescription($description)
              ->setSequence($sequence);
            $xactions[] = id(new PholioTransaction())
              ->setTransactionType(
                PholioTransactionType::TYPE_IMAGE_REPLACE)
              ->setNewValue($replace_image);
         } else if (!$existing_image) { // this is an add
            $add_image = id(new PholioImage())
              ->setFilePhid($file_phid)
              ->setName(strlen($title) ? $title : $file->getName())
              ->setDescription($description)
              ->setSequence($sequence);
            $xactions[] = id(new PholioTransaction())
              ->setTransactionType(PholioTransactionType::TYPE_IMAGE_FILE)
              ->setNewValue(
                array('+' => array($add_image)));
          } else {
            $xactions[] = id(new PholioTransaction())
              ->setTransactionType(PholioTransactionType::TYPE_IMAGE_NAME)
              ->setNewValue(
                array($existing_image->getPHID() => $title));
            $xactions[] = id(new PholioTransaction())
              ->setTransactionType(
                PholioTransactionType::TYPE_IMAGE_DESCRIPTION)
                ->setNewValue(
                  array($existing_image->getPHID() => $description));
            $existing_image->setSequence($sequence);
          }
          $sequence++;
        }
        foreach ($mock_images as $file_phid => $mock_image) {
          if (!isset($files[$file_phid]) && !isset($replaces[$file_phid])) {
            // this is an outright delete
            $xactions[] = id(new PholioTransaction())
              ->setTransactionType(PholioTransactionType::TYPE_IMAGE_FILE)
              ->setNewValue(
                array('-' => array($mock_image)));
          }
        }

        $mock->openTransaction();
          $editor = id(new PholioMockEditor())
            ->setContentSourceFromRequest($request)
            ->setContinueOnNoEffect(true)
            ->setActor($user);

          $xactions = $editor->applyTransactions($mock, $xactions);

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

    $image_elements = array();
    foreach ($mock_images as $mock_image) {
      $image_elements[] = id(new PholioUploadedImageView())
        ->setUser($user)
        ->setImage($mock_image);
    }

    $list_id = celerity_generate_unique_node_id();
    $drop_id = celerity_generate_unique_node_id();

    $list_control = phutil_tag(
      'div',
      array(
        'id' => $list_id,
        'class' => 'pholio-edit-list',
      ),
      $image_elements);

    $drop_control = phutil_tag(
      'div',
      array(
        'id' => $drop_id,
        'class' => 'pholio-edit-drop',
      ),
      'Drag and drop images here to add them to the mock.');

    Javelin::initBehavior(
      'pholio-mock-edit',
      array(
        'listID' => $list_id,
        'dropID' => $drop_id,
        'uploadURI' => '/file/dropupload/',
        'renderURI' => $this->getApplicationURI('image/upload/'),
        'pht' => array(
          'uploading' => pht('Uploading Image...'),
          'uploaded' => pht('Upload Complete...'),
          'undo' => pht('Undo'),
          'removed' => pht('This image will be removed from the mock.'),
        ),
      ));

    require_celerity_resource('pholio-edit-css');
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
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue($list_control))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue($drop_control)
          ->setError($e_images))
      ->appendChild($submit);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($this->getApplicationURI()));

    $content = array(
      $crumbs,
      $error_view,
      $form,
    );

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
