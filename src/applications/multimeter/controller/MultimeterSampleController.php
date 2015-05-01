<?php

final class MultimeterSampleController extends MultimeterController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $group_map = array(
      'type' => 'eventType',
      'host' => 'eventHostID',
      'context' => 'eventContextID',
      'viewer' => 'eventViewerID',
      'request' => 'requestKey',
      'label' => 'eventLabelID',
    );

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
    $data = queryfx_all(
      $conn,
      'SELECT *, count(*) N, SUM(sampleRate * resourceCost) as totalCost
        FROM %T
        WHERE epoch >= %d AND epoch <= %d
        GROUP BY %Q
        ORDER BY totalCost DESC, MAX(id) DESC
        LIMIT 100',
      $table->getTableName(),
      $ago,
      $now,
      implode(', ', array_select_keys($group_map, $group)));

    $this->loadDimensions($data);

    $rows = array();
    foreach ($data as $row) {

      if (isset($group['request'])) {
        $request_col = $row['requestKey'];
      } else {
        $request_col = $this->renderGroupingLink($group, 'request');
      }

      if (isset($group['viewer'])) {
        $viewer_col = $this->getViewerDimension($row['eventViewerID'])
          ->getName();
      } else {
        $viewer_col = $this->renderGroupingLink($group, 'viewer');
      }

      if (isset($group['context'])) {
        $context_col = $this->getContextDimension($row['eventContextID'])
          ->getName();
      } else {
        $context_col = $this->renderGroupingLink($group, 'context');
      }

      if (isset($group['host'])) {
        $host_col = $this->getHostDimension($row['eventHostID'])
          ->getName();
      } else {
        $host_col = $this->renderGroupingLink($group, 'host');
      }

      if (isset($group['label'])) {
        $label_col = $this->getLabelDimension($row['eventLabelID'])
          ->getName();
      } else {
        $label_col = $this->renderGroupingLink($group, 'label');
      }

      $rows[] = array(
        ($row['N'] == 1)
          ? $row['id']
          : pht('%s Events', new PhutilNumber($row['N'])),
        $request_col,
        $viewer_col,
        $context_col,
        $host_col,
        MultimeterEvent::getEventTypeName($row['eventType']),
        $label_col,
        MultimeterEvent::formatResourceCost(
          $viewer,
          $row['eventType'],
          $row['totalCost']),
        $row['sampleRate'],
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
          pht('Cost'),
          pht('Rate'),
          pht('Epoch'),
        ))
      ->setColumnClasses(
        array(
          'n',
          null,
          null,
          null,
          null,
          null,
          'wide',
          'n',
          'n',
          null,
        ));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(
        pht(
          'Samples (%s - %s)',
          phabricator_datetime($ago, $viewer),
          phabricator_datetime($now, $viewer)))
      ->appendChild($table);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Samples'), $this->getGroupURI(array()));

    $crumb_map = array(
      'host' => pht('By Host'),
      'context' => pht('By Context'),
      'viewer' => pht('By Viewer'),
      'request' => pht('By Request'),
      'label' => pht('By Label'),
    );

    $parts = array();
    foreach ($group as $item) {
      if ($item == 'type') {
        continue;
      }
      $parts[$item] = $item;
      $crumbs->addTextCrumb(
        idx($crumb_map, $item, $item),
        $this->getGroupURI($parts));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => pht('Samples'),
      ));
  }

  private function renderGroupingLink(array $group, $key) {
    $group[] = $key;
    $uri = $this->getGroupURI($group);

    return phutil_tag(
      'a',
      array(
        'href' => $uri,
        'style' => 'font-weight: bold',
      ),
      pht('(All)'));
  }

  private function getGroupURI(array $group) {
    unset($group['type']);
    $uri = clone $this->getRequest()->getRequestURI();
    $uri->setQueryParam('group', implode('.', $group));
    return $uri;
  }


}
