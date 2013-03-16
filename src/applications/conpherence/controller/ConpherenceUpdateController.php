<?php

/**
 * @group conpherence
 */
final class ConpherenceUpdateController extends
  ConpherenceController {

  private $conpherenceID;

  public function setConpherenceID($conpherence_id) {
    $this->conpherenceID = $conpherence_id;
    return $this;
  }
  public function getConpherenceID() {
    return $this->conpherenceID;
  }
  public function willProcessRequest(array $data) {
    $this->setConpherenceID(idx($data, 'id'));
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $conpherence_id = $this->getConpherenceID();
    if (!$conpherence_id) {
      return new Aphront404Response();
    }

    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withIDs(array($conpherence_id))
      ->needOrigPics(true)
      ->needHeaderPics(true)
      ->executeOne();
    $supported_formats = PhabricatorFile::getTransformableImageFormats();

    $action = $request->getStr('action', 'metadata');
    $latest_transaction_id = null;
    $fancy_ajax_style = true;
    $error_view = null;
    $e_file = array();
    $errors = array();
    if ($request->isFormPost()) {
      $content_source = PhabricatorContentSource::newForSource(
        PhabricatorContentSource::SOURCE_WEB,
        array(
          'ip' => $request->getRemoteAddr()
        ));
      $editor = id(new ConpherenceEditor())
        ->setContinueOnNoEffect($request->isContinueRequest())
        ->setContentSource($content_source)
        ->setActor($user);

      switch ($action) {
        case 'message':
          $message = $request->getStr('text');
          $latest_transaction_id = $request->getInt('latest_transaction_id');
          $xactions = $editor->generateTransactionsFromText(
            $conpherence,
            $message);
          break;
        case 'metadata':
          $xactions = array();
          $top = $request->getInt('image_y');
          $left = $request->getInt('image_x');
          $file_id = $request->getInt('file_id');
          $title = $request->getStr('title');
          $updated = false;
          if ($file_id) {
            $orig_file = id(new PhabricatorFileQuery())
              ->setViewer($user)
              ->withIDs(array($file_id))
              ->executeOne();
            $okay = $orig_file->isTransformableImage();
            if ($okay) {
              $xactions[] = id(new ConpherenceTransaction())
                ->setTransactionType(ConpherenceTransactionType::TYPE_PICTURE)
                ->setNewValue($orig_file->getPHID());
              // do 2 transformations "crudely"
              $xformer = new PhabricatorImageTransformer();
              $header_file = $xformer->executeConpherenceTransform(
                $orig_file,
                0,
                0,
                ConpherenceImageData::HEAD_WIDTH,
                ConpherenceImageData::HEAD_HEIGHT);
              // this is handled outside the editor for now. no particularly
              // good reason to move it inside
              $conpherence->setImagePHIDs(
                array(
                  ConpherenceImageData::SIZE_HEAD => $header_file->getPHID(),
                ));
              $conpherence->setImages(
                array(
                  ConpherenceImageData::SIZE_HEAD => $header_file,
                ));
            } else {
              $e_file[] = $orig_file;
              $errors[] =
                pht('This server only supports these image formats: %s.',
                  implode(', ', $supported_formats));
            }
            // use the existing title in this image upload case
            $title = $conpherence->getTitle();
            $updated = true;
            $fancy_ajax_style = false;
          } else if ($top !== null || $left !== null) {
            $file = $conpherence->getImage(ConpherenceImageData::SIZE_ORIG);
            $xformer = new PhabricatorImageTransformer();
            $xformed = $xformer->executeConpherenceTransform(
              $file,
              $top,
              $left,
              ConpherenceImageData::HEAD_WIDTH,
              ConpherenceImageData::HEAD_HEIGHT);
            $image_phid = $xformed->getPHID();

            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(
                ConpherenceTransactionType::TYPE_PICTURE_CROP)
                ->setNewValue($image_phid);
            $updated = true;
          }
          if ($title != $conpherence->getTitle()) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(ConpherenceTransactionType::TYPE_TITLE)
              ->setNewValue($title);
            $updated = true;
          }
          if (!$updated && $request->isContinueRequest()) {
            $errors[] = pht(
              'That was a non-update. Try cancel.');
          }
          break;
        default:
          throw new Exception('Unknown action: '.$action);
          break;
      }
      if ($xactions) {
        try {
          $xactions = $editor->applyTransactions($conpherence, $xactions);
          if ($fancy_ajax_style) {
            $content = $this->loadAndRenderUpdates(
              $conpherence_id,
              $latest_transaction_id);
            return id(new AphrontAjaxResponse())
              ->setContent($content);
          } else {
            return id(new AphrontRedirectResponse())
              ->setURI($this->getApplicationURI($conpherence->getID().'/'));
          }
        } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
          return id(new PhabricatorApplicationTransactionNoEffectResponse())
            ->setCancelURI($this->getApplicationURI($conpherence_id.'/'))
            ->setException($ex);
        }
      }
    }

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Errors editing conpherence.'))
        ->setInsideDialogue(true)
        ->setErrors($errors);
    }

    switch ($action) {
      case 'metadata':
      default:
        $dialogue = $this->renderMetadataDialogue($conpherence, $error_view);
        break;
    }

    return id(new AphrontDialogResponse())
      ->setDialog($dialogue
        ->setUser($user)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setSubmitURI($this->getApplicationURI('update/'.$conpherence_id.'/'))
        ->addSubmitButton()
        ->addCancelButton($this->getApplicationURI($conpherence->getID().'/')));

  }

  private function renderMetadataDialogue(
    ConpherenceThread $conpherence,
    $error_view) {

    $form = id(new AphrontFormLayoutView())
      ->appendChild($error_view)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Title'))
        ->setName('title')
        ->setValue($conpherence->getTitle()));

    $image = $conpherence->getImage(ConpherenceImageData::SIZE_ORIG);
    if ($image) {
      $form
        ->appendChild(
          id(new AphrontFormMarkupControl())
          ->setLabel(pht('Image'))
          ->setValue(phutil_tag(
            'img',
            array(
              'src' =>
              $conpherence->loadImageURI(ConpherenceImageData::SIZE_HEAD),
              ))))
              ->appendChild(
                id(new AphrontFormCropControl())
                ->setLabel(pht('Crop Image'))
                ->setValue($image)
                ->setWidth(ConpherenceImageData::HEAD_WIDTH)
                ->setHeight(ConpherenceImageData::HEAD_HEIGHT))
                ->appendChild(
                  id(new ConpherenceFormDragAndDropUploadControl())
                  ->setLabel(pht('Change Image')));
    } else {
      $form
        ->appendChild(
          id(new ConpherenceFormDragAndDropUploadControl())
          ->setLabel(pht('Image')));
    }

    require_celerity_resource('conpherence-update-css');
    return id(new AphrontDialogView())
      ->setTitle(pht('Update Conpherence'))
      ->addHiddenInput('action', 'metadata')
      ->addHiddenInput('__continue__', true)
      ->appendChild($form);
  }

  private function loadAndRenderUpdates(
    $conpherence_id,
    $latest_transaction_id) {

    $user = $this->getRequest()->getUser();
    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->setAfterID($latest_transaction_id)
      ->needHeaderPics(true)
      ->needWidgetData(true)
      ->withIDs(array($conpherence_id))
      ->executeOne();

    $data = $this->renderConpherenceTransactions($conpherence);
    $rendered_transactions = $data['transactions'];
    $new_latest_transaction_id = $data['latest_transaction_id'];

    $selected = true;
    $nav_item = $this->buildConpherenceMenuItem(
      $conpherence,
      '-nav-item',
      $selected);
    $menu_item = $this->buildConpherenceMenuItem(
      $conpherence,
      '-menu-item',
      $selected);

    $header = $this->buildHeaderPaneContent($conpherence);

    $file_widget = id(new ConpherenceFileWidgetView())
      ->setUser($this->getRequest()->getUser())
      ->setConpherence($conpherence)
      ->setUpdateURI(
        $this->getApplicationURI('update/'.$conpherence->getID().'/'));

    $content = array(
      'transactions' => $rendered_transactions,
      'latest_transaction_id' => $new_latest_transaction_id,
      'menu_item' => $menu_item->render(),
      'nav_item' => $nav_item->render(),
      'conpherence_phid' => $conpherence->getPHID(),
      'header' => $header,
      'file_widget' => $file_widget->render()
    );
    return $content;
  }

}
