<?php

final class PhabricatorConfigGroupController
  extends PhabricatorConfigController {

  private $groupKey;

  public function willProcessRequest(array $data) {
    $this->groupKey = $data['key'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $groups = PhabricatorApplicationConfigOptions::loadAll();
    $options = idx($groups, $this->groupKey);
    if (!$options) {
      return new Aphront404Response();
    }

    $title = pht('%s Configuration', $options->getName());
    $list = $this->buildOptionList($options->getOptions());

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($list);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Config'), $this->getApplicationURI())
      ->addTextCrumb($options->getName(), $this->getApplicationURI());

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
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
    $list->setStackable(true);
    foreach ($options as $option) {
      $summary = $engine->getOutput($option, 'summary');

      $item = id(new PHUIObjectItemView())
        ->setHeader($option->getKey())
        ->setHref('/config/edit/'.$option->getKey().'/')
        ->addAttribute($summary);

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
            phutil_tag('span', array(), pht('Current Value:')),
            ' '.$current_value,
          ));

        $item->appendChild($current_value);
      }

      $db_value = idx($db_values, $option->getKey());
      if ($db_value && !$db_value->getIsDeleted()) {
        $item->addIcon('edit', pht('Customized'));
      }

      if ($option->getHidden()) {
        $item->addIcon('unpublish', pht('Hidden'));
      } else if ($option->getLocked()) {
        $item->addIcon('lock', pht('Locked'));
      }

      $list->addItem($item);
    }

    return $list;
  }

}
