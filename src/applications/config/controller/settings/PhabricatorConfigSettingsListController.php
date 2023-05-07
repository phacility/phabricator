<?php

final class PhabricatorConfigSettingsListController
  extends PhabricatorConfigSettingsController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $filter = $request->getURIData('filter');
    if ($filter === null || !strlen($filter)) {
      $filter = 'settings';
    }

    $is_core = ($filter === 'settings');
    $is_advanced = ($filter === 'advanced');
    $is_all = ($filter === 'all');

    $show_core = ($is_core || $is_all);
    $show_advanced = ($is_advanced || $is_all);

    if ($is_core) {
      $title = pht('Core Settings');
    } else if ($is_advanced) {
      $title = pht('Advanced Settings');
    } else {
      $title = pht('All Settings');
    }

    $db_values = id(new PhabricatorConfigEntry())
      ->loadAllWhere('namespace = %s', 'default');
    $db_values = mpull($db_values, null, 'getConfigKey');

    $list = id(new PHUIObjectItemListView())
      ->setBig(true)
      ->setFlush(true);

    $rows = array();
    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    ksort($options);
    foreach ($options as $option) {
      $key = $option->getKey();

      $is_advanced = (bool)$option->getLocked();
      if ($is_advanced && !$show_advanced) {
        continue;
      }

      if (!$is_advanced && !$show_core) {
        continue;
      }

      $db_value = idx($db_values, $key);

      $item = $this->newConfigOptionView($option, $db_value);
      $list->addItem($item);
    }

    $header = $this->buildHeaderView($title);

    $crumbs = $this->newCrumbs()
      ->addTextCrumb($title);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($list);

    $nav = $this->newNavigation($filter);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content);
  }

  private function newConfigOptionView(
    PhabricatorConfigOption $option,
    PhabricatorConfigEntry $stored_value = null) {

    $summary = $option->getSummary();

    $item = id(new PHUIObjectItemView())
      ->setHeader($option->getKey())
      ->setClickable(true)
      ->setHref('/config/edit/'.$option->getKey().'/')
      ->addAttribute($summary);

    $color = null;
    if ($stored_value && !$stored_value->getIsDeleted()) {
      $item->setEffect('visited');
      $color = 'violet';
    }

    if ($option->getHidden()) {
      $item->setStatusIcon('fa-eye-slash', pht('Hidden'));
    } else if ($option->getLocked()) {
      $item->setStatusIcon('fa-lock '.$color, pht('Locked'));
    } else if ($color) {
      $item->setStatusIcon('fa-pencil '.$color, pht('Editable'));
    } else {
      $item->setStatusIcon('fa-circle-o grey', pht('Default'));
    }

    return $item;
  }

}
