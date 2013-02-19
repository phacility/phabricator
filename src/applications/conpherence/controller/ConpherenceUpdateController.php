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

    $updated = false;
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

      $action = $request->getStr('action');
      switch ($action) {
        case 'message':
          $message = $request->getStr('text');
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
          }
          if ($title != $conpherence->getTitle()) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(ConpherenceTransactionType::TYPE_TITLE)
              ->setNewValue($title);
          }
          break;
        default:
          throw new Exception('Unknown action: '.$action);
          break;
      }
      if ($xactions) {
        try {
          $xactions = $editor->applyTransactions($conpherence, $xactions);
          $updated = true;
        } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
          return id(new PhabricatorApplicationTransactionNoEffectResponse())
            ->setCancelURI($this->getApplicationURI($conpherence_id.'/'))
            ->setException($ex);
        }
      } else if (empty($errors)) {
        $errors[] = pht(
          'That was a non-update. Try cancel.');
      }
    }

    if ($updated) {
      return id(new AphrontRedirectResponse())->setURI(
        $this->getApplicationURI($conpherence_id.'/'));
    }

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Errors editing conpherence.'))
        ->setInsideDialogue(true)
        ->setErrors($errors);
    }

    $form = id(new AphrontFormLayoutView())
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
    return id(new AphrontDialogResponse())
      ->setDialog(
        id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Update Conpherence'))
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setSubmitURI($this->getApplicationURI('update/'.$conpherence_id.'/'))
        ->addHiddenInput('action', 'metadata')
        ->appendChild($error_view)
        ->appendChild($form)
        ->addSubmitButton()
        ->addCancelButton($this->getApplicationURI($conpherence->getID().'/')));
  }
}
