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
    $e_image = null;
    $errors = array();
    if ($request->isFormPost()) {
      $content_source = PhabricatorContentSource::newForSource(
        PhabricatorContentSource::SOURCE_WEB,
        array(
          'ip' => $request->getRemoteAddr()
        ));

      $action = $request->getStr('action');
      switch ($action) {
        case 'message':
          $message = $request->getStr('text');
          $files = array();
          $file_phids =
            PhabricatorMarkupEngine::extractFilePHIDsFromEmbeddedFiles(
              array($message)
            );
          if ($file_phids) {
            $files = id(new PhabricatorFileQuery())
              ->setViewer($user)
              ->withPHIDs($file_phids)
              ->execute();
          }
          $xactions = array();
          if ($files) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(ConpherenceTransactionType::TYPE_FILES)
              ->setNewValue(array('+' => mpull($files, 'getPHID')));
          }
          $xactions[] = id(new ConpherenceTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
            ->attachComment(
              id(new ConpherenceTransactionComment())
              ->setContent($message)
              ->setConpherencePHID($conpherence->getPHID())
            );
          $time = time();
          $conpherence->openTransaction();
          $xactions = id(new ConpherenceEditor())
            ->setContentSource($content_source)
            ->setActor($user)
            ->applyTransactions($conpherence, $xactions);
          $last_xaction = end($xactions);
          $xaction_phid = $last_xaction->getPHID();
          $behind = ConpherenceParticipationStatus::BEHIND;
          $up_to_date = ConpherenceParticipationStatus::UP_TO_DATE;
          $participants = $conpherence->getParticipants();
          foreach ($participants as $phid => $participant) {
            if ($phid != $user->getPHID()) {
              if ($participant->getParticipationStatus() != $behind) {
                $participant->setBehindTransactionPHID($xaction_phid);
              }
              $participant->setParticipationStatus($behind);
              $participant->setDateTouched($time);
            } else {
              $participant->setParticipationStatus($up_to_date);
              $participant->setDateTouched($time);
            }
            $participant->save();
          }
          $updated = $conpherence->saveTransaction();
          break;
        case 'metadata':
          $xactions = array();
          $default_image = $request->getExists('default_image');
          if ($default_image) {
            $image_phid = null;
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(ConpherenceTransactionType::TYPE_PICTURE)
              ->setNewValue($image_phid);
          } else if (!empty($_FILES['image'])) {
            $err = idx($_FILES['image'], 'error');
            if ($err != UPLOAD_ERR_NO_FILE) {
              $file = PhabricatorFile::newFromPHPUpload(
                $_FILES['image'],
                array(
                  'authorPHID' => $user->getPHID(),
                ));
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
                $e_image = pht('Not Supported');
                $errors[] =
                  pht('This server only supports these image formats: %s.',
                  implode(', ', $supported_formats));
              }
            }
          }
          $title = $request->getStr('title');
          if ($title != $conpherence->getTitle()) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(ConpherenceTransactionType::TYPE_TITLE)
              ->setNewValue($title);
          }

          if ($xactions) {
            $conpherence->openTransaction();
            $xactions = id(new ConpherenceEditor())
              ->setContentSource($content_source)
              ->setActor($user)
              ->setContinueOnNoEffect(true)
              ->applyTransactions($conpherence, $xactions);
            $updated = $conpherence->saveTransaction();
          } else if (empty($errors)) {
            $errors[] = pht(
              'That was a non-update. Try cancel.'
            );
          }
          break;
        default:
          throw new Exception('Unknown action: '.$action);
          break;
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
        ->setValue(phutil_tag(
          'img',
          array(
            'src' => $conpherence->loadImageURI(),
          ))
        )
      )
      ->appendChild(
        id(new AphrontFormImageControl())
        ->setLabel(pht('Change Image'))
        ->setName('image')
        ->setCaption('Supported formats: '.implode(', ', $supported_formats))
        ->setError($e_image)
      );

    // TODO -- fix javelin so we can upload files from a workflow
    require_celerity_resource('conpherence-update-css');
    return $this->buildStandardPageResponse(
      array(
        $error_view,
        id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Update Conpherence'))
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setSubmitURI($this->getApplicationURI('update/'.$conpherence_id.'/'))
        ->addHiddenInput('action', 'metadata')
        ->appendChild($form)
        ->addSubmitButton()
        ->addCancelButton($this->getApplicationURI($conpherence->getID().'/')),
      ),
      array()
    );
  }
}
