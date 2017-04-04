<?php

final class FileInfoConduitAPIMethod extends FileConduitAPIMethod {

  public function getAPIMethodName() {
    return 'file.info';
  }

  public function getMethodDescription() {
    return pht('Get information about a file.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "file.search" instead.');
  }

  protected function defineParamTypes() {
    return array(
      'phid' => 'optional phid',
      'id'   => 'optional id',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-NOT-FOUND' => pht('No such file exists.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $phid = $request->getValue('phid');
    $id   = $request->getValue('id');

    $query = id(new PhabricatorFileQuery())
      ->setViewer($request->getUser());
    if ($id) {
      $query->withIDs(array($id));
    } else {
      $query->withPHIDs(array($phid));
    }

    $file = $query->executeOne();

    if (!$file) {
      throw new ConduitException('ERR-NOT-FOUND');
    }

    $uri = $file->getInfoURI();

    return array(
      'id'            => $file->getID(),
      'phid'          => $file->getPHID(),
      'objectName'    => 'F'.$file->getID(),
      'name'          => $file->getName(),
      'mimeType'      => $file->getMimeType(),
      'byteSize'      => $file->getByteSize(),
      'authorPHID'    => $file->getAuthorPHID(),
      'dateCreated'   => $file->getDateCreated(),
      'dateModified'  => $file->getDateModified(),
      'uri'           => PhabricatorEnv::getProductionURI($uri),
    );
  }

}
