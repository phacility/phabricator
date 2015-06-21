<?php

final class HarbormasterBuildViewController
  extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $this->id;
    $generation = $request->getInt('g');

    $build = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$build) {
      return new Aphront404Response();
    }

    require_celerity_resource('harbormaster-css');

    $title = pht('Build %d', $id);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($build);

    if ($build->isRestarting()) {
      $header->setStatus('fa-exclamation-triangle', 'red', pht('Restarting'));
    } else if ($build->isStopping()) {
      $header->setStatus('fa-exclamation-triangle', 'red', pht('Pausing'));
    } else if ($build->isResuming()) {
      $header->setStatus('fa-exclamation-triangle', 'red', pht('Resuming'));
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    $actions = $this->buildActionList($build);
    $this->buildPropertyLists($box, $build, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $this->addBuildableCrumb($crumbs, $build->getBuildable());
    $crumbs->addTextCrumb($title);

    if ($generation === null || $generation > $build->getBuildGeneration() ||
      $generation < 0) {
      $generation = $build->getBuildGeneration();
    }

    $build_targets = id(new HarbormasterBuildTargetQuery())
      ->setViewer($viewer)
      ->needBuildSteps(true)
      ->withBuildPHIDs(array($build->getPHID()))
      ->withBuildGenerations(array($generation))
      ->execute();

    if ($build_targets) {
      $messages = id(new HarbormasterBuildMessageQuery())
        ->setViewer($viewer)
        ->withBuildTargetPHIDs(mpull($build_targets, 'getPHID'))
        ->execute();
      $messages = mgroup($messages, 'getBuildTargetPHID');
    } else {
      $messages = array();
    }

    $targets = array();
    foreach ($build_targets as $build_target) {
      $header = id(new PHUIHeaderView())
        ->setHeader($build_target->getName())
        ->setUser($viewer);

      $target_box = id(new PHUIObjectBoxView())
        ->setHeader($header);

      $properties = new PHUIPropertyListView();
      $status_view = new PHUIStatusListView();

      $item = new PHUIStatusItemView();

      $status = $build_target->getTargetStatus();
      $status_name =
        HarbormasterBuildTarget::getBuildTargetStatusName($status);
      $icon = HarbormasterBuildTarget::getBuildTargetStatusIcon($status);
      $color = HarbormasterBuildTarget::getBuildTargetStatusColor($status);

      $item->setTarget($status_name);
      $item->setIcon($icon, $color);
      $status_view->addItem($item);

      $when = array();
      $started = $build_target->getDateStarted();
      $now = PhabricatorTime::getNow();
      if ($started) {
        $ended = $build_target->getDateCompleted();
        if ($ended) {
          $when[] = pht(
            'Completed at %s',
            phabricator_datetime($started, $viewer));

          $duration = ($ended - $started);
          if ($duration) {
            $when[] = pht(
              'Built for %s',
              phutil_format_relative_time_detailed($duration));
          } else {
            $when[] = pht('Built instantly');
          }
        } else {
          $when[] = pht(
            'Started at %s',
            phabricator_datetime($started, $viewer));
          $duration = ($now - $started);
          if ($duration) {
            $when[] = pht(
              'Running for %s',
              phutil_format_relative_time_detailed($duration));
          }
        }
      } else {
        $created = $build_target->getDateCreated();
        $when[] = pht(
          'Queued at %s',
          phabricator_datetime($started, $viewer));
        $duration = ($now - $created);
        if ($duration) {
          $when[] = pht(
            'Waiting for %s',
            phutil_format_relative_time_detailed($duration));
        }
      }

      $properties->addProperty(
        pht('When'),
        phutil_implode_html(" \xC2\xB7 ", $when));

      $properties->addProperty(pht('Status'), $status_view);

      $target_box->addPropertyList($properties, pht('Overview'));

      $step = $build_target->getBuildStep();

      if ($step) {
        $description = $step->getDescription();
        if ($description) {
          $rendered = PhabricatorMarkupEngine::renderOneObject(
            id(new PhabricatorMarkupOneOff())
              ->setContent($description)
              ->setPreserveLinebreaks(true),
            'default',
            $viewer);

          $properties->addSectionHeader(pht('Description'));
          $properties->addTextContent($rendered);
        }
      } else {
        $target_box->setFormErrors(
          array(
            pht(
              'This build step has since been deleted on the build plan.  '.
              'Some information may be omitted.'),
          ));
      }

      $details = $build_target->getDetails();
      if ($details) {
        $properties = new PHUIPropertyListView();
        foreach ($details as $key => $value) {
          $properties->addProperty($key, $value);
        }
        $target_box->addPropertyList($properties, pht('Configuration'));
      }

      $variables = $build_target->getVariables();
      if ($variables) {
        $properties = new PHUIPropertyListView();
        $properties->addRawContent($this->buildProperties($variables));
        $target_box->addPropertyList($properties, pht('Variables'));
      }

      $artifacts = $this->buildArtifacts($build_target);
      if ($artifacts) {
        $properties = new PHUIPropertyListView();
        $properties->addRawContent($artifacts);
        $target_box->addPropertyList($properties, pht('Artifacts'));
      }

      $build_messages = idx($messages, $build_target->getPHID(), array());
      if ($build_messages) {
        $properties = new PHUIPropertyListView();
        $properties->addRawContent($this->buildMessages($build_messages));
        $target_box->addPropertyList($properties, pht('Messages'));
      }

      $properties = new PHUIPropertyListView();
      $properties->addProperty(
        pht('Build Target ID'),
        $build_target->getID());
      $properties->addProperty(
        pht('Build Target PHID'),
        $build_target->getPHID());
      $target_box->addPropertyList($properties, pht('Metadata'));

      $targets[] = $target_box;

      $targets[] = $this->buildLog($build, $build_target);
    }

    $timeline = $this->buildTransactionTimeline(
      $build,
      new HarbormasterBuildTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $targets,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildArtifacts(
    HarbormasterBuildTarget $build_target) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $artifacts = id(new HarbormasterBuildArtifactQuery())
      ->setViewer($viewer)
      ->withBuildTargetPHIDs(array($build_target->getPHID()))
      ->execute();

    if (count($artifacts) === 0) {
      return null;
    }

    $list = id(new PHUIObjectItemListView())
      ->setFlush(true);

    foreach ($artifacts as $artifact) {
      $item = $artifact->getObjectItemView($viewer);
      if ($item !== null) {
        $list->addItem($item);
      }
    }

    return $list;
  }

  private function buildLog(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    $request = $this->getRequest();
    $viewer = $request->getUser();
    $limit = $request->getInt('l', 25);

    $logs = id(new HarbormasterBuildLogQuery())
      ->setViewer($viewer)
      ->withBuildTargetPHIDs(array($build_target->getPHID()))
      ->execute();

    $empty_logs = array();

    $log_boxes = array();
    foreach ($logs as $log) {
      $start = 1;
      $lines = preg_split("/\r\n|\r|\n/", $log->getLogText());
      if ($limit !== 0) {
        $start = count($lines) - $limit;
        if ($start >= 1) {
          $lines = array_slice($lines, -$limit, $limit);
        } else {
          $start = 1;
        }
      }

      $id = null;
      $is_empty = false;
      if (count($lines) === 1 && trim($lines[0]) === '') {
        // Prevent Harbormaster from showing empty build logs.
        $id = celerity_generate_unique_node_id();
        $empty_logs[] = $id;
        $is_empty = true;
      }

      $log_view = new ShellLogView();
      $log_view->setLines($lines);
      $log_view->setStart($start);

      $header = id(new PHUIHeaderView())
        ->setHeader(pht(
          'Build Log %d (%s - %s)',
          $log->getID(),
          $log->getLogSource(),
          $log->getLogType()))
        ->setSubheader($this->createLogHeader($build, $log))
        ->setUser($viewer);

      $log_box = id(new PHUIObjectBoxView())
        ->setHeader($header)
        ->setForm($log_view);

      if ($is_empty) {
        $log_box = phutil_tag(
          'div',
          array(
            'style' => 'display: none',
            'id' => $id,
          ),
          $log_box);
      }

      $log_boxes[] = $log_box;
    }

    if ($empty_logs) {
      $hide_id = celerity_generate_unique_node_id();

      Javelin::initBehavior('phabricator-reveal-content');

      $expand = phutil_tag(
        'div',
        array(
          'id' => $hide_id,
          'class' => 'harbormaster-empty-logs-are-hidden mlr mlt mll',
        ),
        array(
          pht(
            '%s empty logs are hidden.',
            new PhutilNumber(count($empty_logs))),
          ' ',
          javelin_tag(
            'a',
            array(
              'href' => '#',
              'sigil' => 'reveal-content',
              'meta' => array(
                'showIDs' => $empty_logs,
                'hideIDs' => array($hide_id),
              ),
            ),
            pht('Show all logs.')),
        ));

      array_unshift($log_boxes, $expand);
    }

    return $log_boxes;
  }

  private function createLogHeader($build, $log) {
    $request = $this->getRequest();
    $limit = $request->getInt('l', 25);

    $lines_25 = $this->getApplicationURI('/build/'.$build->getID().'/?l=25');
    $lines_50 = $this->getApplicationURI('/build/'.$build->getID().'/?l=50');
    $lines_100 =
      $this->getApplicationURI('/build/'.$build->getID().'/?l=100');
    $lines_0 = $this->getApplicationURI('/build/'.$build->getID().'/?l=0');

    $link_25 = phutil_tag('a', array('href' => $lines_25), pht('25'));
    $link_50 = phutil_tag('a', array('href' => $lines_50), pht('50'));
    $link_100 = phutil_tag('a', array('href' => $lines_100), pht('100'));
    $link_0 = phutil_tag('a', array('href' => $lines_0), pht('Unlimited'));

    if ($limit === 25) {
      $link_25 = phutil_tag('strong', array(), $link_25);
    } else if ($limit === 50) {
      $link_50 = phutil_tag('strong', array(), $link_50);
    } else if ($limit === 100) {
      $link_100 = phutil_tag('strong', array(), $link_100);
    } else if ($limit === 0) {
      $link_0 = phutil_tag('strong', array(), $link_0);
    }

    return phutil_tag(
      'span',
      array(),
      array(
        $link_25,
        ' - ',
        $link_50,
        ' - ',
        $link_100,
        ' - ',
        $link_0,
        ' Lines',
      ));
  }

  private function buildActionList(HarbormasterBuild $build) {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $id = $build->getID();

    $list = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($build)
      ->setObjectURI("/build/{$id}");

    $can_restart = $build->canRestartBuild();
    $can_stop = $build->canStopBuild();
    $can_resume = $build->canResumeBuild();

    $list->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Restart Build'))
        ->setIcon('fa-repeat')
        ->setHref($this->getApplicationURI('/build/restart/'.$id.'/'))
        ->setDisabled(!$can_restart)
        ->setWorkflow(true));

    if ($build->canResumeBuild()) {
      $list->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Resume Build'))
          ->setIcon('fa-play')
          ->setHref($this->getApplicationURI('/build/resume/'.$id.'/'))
          ->setDisabled(!$can_resume)
          ->setWorkflow(true));
    } else {
      $list->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Pause Build'))
          ->setIcon('fa-pause')
          ->setHref($this->getApplicationURI('/build/stop/'.$id.'/'))
          ->setDisabled(!$can_stop)
          ->setWorkflow(true));
    }

    return $list;
  }

  private function buildPropertyLists(
    PHUIObjectBoxView $box,
    HarbormasterBuild $build,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($build)
      ->setActionList($actions);
    $box->addPropertyList($properties);

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array(
        $build->getBuildablePHID(),
        $build->getBuildPlanPHID(),
      ))
      ->execute();

    $properties->addProperty(
      pht('Buildable'),
      $handles[$build->getBuildablePHID()]->renderLink());

    $properties->addProperty(
      pht('Build Plan'),
      $handles[$build->getBuildPlanPHID()]->renderLink());

    $properties->addProperty(
      pht('Restarts'),
      $build->getBuildGeneration());

    $properties->addProperty(
      pht('Status'),
      $this->getStatus($build));
  }

  private function getStatus(HarbormasterBuild $build) {
    $status_view = new PHUIStatusListView();

    $item = new PHUIStatusItemView();

    if ($build->isStopping()) {
      $status_name = pht('Pausing');
      $icon = PHUIStatusItemView::ICON_RIGHT;
      $color = 'dark';
    } else {
      $status = $build->getBuildStatus();
      $status_name =
        HarbormasterBuild::getBuildStatusName($status);
      $icon = HarbormasterBuild::getBuildStatusIcon($status);
      $color = HarbormasterBuild::getBuildStatusColor($status);
    }

    $item->setTarget($status_name);
    $item->setIcon($icon, $color);
    $status_view->addItem($item);

    return $status_view;
  }

  private function buildMessages(array $messages) {
    $viewer = $this->getRequest()->getUser();

    if ($messages) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($messages, 'getAuthorPHID'))
        ->execute();
    } else {
      $handles = array();
    }

    $rows = array();
    foreach ($messages as $message) {
      $rows[] = array(
        $message->getID(),
        $handles[$message->getAuthorPHID()]->renderLink(),
        $message->getType(),
        $message->getIsConsumed() ? pht('Consumed') : null,
        phabricator_datetime($message->getDateCreated(), $viewer),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString(pht('No messages for this build target.'));
    $table->setHeaders(
      array(
        pht('ID'),
        pht('From'),
        pht('Type'),
        pht('Consumed'),
        pht('Received'),
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
        '',
        'date',
      ));

    return $table;
  }

  private function buildProperties(array $properties) {
    ksort($properties);

    $rows = array();
    foreach ($properties as $key => $value) {
      $rows[] = array(
        $key,
        $value,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Value'),
        ))
      ->setColumnClasses(
        array(
          'pri right',
          'wide',
        ));

    return $table;
  }

}
