<?php

/**
 * NOTE: this class should only be used where local access to the repository
 * is guaranteed and NOT from within the Diffusion application. Diffusion
 * should use Conduit method 'diffusion.filecontentquery' to get this sort
 * of data.
 */
abstract class DiffusionFileContentQuery extends DiffusionQuery {

  private $needsBlame;
  private $fileContent;
  private $viewer;
  private $timeout;
  private $byteLimit;

  public function setTimeout($timeout) {
    $this->timeout = $timeout;
    return $this;
  }

  public function getTimeout() {
    return $this->timeout;
  }

  public function setByteLimit($byte_limit) {
    $this->byteLimit = $byte_limit;
    return $this;
  }

  public function getByteLimit() {
    return $this->byteLimit;
  }

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  abstract public function getFileContentFuture();
  abstract protected function executeQueryFromFuture(Future $future);

  final public function loadFileContentFromFuture(Future $future) {

    if ($this->timeout) {
      $future->setTimeout($this->timeout);
    }

    if ($this->getByteLimit()) {
      $future->setStdoutSizeLimit($this->getByteLimit());
    }

    try {
      $file_content = $this->executeQueryFromFuture($future);
    } catch (CommandException $ex) {
      if (!$future->getWasKilledByTimeout()) {
        throw $ex;
      }

      $message = pht(
        '<Attempt to load this file was terminated after %s second(s).>',
        $this->timeout);

      $file_content = new DiffusionFileContent();
      $file_content->setCorpus($message);
    }

    $this->fileContent = $file_content;

    $repository = $this->getRequest()->getRepository();
    $try_encoding = $repository->getDetail('encoding');
    if ($try_encoding) {
        $this->fileContent->setCorpus(
          phutil_utf8_convert(
            $this->fileContent->getCorpus(), 'UTF-8', $try_encoding));
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

  /**
   * Pretty hairy function. If getNeedsBlame is false, this returns
   *
   *   ($text_list, array(), array())
   *
   * Where $text_list is the raw file content with trailing new lines stripped.
   *
   * If getNeedsBlame is true, this returns
   *
   *   ($text_list, $line_rev_dict, $blame_dict)
   *
   * Where $text_list is just the lines of code -- the raw file content will
   * contain lots of blame data, $line_rev_dict is a dictionary of line number
   * => revision id, and $blame_dict is another complicated data structure.
   * In detail, $blame_dict contains [revision id][author] keys, as well
   * as [commit id][authorPhid] and [commit id][epoch] keys.
   *
   * @return ($text_list, $line_rev_dict, $blame_dict)
   */
  final public function getBlameData() {
    $raw_data = preg_replace('/\n$/', '', $this->getRawData());

    $text_list = array();
    $line_rev_dict = array();
    $blame_dict = array();

    if (!$this->getNeedsBlame()) {
      $text_list = explode("\n", $raw_data);
    } else if ($raw_data != '') {
      $lines = array();
      foreach (explode("\n", $raw_data) as $k => $line) {
        $lines[$k] = $this->tokenizeLine($line);

        list($rev_id, $author, $text) = $lines[$k];
        $text_list[$k] = $text;
        $line_rev_dict[$k] = $rev_id;
      }

      $line_rev_dict = $this->processRevList($line_rev_dict);

      foreach ($lines as $k => $line) {
        list($rev_id, $author, $text) = $line;
        $rev_id = $line_rev_dict[$k];

        if (!isset($blame_dict[$rev_id])) {
          $blame_dict[$rev_id]['author'] = $author;
        }
      }

      $repository = $this->getRequest()->getRepository();

      $commits = id(new DiffusionCommitQuery())
        ->setViewer($this->getViewer())
        ->withDefaultRepository($repository)
        ->withIdentifiers(array_unique($line_rev_dict))
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

    return array($text_list, $line_rev_dict, $blame_dict);
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

  public function getViewer() {
    return $this->viewer;
  }

  protected function processRevList(array $rev_list) {
    return $rev_list;
  }
}
