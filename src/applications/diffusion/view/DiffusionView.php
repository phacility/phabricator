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

  final public function linkHistory($path) {
    $href = $this->getDiffusionRequest()->generateURI(
      array(
        'action' => 'history',
        'path'   => $path,
      ));

    return $this->renderHistoryLink($href);
  }

  final public function linkBranchHistory($branch) {
    $href = $this->getDiffusionRequest()->generateURI(
      array(
        'action' => 'history',
        'branch' => $branch,
      ));

    return $this->renderHistoryLink($href);
  }

  final public function linkTagHistory($tag) {
    $href = $this->getDiffusionRequest()->generateURI(
      array(
        'action' => 'history',
        'commit' => $tag,
      ));

    return $this->renderHistoryLink($href);
  }

  private function renderHistoryLink($href) {
    return javelin_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'diffusion-link-icon',
        'sigil' => 'has-tooltip',
        'meta' => array(
          'tip' => pht('History'),
          'align' => 'E',
        ),
      ),
      id(new PHUIIconView())->setIcon('fa-history bluegrey'));
  }

  final public function linkBrowse(
    $path,
    array $details = array(),
    $button = false) {
    require_celerity_resource('diffusion-icons-css');
    Javelin::initBehavior('phabricator-tooltips');

    $file_type = idx($details, 'type');
    unset($details['type']);

    $display_name = idx($details, 'name');
    unset($details['name']);

    if ($display_name !== null && strlen($display_name)) {
      $display_name = phutil_tag(
        'span',
        array(
          'class' => 'diffusion-browse-name',
        ),
        $display_name);
    }

    if (isset($details['external'])) {
      $params = array(
        'uri' => idx($details, 'external'),
        'id'  => idx($details, 'hash'),
      );

      $href = new PhutilURI('/diffusion/external/', $params);
      $tip = pht('Browse External');
    } else {
      $href = $this->getDiffusionRequest()->generateURI(
        $details + array(
          'action' => 'browse',
          'path'   => $path,
        ));
      $tip = pht('Browse');
    }

    $icon = DifferentialChangeType::getIconForFileType($file_type);
    $color = DifferentialChangeType::getIconColorForFileType($file_type);
    $icon_view = id(new PHUIIconView())
      ->setIcon($icon.' '.$color);

    // If we're rendering a file or directory name, don't show the tooltip.
    if ($display_name !== null) {
      $sigil = null;
      $meta = null;
    } else {
      $sigil = 'has-tooltip';
      $meta = array(
        'tip' => $tip,
        'align' => 'E',
      );
    }

    if ($button) {
      return id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon('fa-code')
        ->setHref($href)
        ->setToolTip(pht('Browse'))
        ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE);
    }

    return javelin_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'diffusion-link-icon',
        'sigil' => $sigil,
        'meta' => $meta,
      ),
      array(
        $icon_view,
        $display_name,
      ));
  }

  final public static function linkCommit(
    PhabricatorRepository $repository,
    $commit,
    $summary = '') {

    $commit_name = $repository->formatCommitName($commit, $local = true);

    if (strlen($summary)) {
      $commit_name .= ': '.$summary;
    }

    return phutil_tag(
      'a',
      array(
        'href' => $repository->getCommitURI($commit),
      ),
      $commit_name);
  }

  final public static function linkDetail(
    PhabricatorRepository $repository,
    $commit,
    $detail) {

    return phutil_tag(
      'a',
      array(
        'href' => $repository->getCommitURI($commit),
      ),
      $detail);
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
            'align' => 'S',
            'size'  => 'auto',
          ),
        ),
        $email->getDisplayName());
    }
    return hsprintf('%s', $name);
  }

  final protected function renderBuildable(
    HarbormasterBuildable $buildable,
    $type = null) {
    Javelin::initBehavior('phabricator-tooltips');

    $icon = $buildable->getStatusIcon();
    $color = $buildable->getStatusColor();
    $name = $buildable->getStatusDisplayName();

    if ($type == 'button') {
      return id(new PHUIButtonView())
        ->setTag('a')
        ->setText($name)
        ->setIcon($icon)
        ->setColor($color)
        ->setHref('/'.$buildable->getMonogram())
        ->addClass('mmr')
        ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE)
        ->addClass('diffusion-list-build-status');
    }

    return id(new PHUIIconView())
      ->setIcon($icon.' '.$color)
      ->addSigil('has-tooltip')
      ->setHref('/'.$buildable->getMonogram())
      ->setMetadata(
        array(
          'tip' => $name,
        ));

  }

  final protected function loadBuildables(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');

    if (!$commits) {
      return array();
    }

    $viewer = $this->getUser();

    $harbormaster_app = 'PhabricatorHarbormasterApplication';
    $have_harbormaster = PhabricatorApplication::isClassInstalledForViewer(
      $harbormaster_app,
      $viewer);

    if ($have_harbormaster) {
      $buildables = id(new HarbormasterBuildableQuery())
        ->setViewer($viewer)
        ->withBuildablePHIDs(mpull($commits, 'getPHID'))
        ->withManualBuildables(false)
        ->execute();
      $buildables = mpull($buildables, null, 'getBuildablePHID');
    } else {
      $buildables = array();
    }

    return $buildables;
  }

}
