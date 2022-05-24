<?php

final class PhabricatorFileAttachmentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $objectPHIDs;
  private $filePHIDs;
  private $needFiles;
  private $visibleFiles;

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withFilePHIDs(array $file_phids) {
    $this->filePHIDs = $file_phids;
    return $this;
  }

  public function withVisibleFiles($visible_files) {
    $this->visibleFiles = $visible_files;
    return $this;
  }

  public function needFiles($need) {
    $this->needFiles = $need;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorFileAttachment();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'attachments.objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->filePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'attachments.filePHID IN (%Ls)',
        $this->filePHIDs);
    }

    return $where;
  }

  protected function willFilterPage(array $attachments) {
    $viewer = $this->getViewer();
    $object_phids = array();

    foreach ($attachments as $attachment) {
      $object_phid = $attachment->getObjectPHID();
      $object_phids[$object_phid] = $object_phid;
    }

    if ($object_phids) {
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->setParentQuery($this)
        ->withPHIDs($object_phids)
        ->execute();
      $objects = mpull($objects, null, 'getPHID');
    } else {
      $objects = array();
    }

    foreach ($attachments as $key => $attachment) {
      $object_phid = $attachment->getObjectPHID();
      $object = idx($objects, $object_phid);

      if (!$object) {
        $this->didRejectResult($attachment);
        unset($attachments[$key]);
        continue;
      }

      $attachment->attachObject($object);
    }

    if ($this->needFiles) {
      $file_phids = array();
      foreach ($attachments as $attachment) {
        $file_phid = $attachment->getFilePHID();
        $file_phids[$file_phid] = $file_phid;
      }

      if ($file_phids) {
        $files = id(new PhabricatorFileQuery())
          ->setViewer($viewer)
          ->setParentQuery($this)
          ->withPHIDs($file_phids)
          ->execute();
        $files = mpull($files, null, 'getPHID');
      } else {
        $files = array();
      }

      foreach ($attachments as $key => $attachment) {
        $file_phid = $attachment->getFilePHID();
        $file = idx($files, $file_phid);

        if ($this->visibleFiles && !$file) {
          $this->didRejectResult($attachment);
          unset($attachments[$key]);
          continue;
        }

        $attachment->attachFile($file);
      }
    }

    return $attachments;
  }

  protected function getPrimaryTableAlias() {
    return 'attachments';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorFilesApplication';
  }

}
