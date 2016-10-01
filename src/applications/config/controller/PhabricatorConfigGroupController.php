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

    $group_uri = PhabricatorConfigGroupConstants::getGroupURI(
      $options->getGroup());
    $group_name = PhabricatorConfigGroupConstants::getGroupShortName(
      $options->getGroup());

    $nav = $this->buildSideNavView();
    $nav->selectFilter($group_uri);

    $title = pht('%s Configuration', $options->getName());
    $list = $this->buildOptionList($options->getOptions());

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb($group_name, $this->getApplicationURI($group_uri))
      ->addTextCrumb($options->getName())
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $content = id(new PhabricatorConfigPageView())
      ->setHeader($header)
      ->setContent($list);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content)
      ->addClass('white-background');
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

      $label = pht('Current Value:');
      $color = null;
      $db_value = idx($db_values, $option->getKey());
      if ($db_value && !$db_value->getIsDeleted()) {
        $item->setEffect('visited');
        $color = 'violet';
        $label = pht('Customized Value:');
      }

      if ($option->getHidden()) {
        $item->setStatusIcon('fa-eye-slash grey', pht('Hidden'));
        $item->setDisabled(true);
      } else if ($option->getLocked()) {
        $item->setStatusIcon('fa-lock '.$color, pht('Locked'));
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
            'class' => 'config-options-current-value',
          ),
          array(
            phutil_tag('span', array(), $label),
            ' '.$current_value,
          ));

        $item->appendChild($current_value);
      }

      $list->addItem($item);
    }

    return $list;
  }

}
