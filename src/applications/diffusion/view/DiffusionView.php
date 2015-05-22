<?php

abstract class DiffusionView extends AphrontView {

  private $diffusionRequest;

  final public function setDiffusionRequest(DiffusionRequest $request) {
    $this->diffusionRequest = $request;
    return $this;
  }

  final public function getDiffusionRequest() {
    return $this->diffusionRequest;
  }

  final public function linkChange(
    $change_type,
    $file_type,
    $path = null,
    $commit_identifier = null) {

    $text = DifferentialChangeType::getFullNameForChangeType($change_type);
    if ($change_type == DifferentialChangeType::TYPE_CHILD) {
      // TODO: Don't link COPY_AWAY without a direct change.
      return $text;
    }
    if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
      return $text;
    }

    $href = $this->getDiffusionRequest()->generateURI(
      array(
        'action'  => 'change',
        'path'    => $path,
        'commit'  => $commit_identifier,
      ));

    return phutil_tag(
      'a',
      array(
        'href' => $href,
      ),
      $text);
  }

  final public function linkHistory($path) {
    $href = $this->getDiffusionRequest()->generateURI(
      array(
        'action' => 'history',
        'path'   => $path,
      ));

    return phutil_tag(
      'a',
      array(
        'href' => $href,
      ),
      pht('History'));
  }

  final public function linkBrowse($path, array $details = array()) {

    $href = $this->getDiffusionRequest()->generateURI(
      $details + array(
        'action' => 'browse',
        'path'   => $path,
      ));

    if (isset($details['text'])) {
      $text = $details['text'];
    } else {
      $text = pht('Browse');
    }

    return phutil_tag(
      'a',
      array(
        'href' => $href,
      ),
      $text);
  }

  final public function linkExternal($hash, $uri, $text) {
    $href = id(new PhutilURI('/diffusion/external/'))
      ->setQueryParams(
        array(
          'uri' => $uri,
          'id'  => $hash,
        ));

    return phutil_tag(
      'a',
      array(
        'href' => $href,
      ),
      $text);
  }

  final public static function nameCommit(
    PhabricatorRepository $repository,
    $commit) {

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $commit_name = substr($commit, 0, 12);
        break;
      default:
        $commit_name = $commit;
        break;
    }

    $callsign = $repository->getCallsign();
    return "r{$callsign}{$commit_name}";
  }

  final public static function linkCommit(
    PhabricatorRepository $repository,
    $commit,
    $summary = '') {

    $commit_name = self::nameCommit($repository, $commit);
    $callsign = $repository->getCallsign();

    if (strlen($summary)) {
      $commit_name .= ': '.$summary;
    }

    return phutil_tag(
      'a',
      array(
        'href' => "/r{$callsign}{$commit}",
      ),
      $commit_name);
  }

  final public static function linkRevision($id) {
    if (!$id) {
      return null;
    }

    return phutil_tag(
      'a',
      array(
        'href' => "/D{$id}",
      ),
      "D{$id}");
  }

  final public static function renderName($name) {
    $email = new PhutilEmailAddress($name);
    if ($email->getDisplayName() && $email->getDomainName()) {
      Javelin::initBehavior('phabricator-tooltips', array());
      require_celerity_resource('aphront-tooltip-css');
      return javelin_tag(
        'span',
        array(
          'sigil' => 'has-tooltip',
          'meta'  => array(
            'tip'   => $email->getAddress(),
            'align' => 'E',
            'size'  => 'auto',
          ),
        ),
        $email->getDisplayName());
    }
    return hsprintf('%s', $name);
  }

}
