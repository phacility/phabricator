<?php

final class PhabricatorFileDetachController
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

    $handles = $viewer->loadHandles(
      array(
        $object_phid,
        $file_phid,
      ));

    $object_handle = $handles[$object_phid];
    $file_handle = $handles[$file_phid];
    $cancel_uri = $file_handle->getURI();

    $dialog = $this->newDialog()
      ->setViewer($viewer)
      ->setTitle(pht('Detach File'))
      ->addCancelButton($cancel_uri, pht('Close'));

    $file_link = phutil_tag('strong', array(), $file_handle->renderLink());
    $object_link = phutil_tag('strong', array(), $object_handle->renderLink());

    $attachment = id(new PhabricatorFileAttachmentQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->withFilePHIDs(array($file_phid))
      ->needFiles(true)
      ->withVisibleFiles(true)
      ->executeOne();
    if (!$attachment) {
      $body = pht(
        'The file %s is not attached to the object %s.',
        $file_link,
        $object_link);

      return $dialog->appendParagraph($body);
    }

    $mode_reference = PhabricatorFileAttachment::MODE_REFERENCE;
    if ($attachment->getAttachmentMode() === $mode_reference) {
      $body = pht(
        'The file %s is referenced by the object %s, but not attached to '.
        'it, so it can not be detached.',
        $file_link,
        $object_link);

      return $dialog->appendParagraph($body);
    }

    if (!$attachment->canDetach()) {
      $body = pht(
        'The file %s can not be detached from the object %s.',
        $file_link,
        $object_link);

      return $dialog->appendParagraph($body);
    }

    if (!$request->isDialogFormPost()) {
      $dialog->appendParagraph(
        pht(
          'Detach the file %s from the object %s?',
          $file_link,
          $object_link));

      $dialog->addSubmitButton(pht('Detach File'));

      return $dialog;
    }

    if (!($object instanceof PhabricatorApplicationTransactionInterface)) {
      $dialog->appendParagraph(
        pht(
          'This object (of class "%s") does not implement the required '.
          'interface ("%s"), so files can not be manually detached from it.',
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
          $file_phid => PhabricatorFileAttachment::MODE_DETACH,
        ));

    $editor->applyTransactions($object, $xactions);

    return $this->newRedirect()
      ->setURI($cancel_uri);
  }

}
