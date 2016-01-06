<?php

final class DiffusionSvnFileContentQuery extends DiffusionFileContentQuery {

  public function getFileContentFuture() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    return $repository->getRemoteCommandFuture(
      'cat %s',
      $repository->getSubversionPathURI($path, $commit));
  }

  protected function executeQueryFromFuture(Future $future) {
    try {
      list($corpus) = $future->resolvex();
    } catch (CommandException $ex) {
      $stderr = $ex->getStdErr();
      if (preg_match('/path not found$/', trim($stderr))) {
        // TODO: Improve user experience for this. One way to end up here
        // is to have the parser behind and look at a file which was recently
        // nuked; Diffusion will think it still exists and try to grab content
        // at HEAD.
        throw new Exception(
          pht(
            'Failed to retrieve file content from Subversion. The file may '.
            'have been recently deleted, or the Diffusion cache may be out of '.
            'date.'));
      } else {
        throw $ex;
      }
    }

    $file_content = new DiffusionFileContent();
    $file_content->setCorpus($corpus);

    return $file_content;
  }

}
