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
            $message
          );
          break;
        case 'metadata':
          $xactions = array();
          $images = $request->getArr('image');
          if ($images) {
            // just take the first one
            $file_phid = reset($images);
            $file = id(new PhabricatorFileQuery())
              ->setViewer($user)
              ->withPHIDs(array($file_phid))
              ->executeOne();
            $okay = $file->isTransformableImage();
            if ($okay) {
              $xformer = new PhabricatorImageTransformer();
              $xformed = $xformer->executeThumbTransform(
                $file,
                $x = 50,
                $y = 50);
              $image_phid = $xformed->getPHID();
              $xactions[] = id(new ConpherenceTransaction())
                ->setTransactionType(ConpherenceTransactionType::TYPE_PICTURE)
                ->setNewValue($image_phid);
            } else {
              $e_file[] = $file;
              $errors[] =
                pht('This server only supports these image formats: %s.',
                  implode(', ', $supported_formats));
            }
          }
          $title = $request->getStr('title');
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
          'That was a non-update. Try cancel.'
        );
      }
    }

    if ($updated) {
      return id(new AphrontRedirectResponse())->setURI(
        $this->getApplicationURI($conpherence_id.'/')
      );
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
        ->setValue($conpherence->getTitle())
      )
      ->appendChild(
        id(new AphrontFormMarkupControl())
        ->setLabel(pht('Image'))
        ->setValue(phutil_render_tag(
          'img',
          array(
            'src' => $conpherence->loadImageURI(),
          ))
        )
      )
      ->appendChild(
        id(new AphrontFormDragAndDropUploadControl())
        ->setLabel(pht('Change Image'))
        ->setName('image')
        ->setValue($e_file)
        ->setCaption('Supported formats: '.implode(', ', $supported_formats))
        );

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
        ->addCancelButton($this->getApplicationURI($conpherence->getID().'/'))
      );
  }
}
