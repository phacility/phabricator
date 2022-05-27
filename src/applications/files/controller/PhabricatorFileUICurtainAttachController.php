<?php

final class PhabricatorFileUICurtainAttachController
  extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $object_phid = $request->getURIData('objectPHID');
    $file_phid = $request->getURIData('filePHID');

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $attachment = id(new PhabricatorFileAttachmentQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->withFilePHIDs(array($file_phid))
      ->needFiles(true)
      ->withVisibleFiles(true)
      ->executeOne();
    if (!$attachment) {
      return new Aphront404Response();
    }

    $handles = $viewer->loadHandles(
      array(
        $object_phid,
        $file_phid,
      ));

    $object_handle = $handles[$object_phid];
    $file_handle = $handles[$file_phid];
    $cancel_uri = $object_handle->getURI();

    $dialog = $this->newDialog()
      ->setViewer($viewer)
      ->setTitle(pht('Attach File'))
      ->addCancelButton($cancel_uri, pht('Close'));

    $file_link = phutil_tag('strong', array(), $file_handle->renderLink());
    $object_link = phutil_tag('strong', array(), $object_handle->renderLink());

    if ($attachment->isPolicyAttachment()) {
      $body = pht(
        'The file %s is already attached to the object %s.',
        $file_link,
        $object_link);

      return $dialog->appendParagraph($body);
    }

    if (!$request->isDialogFormPost()) {
      $dialog->appendRemarkup(
        pht(
          '(WARNING) This file is referenced by this object, but '.
          'not formally attached to it. Users who can see the object may '.
          'not be able to see the file.'));

      $dialog->appendParagraph(
        pht(
          'Do you want to attach the file %s to the object %s?',
          $file_link,
          $object_link));

      $dialog->addSubmitButton(pht('Attach File'));

      return $dialog;
    }

    if (!$request->getBool('confirm')) {
      $dialog->setTitle(pht('Confirm File Attachment'));

      $dialog->addHiddenInput('confirm', 1);

      $dialog->appendRemarkup(
        pht(
          '(IMPORTANT) If you attach this file to this object, any user who '.
          'has permission to view the object will be able to view and '.
          'download the file!'));

      $dialog->appendParagraph(
        pht(
          'Really attach the file %s to the object %s, allowing any user '.
          'who can view the object to view and download the file?',
          $file_link,
          $object_link));

      $dialog->addSubmitButton(pht('Grant Permission'));

      return $dialog;
    }

    if (!($object instanceof PhabricatorApplicationTransactionInterface)) {
      $dialog->appendParagraph(
        pht(
          'This object (of class "%s") does not implement the required '.
          'interface ("%s"), so files can not be manually attached to it.',
          get_class($object),
          'PhabricatorApplicationTransactionInterface'));

      return $dialog;
    }

    $editor = $object->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $template = $object->getApplicationTransactionTemplate();

    $xactions = array();

    $xactions[] = id(clone $template)
      ->setTransactionType(PhabricatorTransactions::TYPE_FILE)
      ->setNewValue(
        array(
          $file_phid => PhabricatorFileAttachment::MODE_ATTACH,
        ));

    $editor->applyTransactions($object, $xactions);

    return $this->newRedirect()
      ->setURI($cancel_uri);
  }

}
