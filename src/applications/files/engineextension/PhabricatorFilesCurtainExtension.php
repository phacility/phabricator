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

    $ref_list = id(new PHUICurtainObjectRefListView())
      ->setViewer($viewer)
      ->setEmptyMessage(pht('None'));

    $view_capability = PhabricatorPolicyCapability::CAN_VIEW;
    $object_policies = PhabricatorPolicyQuery::loadPolicies(
      $viewer,
      $object);
    $object_policy = idx($object_policies, $view_capability);

    foreach ($visible_attachments as $attachment) {
      $file_phid = $attachment->getFilePHID();
      $handle = $handles[$file_phid];

      $ref = $ref_list->newObjectRefView()
        ->setHandle($handle);

      $file = $attachment->getFile();
      if (!$file) {
        // ...
      } else {
        if (!$attachment->isPolicyAttachment()) {
          $file_policies = PhabricatorPolicyQuery::loadPolicies(
            $viewer,
            $file);
          $file_policy = idx($file_policies, $view_capability);

          if ($object_policy->isStrongerThanOrEqualTo($file_policy)) {
            // The file is not attached to the object, but the file policy
            // allows anyone who can see the object to see the file too, so
            // there is no material problem with the file not being attached.
          } else {
            $attach_uri = urisprintf(
              '/file/ui/curtain/attach/%s/%s/',
              $object->getPHID(),
              $file->getPHID());

            $attached_link = javelin_tag(
              'a',
              array(
                'href' => $attach_uri,
                'sigil' => 'workflow',
              ),
              pht('File Not Attached'));

            $ref->setExiled(
              true,
              $attached_link);
          }
        }
      }

      $epoch = $attachment->getDateCreated();
      $ref->setEpoch($epoch);
    }

    $show_all = (count($visible_attachments) < count($attachments));
    if ($show_all) {
      $view_all_uri = urisprintf(
        '/file/ui/curtain/list/%s/',
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
      ->setHeaderText(pht('Referenced Files'))
      ->setOrder(15000)
      ->appendChild($ref_list);
  }


}
