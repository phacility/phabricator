<?php

abstract class DiffusionBrowseQuery {

  private $request;

  protected $reason;
  protected $existedAtCommit;
  protected $deletedAtCommit;
  protected $validityOnly;

  const REASON_IS_FILE              = 'is-file';
  const REASON_IS_DELETED           = 'is-deleted';
  const REASON_IS_NONEXISTENT       = 'nonexistent';
  const REASON_BAD_COMMIT           = 'bad-commit';
  const REASON_IS_EMPTY             = 'empty';
  const REASON_IS_UNTRACKED_PARENT  = 'untracked-parent';

  final private function __construct() {
    // <private>
  }

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {

    $repository = $request->getRepository();

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        // TODO: Verify local-path?
        $query = new DiffusionGitBrowseQuery();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $query = new DiffusionMercurialBrowseQuery();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $query = new DiffusionSvnBrowseQuery();
        break;
      default:
        throw new Exception("Unsupported VCS!");
    }

    $query->request = $request;

    return $query;
  }

  final protected function getRequest() {
    return $this->request;
  }

  final public function getReasonForEmptyResultSet() {
    return $this->reason;
  }

  final public function getExistedAtCommit() {
    return $this->existedAtCommit;
  }

  final public function getDeletedAtCommit() {
    return $this->deletedAtCommit;
  }

  final public function loadPaths() {
    return $this->executeQuery();
  }

  final public function shouldOnlyTestValidity() {
    return $this->validityOnly;
  }

  final public function needValidityOnly($need_validity_only) {
    $this->validityOnly = $need_validity_only;
    return $this;
  }

  final public function renderReadme(array $results) {
    $drequest = $this->getRequest();

    $readme = null;
    foreach ($results as $result) {
      $file_type = $result->getFileType();
      if (($file_type != ArcanistDiffChangeType::FILE_NORMAL) &&
          ($file_type != ArcanistDiffChangeType::FILE_TEXT)) {
        // Skip directories, etc.
        continue;
      }

      $path = $result->getPath();

      if (preg_match('/^readme(|\.txt|\.remarkup|\.rainbow)$/i', $path)) {
        $readme = $result;
        break;
      }
    }

    if (!$readme) {
      return null;
    }

    $readme_request = DiffusionRequest::newFromDictionary(
      array(
        'repository'  => $drequest->getRepository(),
        'commit'      => $drequest->getStableCommitName(),
        'path'        => $readme->getFullPath(),
      ));

    $content_query = DiffusionFileContentQuery::newFromDiffusionRequest(
      $readme_request);
    $content_query->loadFileContent();
    $readme_content = $content_query->getRawData();

    if (preg_match('/\\.txt$/', $readme->getPath())) {
      $readme_content = phutil_escape_html($readme_content);
      $readme_content = nl2br($readme_content);

      $class = null;
    } else if (preg_match('/\\.rainbow$/', $readme->getPath())) {
      $highlighter = new PhutilRainbowSyntaxHighlighter();
      $readme_content = $highlighter
        ->getHighlightFuture($readme_content)
        ->resolve();
      $readme_content = nl2br($readme_content);

      require_celerity_resource('syntax-highlighting-css');
      $class = 'remarkup-code';
    } else {
      // Markup extensionless files as remarkup so we get links and such.
      $engine = PhabricatorMarkupEngine::newDiffusionMarkupEngine();
      $readme_content = $engine->markupText($readme_content);

      $class = 'phabricator-remarkup';
    }

    $readme_content = phutil_render_tag(
      'div',
      array(
        'class' => $class,
      ),
      $readme_content);

    return $readme_content;
  }

  abstract protected function executeQuery();
}
