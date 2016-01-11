<?php

final class DiffusionFileContentQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.filecontentquery';
  }

  public function getMethodDescription() {
    return pht('Retrieve file content from a repository.');
  }

  protected function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'path' => 'required string',
      'commit' => 'required string',
      'timeout' => 'optional int',
      'byteLimit' => 'optional int',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();

    $file_query = DiffusionFileContentQuery::newFromDiffusionRequest($drequest);

    $timeout = $request->getValue('timeout');
    if ($timeout) {
      $file_query->setTimeout($timeout);
    }

    $byte_limit = $request->getValue('byteLimit');
    if ($byte_limit) {
      $file_query->setByteLimit($byte_limit);
    }

    $content = $file_query->execute();

    $too_slow = (bool)$file_query->getExceededTimeLimit();
    $too_huge = (bool)$file_query->getExceededByteLimit();

    $file_phid = null;
    if (!$too_slow && !$too_huge) {
      $file = $this->newFile($drequest, $content);
      $file_phid = $file->getPHID();
    }

    return array(
      'tooSlow' => $too_slow,
      'tooHuge' => $too_huge,
      'filePHID' => $file_phid,
    );
  }

  private function newFile(DiffusionRequest $drequest, $content) {
    $path = $drequest->getPath();
    $name = basename($path);

    $repository = $drequest->getRepository();
    $repository_phid = $repository->getPHID();

    $file = PhabricatorFile::buildFromFileDataOrHash(
      $content,
      array(
        'name' => $name,
        'ttl' => time() + phutil_units('48 hours in seconds'),
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
      ));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $file->attachToObject($repository_phid);
    unset($unguarded);

    return $file;
  }

}
