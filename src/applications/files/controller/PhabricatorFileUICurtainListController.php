<?php

final class PhabricatorFileUICurtainListController
  extends PhabricatorFileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $object_phid = $request->getURIData('phid');

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $attachments = id(new PhabricatorFileAttachmentQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->needFiles(true)
      ->execute();

    $handles = $viewer->loadHandles(array($object_phid));
    $object_handle = $handles[$object_phid];

    $file_phids = mpull($attachments, 'getFilePHID');
    $file_handles = $viewer->loadHandles($file_phids);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($attachments as $attachment) {
      $file_phid = $attachment->getFilePHID();
      $handle = $file_handles[$file_phid];

      $item = id(new PHUIObjectItemView())
        ->setHeader($handle->getFullName())
        ->setHref($handle->getURI())
        ->setDisabled($handle->isDisabled());

      if ($handle->getImageURI()) {
        $item->setImageURI($handle->getImageURI());
      }

      $list->addItem($item);
    }

    return $this->newDialog()
      ->setViewer($viewer)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('Referenced Files'))
      ->setObjectList($list)
      ->addCancelButton($object_handle->getURI(), pht('Close'));
  }

}
