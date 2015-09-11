<?php

final class PhabricatorConfigCacheController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('cache/');

    $title = pht('Cache Status');

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Cache Status'));

    $code_box = $this->renderCodeBox();
    $data_box = $this->renderDataBox();

    $nav->appendChild(
      array(
        $crumbs,
        $code_box,
        $data_box,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

  private function renderCodeBox() {
    $cache = PhabricatorOpcodeCacheSpec::getActiveCacheSpec();

    $properties = id(new PHUIPropertyListView());

    $this->renderCommonProperties($properties, $cache);

    $purge_icon = id(new PHUIIconView())
      ->setIconFont('fa-exclamation-triangle');

    $purge_button = id(new PHUIButtonView())
      ->setText(pht('Purge Caches'))
      ->setHref('/config/cache/purge/')
      ->setTag('a')
      ->setWorkflow(true)
      ->setIcon($purge_icon);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Opcode Cache'))
      ->addActionLink($purge_button);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);
  }

  private function renderDataBox() {
    $cache = PhabricatorDataCacheSpec::getActiveCacheSpec();

    $properties = id(new PHUIPropertyListView());

    $this->renderCommonProperties($properties, $cache);

    $table = null;
    if ($cache->getName() !== null) {
      $total_memory = $cache->getTotalMemory();

      $summary = $cache->getCacheSummary();
      $summary = isort($summary, 'total');
      $summary = array_reverse($summary, true);

      $rows = array();
      foreach ($summary as $key => $info) {
        $rows[] = array(
          $key,
          pht('%s', new PhutilNumber($info['count'])),
          phutil_format_bytes($info['max']),
          phutil_format_bytes($info['total']),
          sprintf('%.1f%%', (100 * ($info['total'] / $total_memory))),
        );
      }

      $table = id(new AphrontTableView($rows))
        ->setHeaders(
          array(
            pht('Pattern'),
            pht('Count'),
            pht('Largest'),
            pht('Total'),
            pht('Usage'),
          ))
        ->setColumnClasses(
          array(
            'wide',
            'n',
            'n',
            'n',
            'n',
          ));
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Data Cache'))
      ->addPropertyList($properties)
      ->setTable($table);
  }

  private function renderCommonProperties(
    PHUIPropertyListView $properties,
    PhabricatorCacheSpec $cache) {

    if ($cache->getName() !== null) {
      $name = $this->renderYes($cache->getName());
    } else {
      $name = $this->renderNo(pht('None'));
    }
    $properties->addProperty(pht('Cache'), $name);

    if ($cache->getIsEnabled()) {
      $enabled = $this->renderYes(pht('Enabled'));
    } else {
      $enabled = $this->renderNo(pht('Not Enabled'));
    }
    $properties->addProperty(pht('Enabled'), $enabled);

    $version = $cache->getVersion();
    if ($version) {
      $properties->addProperty(pht('Version'), $this->renderInfo($version));
    }

    if ($cache->getName() === null) {
      return;
    }

    $mem_total = $cache->getTotalMemory();
    $mem_used = $cache->getUsedMemory();

    if ($mem_total) {
      $percent = 100 * ($mem_used / $mem_total);

      $properties->addProperty(
        pht('Memory Usage'),
        pht(
          '%s of %s',
          phutil_tag('strong', array(), sprintf('%.1f%%', $percent)),
          phutil_format_bytes($mem_total)));
    }

    $entry_count = $cache->getEntryCount();
    if ($entry_count !== null) {
      $properties->addProperty(
        pht('Cache Entries'),
        pht('%s', new PhutilNumber($entry_count)));
    }

  }

  private function renderYes($info) {
    return array(
      id(new PHUIIconView())->setIconFont('fa-check', 'green'),
      ' ',
      $info,
    );
  }

  private function renderNo($info) {
    return array(
      id(new PHUIIconView())->setIconFont('fa-times-circle', 'red'),
      ' ',
      $info,
    );
  }

  private function renderInfo($info) {
    return array(
      id(new PHUIIconView())->setIconFont('fa-info-circle', 'grey'),
      ' ',
      $info,
    );
  }

}
