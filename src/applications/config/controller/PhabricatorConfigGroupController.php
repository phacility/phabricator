<?php

final class PhabricatorConfigGroupController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $group_key = $request->getURIData('key');

    $groups = PhabricatorApplicationConfigOptions::loadAll();
    $options = idx($groups, $group_key);
    if (!$options) {
      return new Aphront404Response();
    }

    $group_uri = PhabricatorConfigGroupConstants::getGroupFullURI(
      $options->getGroup());
    $group_name = PhabricatorConfigGroupConstants::getGroupShortName(
      $options->getGroup());

    $nav = $this->buildSideNavView();
    $nav->selectFilter($group_uri);

    $title = pht('%s Configuration', $options->getName());
    $header = $this->buildHeaderView($title);
    $list = $this->buildOptionList($options->getOptions());
    $group_url = phutil_tag('a', array('href' => $group_uri), $group_name);

    $box_header = pht("%s \xC2\xBB %s", $group_url, $options->getName());
    $view = $this->buildConfigBoxView($box_header, $list);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($group_name, $this->getApplicationURI($group_uri))
      ->addTextCrumb($options->getName())
      ->setBorder(true);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setNavigation($nav)
      ->setFixed(true)
      ->setMainColumn($view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }

  private function buildOptionList(array $options) {
    assert_instances_of($options, 'PhabricatorConfigOption');

    require_celerity_resource('config-options-css');

    $db_values = array();
    if ($options) {
      $db_values = id(new PhabricatorConfigEntry())->loadAllWhere(
        'configKey IN (%Ls) AND namespace = %s',
        mpull($options, 'getKey'),
        'default');
      $db_values = mpull($db_values, null, 'getConfigKey');
    }

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($this->getRequest()->getUser());
    foreach ($options as $option) {
      $engine->addObject($option, 'summary');
    }
    $engine->process();

    $list = new PHUIObjectItemListView();
    $list->setBig(true);
    foreach ($options as $option) {
      $summary = $engine->getOutput($option, 'summary');

      $item = id(new PHUIObjectItemView())
        ->setHeader($option->getKey())
        ->setHref('/config/edit/'.$option->getKey().'/')
        ->addAttribute($summary);

      $color = null;
      $db_value = idx($db_values, $option->getKey());
      if ($db_value && !$db_value->getIsDeleted()) {
        $item->setEffect('visited');
        $color = 'violet';
      }

      if ($option->getHidden()) {
        $item->setStatusIcon('fa-eye-slash grey', pht('Hidden'));
        $item->setDisabled(true);
      } else if ($option->getLocked()) {
        $item->setStatusIcon('fa-lock '.$color, pht('Locked'));
      } else if ($color) {
        $item->setStatusIcon('fa-pencil '.$color, pht('Editable'));
      } else {
        $item->setStatusIcon('fa-pencil-square-o '.$color, pht('Editable'));
      }

      if (!$option->getHidden()) {
        $current_value = PhabricatorEnv::getEnvConfig($option->getKey());
        $current_value = PhabricatorConfigJSON::prettyPrintJSON(
          $current_value);
        $current_value = phutil_tag(
          'div',
          array(
            'class' => 'config-options-current-value '.$color,
          ),
          array(
            $current_value,
          ));

        $item->setSideColumn($current_value);
      }

      $list->addItem($item);
    }

    return $list;
  }

}
