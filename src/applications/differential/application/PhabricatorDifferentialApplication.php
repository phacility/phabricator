<?php

final class PhabricatorDifferentialApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/differential/';
  }

  public function getName() {
    return pht('Differential');
  }

  public function getShortDescription() {
    return pht('Review Code');
  }

  public function getIconName() {
    return 'differential';
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return true;
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('Differential User Guide');
  }

  public function getFactObjectsForAnalysis() {
    return array(
      new DifferentialRevision(),
    );
  }

  public function getTitleGlyph() {
    return "\xE2\x9A\x99";
  }

  public function getEventListeners() {
    return array(
      new DifferentialActionMenuEventListener(),
      new DifferentialHovercardEventListener(),
      new DifferentialLandingActionMenuEventListener(),
    );
  }

  public function getOverview() {
    return pht(<<<EOTEXT
Differential is a **code review application** which allows engineers to review,
discuss and approve changes to software.
EOTEXT
);
  }

  public function getRoutes() {
    return array(
      '/D(?P<id>[1-9]\d*)' => 'DifferentialRevisionViewController',
      '/differential/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'DifferentialRevisionListController',
        'diff/' => array(
          '(?P<id>[1-9]\d*)/' => 'DifferentialDiffViewController',
          'create/' => 'DifferentialDiffCreateController',
        ),
        'changeset/' => 'DifferentialChangesetViewController',
        'revision/edit/(?:(?P<id>[1-9]\d*)/)?'
          => 'DifferentialRevisionEditController',
        'revision/land/(?:(?P<id>[1-9]\d*))/(?P<strategy>[^/]+)/'
          => 'DifferentialRevisionLandController',
        'revision/closedetails/(?P<phid>[^/]+)/'
          => 'DifferentialRevisionCloseDetailsController',
        'comment/' => array(
          'preview/(?P<id>[1-9]\d*)/' => 'DifferentialCommentPreviewController',
          'save/(?P<id>[1-9]\d*)/' => 'DifferentialCommentSaveController',
          'inline/' => array(
            'preview/(?P<id>[1-9]\d*)/'
              => 'DifferentialInlineCommentPreviewController',
            'edit/(?P<id>[1-9]\d*)/'
              => 'DifferentialInlineCommentEditController',
          ),
        ),
        'preview/' => 'PhabricatorMarkupPreviewController',
      ),
    );
  }

  public function getApplicationOrder() {
    return 0.100;
  }

  public function getRemarkupRules() {
    return array(
      new DifferentialRemarkupRule(),
    );
  }

  public function loadStatus(PhabricatorUser $user) {
    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($user)
      ->withResponsibleUsers(array($user->getPHID()))
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->needRelationships(true)
      ->execute();

    list($blocking, $active, $waiting) =
      DifferentialRevisionQuery::splitResponsible(
        $revisions,
        array($user->getPHID()));

    $status = array();

    $blocking = count($blocking);
    $type = PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Review(s) Blocking Others', $blocking))
      ->setCount($blocking);

    $active = count($active);
    $type = PhabricatorApplicationStatusView::TYPE_WARNING;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Review(s) Need Attention', $active))
      ->setCount($active);

    $waiting = count($waiting);
    $type = PhabricatorApplicationStatusView::TYPE_INFO;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Review(s) Waiting on Others', $waiting))
      ->setCount($waiting);

    return $status;
  }

  protected function getCustomCapabilities() {
    return array(
      DifferentialDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created revisions.'),
      ),
    );
  }

}
