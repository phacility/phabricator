<?php

final class MultimeterSampleController extends MultimeterController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $group_map = $this->getColumnMap();

    $group = explode('.', $request->getStr('group'));
    $group = array_intersect($group, array_keys($group_map));
    $group = array_fuse($group);

    if (empty($group['type'])) {
      $group['type'] = 'type';
    }

    $now = PhabricatorTime::getNow();
    $ago = ($now - phutil_units('24 hours in seconds'));

    $table = new MultimeterEvent();
    $conn = $table->establishConnection('r');

    $where = array();
    $where[] = qsprintf(
      $conn,
      'epoch >= %d AND epoch <= %d',
      $ago,
      $now);

    $with = array();
    foreach ($group_map as $key => $column) {

      // Don't let non-admins filter by viewers, this feels a little too
      // invasive of privacy.
      if ($key == 'viewer') {
        if (!$viewer->getIsAdmin()) {
          continue;
        }
      }

      $with[$key] = $request->getStrList($key);
      if ($with[$key]) {
        $where[] = qsprintf(
          $conn,
          '%T IN (%Ls)',
          $column,
          $with[$key]);
      }
    }

    $data = queryfx_all(
      $conn,
      'SELECT *,
          count(*) AS N,
          SUM(sampleRate * resourceCost) AS totalCost,
          SUM(sampleRate * resourceCost) / SUM(sampleRate) AS averageCost
        FROM %T
        WHERE %LA
        GROUP BY %LC
        ORDER BY totalCost DESC, MAX(id) DESC
        LIMIT 100',
      $table->getTableName(),
      $where,
      array_select_keys($group_map, $group));

    $this->loadDimensions($data);
    $phids = array();
    foreach ($data as $row) {
      $viewer_name = $this->getViewerDimension($row['eventViewerID'])
        ->getName();
      $viewer_phid = $this->getEventViewerPHID($viewer_name);
      if ($viewer_phid) {
        $phids[] = $viewer_phid;
      }
    }
    $handles = $viewer->loadHandles($phids);

    $rows = array();
    foreach ($data as $row) {

      if ($row['N'] == 1) {
        $events_col = $row['id'];
      } else {
        $events_col = $this->renderGroupingLink(
          $group,
          'id',
          pht('%s Event(s)', new PhutilNumber($row['N'])));
      }

      if (isset($group['request'])) {
        $request_col = $row['requestKey'];
        if (!$with['request']) {
          $request_col = $this->renderSelectionLink(
            'request',
            $row['requestKey'],
            $request_col);
        }
      } else {
        $request_col = $this->renderGroupingLink($group, 'request');
      }

      if (isset($group['viewer'])) {
        if ($viewer->getIsAdmin()) {
          $viewer_col = $this->getViewerDimension($row['eventViewerID'])
            ->getName();

          $viewer_phid = $this->getEventViewerPHID($viewer_col);
          if ($viewer_phid) {
            $viewer_col = $handles[$viewer_phid]->getName();
          }

          if (!$with['viewer']) {
            $viewer_col = $this->renderSelectionLink(
              'viewer',
              $row['eventViewerID'],
              $viewer_col);
          }
        } else {
          $viewer_col = phutil_tag('em', array(), pht('(Masked)'));
        }
      } else {
        $viewer_col = $this->renderGroupingLink($group, 'viewer');
      }

      if (isset($group['context'])) {
        $context_col = $this->getContextDimension($row['eventContextID'])
          ->getName();
        if (!$with['context']) {
          $context_col = $this->renderSelectionLink(
            'context',
            $row['eventContextID'],
            $context_col);
        }
      } else {
        $context_col = $this->renderGroupingLink($group, 'context');
      }

      if (isset($group['host'])) {
        $host_col = $this->getHostDimension($row['eventHostID'])
          ->getName();
        if (!$with['host']) {
          $host_col = $this->renderSelectionLink(
            'host',
            $row['eventHostID'],
            $host_col);
        }
      } else {
        $host_col = $this->renderGroupingLink($group, 'host');
      }

      if (isset($group['label'])) {
        $label_col = $this->getLabelDimension($row['eventLabelID'])
          ->getName();
        if (!$with['label']) {
          $label_col = $this->renderSelectionLink(
            'label',
            $row['eventLabelID'],
            $label_col);
        }
      } else {
        $label_col = $this->renderGroupingLink($group, 'label');
      }

      if ($with['type']) {
        $type_col = MultimeterEvent::getEventTypeName($row['eventType']);
      } else {
        $type_col = $this->renderSelectionLink(
          'type',
          $row['eventType'],
          MultimeterEvent::getEventTypeName($row['eventType']));
      }

      $rows[] = array(
        $events_col,
        $request_col,
        $viewer_col,
        $context_col,
        $host_col,
        $type_col,
        $label_col,
        MultimeterEvent::formatResourceCost(
          $viewer,
          $row['eventType'],
          $row['averageCost']),
        MultimeterEvent::formatResourceCost(
          $viewer,
          $row['eventType'],
          $row['totalCost']),
        ($row['N'] == 1)
          ? $row['sampleRate']
          : '-',
        phabricator_datetime($row['epoch'], $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Request'),
          pht('Viewer'),
          pht('Context'),
          pht('Host'),
          pht('Type'),
          pht('Label'),
          pht('Avg'),
          pht('Cost'),
          pht('Rate'),
          pht('Epoch'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          null,
          null,
          null,
          'wide',
          'n',
          'n',
          'n',
          null,
        ));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Samples'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Samples'),
      $this->getGroupURI(array(), true));
    $crumbs->setBorder(true);

    $crumb_map = array(
      'host' => pht('By Host'),
      'context' => pht('By Context'),
      'viewer' => pht('By Viewer'),
      'request' => pht('By Request'),
      'label' => pht('By Label'),
      'id' => pht('By ID'),
    );

    $parts = array();
    foreach ($group as $item) {
      if ($item == 'type') {
        continue;
      }
      $parts[$item] = $item;
      $crumbs->addTextCrumb(
        idx($crumb_map, $item, $item),
        $this->getGroupURI($parts, true));
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(
        pht(
          'Samples (%s - %s)',
          phabricator_datetime($ago, $viewer),
          phabricator_datetime($now, $viewer)))
      ->setHeaderIcon('fa-motorcycle');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($box);

    return $this->newPage()
      ->setTitle(pht('Samples'))
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function renderGroupingLink(array $group, $key, $name = null) {
    $group[] = $key;
    $uri = $this->getGroupURI($group);

    if ($name === null) {
      $name = pht('(All)');
    }

    return phutil_tag(
      'a',
      array(
        'href' => $uri,
        'style' => 'font-weight: bold',
      ),
      $name);
  }

  private function getGroupURI(array $group, $wipe = false) {
    unset($group['type']);
    $uri = clone $this->getRequest()->getRequestURI();

    $group = implode('.', $group);
    if (!strlen($group)) {
      $uri->removeQueryParam('group');
    } else {
      $uri->replaceQueryParam('group', $group);
    }

    if ($wipe) {
      foreach ($this->getColumnMap() as $key => $column) {
        $uri->removeQueryParam($key);
      }
    }

    return $uri;
  }

  private function renderSelectionLink($key, $value, $link_text) {
    $value = (array)$value;

    $uri = clone $this->getRequest()->getRequestURI();
    $uri->replaceQueryParam($key, implode(',', $value));

    return phutil_tag(
      'a',
      array(
        'href' => $uri,
      ),
      $link_text);
  }

  private function getColumnMap() {
    return array(
      'type' => 'eventType',
      'host' => 'eventHostID',
      'context' => 'eventContextID',
      'viewer' => 'eventViewerID',
      'request' => 'requestKey',
      'label' => 'eventLabelID',
      'id' => 'id',
    );
  }

  private function getEventViewerPHID($viewer_name) {
    if (!strncmp($viewer_name, 'user.', 5)) {
      return substr($viewer_name, 5);
    }
    return null;
  }

}
