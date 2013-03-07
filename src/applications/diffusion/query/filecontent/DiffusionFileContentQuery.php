<?php

abstract class DiffusionFileContentQuery extends DiffusionQuery {

  private $needsBlame;
  private $fileContent;
  private $viewer;

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  abstract public function getFileContentFuture();
  abstract protected function executeQueryFromFuture(Future $future);

  final public function loadFileContentFromFuture(Future $future) {
    $this->fileContent = $this->executeQueryFromFuture($future);

    $repository = $this->getRequest()->getRepository();
    $try_encoding = $repository->getDetail('encoding');
    if ($try_encoding) {
        $this->fileContent->setCorpus(
          phutil_utf8_convert(
            $this->fileContent->getCorpus(), "UTF-8", $try_encoding));
    }

    return $this->fileContent;
  }

  final protected function executeQuery() {
    return $this->loadFileContentFromFuture($this->getFileContentFuture());
  }

  final public function loadFileContent() {
    return $this->executeQuery();
  }

  final public function getRawData() {
    return $this->fileContent->getCorpus();
  }

  final public function getBlameData() {
    $raw_data = preg_replace('/\n$/', '', $this->getRawData());

    $text_list = array();
    $rev_list = array();
    $blame_dict = array();

    if (!$this->getNeedsBlame()) {
      $text_list = explode("\n", $raw_data);
    } else if ($raw_data != '') {
      $lines = array();
      foreach (explode("\n", $raw_data) as $k => $line) {
        $lines[$k] = $this->tokenizeLine($line);

        list($rev_id, $author, $text) = $lines[$k];
        $text_list[$k] = $text;
        $rev_list[$k] = $rev_id;
      }

      $rev_list = $this->processRevList($rev_list);

      foreach ($lines as $k => $line) {
        list($rev_id, $author, $text) = $line;
        $rev_id = $rev_list[$k];

        if (!isset($blame_dict[$rev_id])) {
          $blame_dict[$rev_id]['author'] = $author;
        }
      }

      $repository = $this->getRequest()->getRepository();

      $commits = id(new PhabricatorAuditCommitQuery())
        ->withIdentifiers(
          $repository->getID(),
          array_unique($rev_list))
        ->execute();

      foreach ($commits as $commit) {
        $blame_dict[$commit->getCommitIdentifier()]['epoch'] =
          $commit->getEpoch();
      }

      if ($commits) {
        $commits_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
          'commitID IN (%Ls)',
          mpull($commits, 'getID'));

        foreach ($commits_data as $data) {
          $author_phid = $data->getCommitDetail('authorPHID');
          if (!$author_phid) {
            continue;
          }
          $commit = $commits[$data->getCommitID()];
          $commit_identifier = $commit->getCommitIdentifier();
          $blame_dict[$commit_identifier]['authorPHID'] = $author_phid;
        }
      }

   }

    return array($text_list, $rev_list, $blame_dict);
  }

  abstract protected function tokenizeLine($line);

  public function setNeedsBlame($needs_blame) {
    $this->needsBlame = $needs_blame;
    return $this;
  }

  public function getNeedsBlame() {
    return $this->needsBlame;
  }

  public function setViewer(PhabricatorUser $user) {
    $this->viewer = $user;
    return $this;
  }

  protected function processRevList(array $rev_list) {
    return $rev_list;
  }
}
