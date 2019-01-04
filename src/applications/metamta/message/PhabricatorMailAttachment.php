<?php

final class PhabricatorMailAttachment extends Phobject {

  private $data;
  private $filename;
  private $mimetype;
  private $file;
  private $filePHID;

  public function __construct($data, $filename, $mimetype) {
    $this->setData($data);
    $this->setFilename($filename);
    $this->setMimeType($mimetype);
  }

  public function getData() {
    return $this->data;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getFilename() {
    return $this->filename;
  }

  public function setFilename($filename) {
    $this->filename = $filename;
    return $this;
  }

  public function getMimeType() {
    return $this->mimetype;
  }

  public function setMimeType($mimetype) {
    $this->mimetype = $mimetype;
    return $this;
  }

  public function toDictionary() {
    if (!$this->file) {
      $iterator = new ArrayIterator(array($this->getData()));

      $source = id(new PhabricatorIteratorFileUploadSource())
        ->setName($this->getFilename())
        ->setViewPolicy(PhabricatorPolicies::POLICY_NOONE)
        ->setMIMEType($this->getMimeType())
        ->setIterator($iterator);

      $this->file = $source->uploadFile();
    }

    return array(
      'filename' => $this->getFilename(),
      'mimetype' => $this->getMimeType(),
      'filePHID' => $this->file->getPHID(),
    );
  }

  public static function newFromDictionary(array $dict) {
    $file = null;

    $file_phid = idx($dict, 'filePHID');
    if ($file_phid) {
      $file = id(new PhabricatorFileQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs(array($file_phid))
        ->executeOne();
      if ($file) {
        $dict['data'] = $file->loadFileData();
      }
    }

    $attachment = new self(
      idx($dict, 'data'),
      idx($dict, 'filename'),
      idx($dict, 'mimetype'));

    if ($file) {
      $attachment->file = $file;
    }

    return $attachment;
  }

}
