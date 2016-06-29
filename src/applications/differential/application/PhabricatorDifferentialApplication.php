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

  public function getIcon() {
    return 'fa-cog';
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return true;
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Differential User Guide'),
        'href' => PhabricatorEnv::getDoclink('Differential User Guide'),
      ),
    );
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
      new DifferentialLandingActionMenuEventListener(),
    );
  }

  public function getOverview() {
    return pht(
      'Differential is a **code review application** which allows '.
      'engineers to review, discuss and approve changes to software.');
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
        'revision/' => array(
          'edit/(?:(?P<id>[1-9]\d*)/)?'
            => 'DifferentialRevisionEditController',
          'land/(?:(?P<id>[1-9]\d*))/(?P<strategy>[^/]+)/'
            => 'DifferentialRevisionLandController',
          'closedetails/(?P<phid>[^/]+)/'
            => 'DifferentialRevisionCloseDetailsController',
          'update/(?P<revisionID>[1-9]\d*)/'
            => 'DifferentialDiffCreateController',
          'operation/(?P<id>[1-9]\d*)/'
            => 'DifferentialRevisionOperationController',
        ),
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

  public static function loadNeedAttentionRevisions(PhabricatorUser $viewer) {
    if (!$viewer->isLoggedIn()) {
      return array();
    }

    $viewer_phid = $viewer->getPHID();

    $responsible_phids = id(new DifferentialResponsibleDatasource())
      ->setViewer($viewer)
      ->evaluateTokens(array($viewer_phid));

    $revision_query = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->withResponsibleUsers($responsible_phids)
      ->needReviewerStatus(true)
      ->needRelationships(true)
      ->needFlags(true)
      ->needDrafts(true)
      ->setLimit(self::MAX_STATUS_ITEMS);

    $revisions = $revision_query->execute();

    $query = id(new PhabricatorSavedQuery())
      ->attachParameterMap(
        array(
          'responsiblePHIDs' => $responsible_phids,
        ));

    $groups = id(new DifferentialRevisionRequiredActionResultBucket())
      ->setViewer($viewer)
      ->newResultGroups($query, $revisions);

    $include = array();
    foreach ($groups as $group) {
      switch ($group->getKey()) {
        case DifferentialRevisionRequiredActionResultBucket::KEY_MUSTREVIEW:
        case DifferentialRevisionRequiredActionResultBucket::KEY_SHOULDREVIEW:
          foreach ($group->getObjects() as $object) {
            $include[] = $object;
          }
          break;
        default:
          break;
      }
    }

    return $include;
  }

  public function loadStatus(PhabricatorUser $user) {
    $revisions = self::loadNeedAttentionRevisions($user);
    $limit = self::MAX_STATUS_ITEMS;

    if (count($revisions) >= $limit) {
      $display_count = ($limit - 1);
      $display_label = pht(
        '%s+ Active Review(s)',
        new PhutilNumber($display_count));
    } else {
      $display_count = count($revisions);
      $display_label = pht(
        '%s Review(s) Need Attention',
        new PhutilNumber($display_count));
    }

    $status = array();

    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType(PhabricatorApplicationStatusView::TYPE_WARNING)
      ->setText($display_label)
      ->setCount($display_count);

    return $status;
  }

  public function supportsEmailIntegration() {
    return true;
  }

  public function getAppEmailBlurb() {
    return pht(
      'Send email to these addresses to create revisions. The body of the '.
      'message and / or one or more attachments should be the output of a '.
      '"diff" command. %s',
      phutil_tag(
        'a',
        array(
          'href' => $this->getInboundEmailSupportLink(),
        ),
        pht('Learn More')));
  }

  protected function getCustomCapabilities() {
    return array(
      DifferentialDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created revisions.'),
        'template' => DifferentialRevisionPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
    );
  }

  public function getMailCommandObjects() {
    return array(
      'revision' => array(
        'name' => pht('Email Commands: Revisions'),
        'header' => pht('Interacting with Differential Revisions'),
        'object' => new DifferentialRevision(),
        'summary' => pht(
          'This page documents the commands you can use to interact with '.
          'revisions in Differential.'),
      ),
    );
  }

  public function getApplicationSearchDocumentTypes() {
    return array(
      DifferentialRevisionPHIDType::TYPECONST,
    );
  }

}
