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

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $list = $this->buildOptionList($options->getOptions());

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Config'))
          ->setHref($this->getApplicationURI()))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($options->getName())
          ->setHref($this->getApplicationURI()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $list,
      ),
      array(
        'title' => $title,
        'device' => true,
      )
    );
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


    $list = new PhabricatorObjectItemListView();
    foreach ($options as $option) {
      $current_value = PhabricatorEnv::getEnvConfig($option->getKey());
      $current_value = $this->prettyPrintJSON($current_value);
      $current_value = phutil_render_tag(
        'div',
        array(
          'class' => 'config-options-current-value',
        ),
        '<span>'.pht('Current Value:').'</span> '.
        phutil_escape_html($current_value));


      $item = id(new PhabricatorObjectItemView())
        ->setHeader($option->getKey())
        ->setHref('/config/edit/'.$option->getKey().'/')
        ->addAttribute(phutil_escape_html($option->getSummary()))
        ->appendChild($current_value);

      $db_value = idx($db_values, $option->getKey());
      if ($db_value && !$db_value->getIsDeleted()) {
        $item->addIcon('edit', pht('Customized'));
      } else {
        $item->addIcon('edit-grey', pht('Default'));
      }

      $list->addItem($item);
    }

    return $list;
  }

}
