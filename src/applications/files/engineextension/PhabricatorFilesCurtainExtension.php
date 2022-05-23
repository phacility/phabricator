<?php

final class PhabricatorFilesCurtainExtension
  extends PHUICurtainExtension {

  const EXTENSIONKEY = 'files.files';

  public function shouldEnableForObject($object) {
    return true;
  }

  public function getExtensionApplication() {
    return new PhabricatorFilesApplication();
  }

  public function buildCurtainPanel($object) {
    $viewer = $this->getViewer();

    $attachment_table = new PhabricatorFileAttachment();
    $attachment_conn = $attachment_table->establishConnection('r');

    $exact_limit = 100;
    $visible_limit = 8;

    $attachments = id(new PhabricatorFileAttachmentQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->setLimit($exact_limit + 1)
      ->needFiles(true)
      ->execute();

    $visible_attachments = array_slice($attachments, 0, $visible_limit, true);
    $visible_phids = mpull($visible_attachments, 'getFilePHID');

    $handles = $viewer->loadHandles($visible_phids);

    PhabricatorPolicyFilterSet::loadHandleViewCapabilities(
      $viewer,
      $handles,
      array($object));

    $ref_list = id(new PHUICurtainObjectRefListView())
      ->setViewer($viewer)
      ->setEmptyMessage(pht('None'));

    foreach ($visible_attachments as $attachment) {
      $file_phid = $attachment->getFilePHID();
      $handle = $handles[$file_phid];

      $ref = $ref_list->newObjectRefView()
        ->setHandle($handle);

      if ($handle->hasCapabilities()) {
        if (!$handle->hasViewCapability($object)) {
          $ref->setExiled(true);
        }
      }

      $epoch = $attachment->getDateCreated();
      $ref->setEpoch($epoch);
    }

    $show_all = (count($visible_attachments) < count($attachments));
    if ($show_all) {
      $view_all_uri = urisprintf(
        '/file/ui/curtainlist/%s/',
        $object->getPHID());

      $loaded_count = count($attachments);
      if ($loaded_count > $exact_limit) {
        $link_text = pht('View All Files');
      } else {
        $link_text = pht('View All %d Files', new PhutilNumber($loaded_count));
      }

      $ref_list->newTailLink()
        ->setURI($view_all_uri)
        ->setText($link_text)
        ->setWorkflow(true);
    }

    return $this->newPanel()
      ->setHeaderText(pht('Attached Files'))
      ->setOrder(15000)
      ->appendChild($ref_list);
  }


}
