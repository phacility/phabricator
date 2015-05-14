<?php

final class PhabricatorFlagSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Flags');
  }

  public function getApplicationClassName() {
    return 'PhabricatorFlagsApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter('colors', $request->getArr('colors'));
    $saved->setParameter('group', $request->getStr('group'));
    $saved->setParameter('objectFilter', $request->getStr('objectFilter'));
    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorFlagQuery())
      ->needHandles(true)
      ->withOwnerPHIDs(array($this->requireViewer()->getPHID()));

    $colors = $saved->getParameter('colors');
    if ($colors) {
      $query->withColors($colors);
    }
    $group = $saved->getParameter('group');
    $options = $this->getGroupOptions();
    if ($group && isset($options[$group])) {
      $query->setGroupBy($group);
    }

    $object_filter = $saved->getParameter('objectFilter');
    $objects = $this->getObjectFilterOptions();
    if ($object_filter && isset($objects[$object_filter])) {
      $query->withTypes(array($object_filter));
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $form
      ->appendChild(
        id(new PhabricatorFlagSelectControl())
        ->setName('colors')
        ->setLabel(pht('Colors'))
        ->setValue($saved_query->getParameter('colors', array())))
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setName('group')
        ->setLabel(pht('Group By'))
        ->setValue($saved_query->getParameter('group'))
        ->setOptions($this->getGroupOptions()))
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setName('objectFilter')
        ->setLabel(pht('Object Type'))
        ->setValue($saved_query->getParameter('objectFilter'))
        ->setOptions($this->getObjectFilterOptions()));
  }

  protected function getURI($path) {
    return '/flag/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('Flagged'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getGroupOptions() {
    return array(
      PhabricatorFlagQuery::GROUP_NONE => pht('None'),
      PhabricatorFlagQuery::GROUP_COLOR => pht('Color'),
    );
  }

  private function getObjectFilterOptions() {
    $objects = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorFlaggableInterface')
      ->loadObjects();
    $all_types = PhabricatorPHIDType::getAllTypes();
    $options = array();
    foreach ($objects as $object) {
      $phid = $object->generatePHID();
      $phid_type = phid_get_type($phid);
      $type_object = idx($all_types, $phid_type);
      if ($type_object) {
        $options[$phid_type] = $type_object->getTypeName();
      }
    }
    // sort it alphabetically...
    asort($options);
    $default_option = array(
      0 => pht('All Object Types'),
    );
    // ...and stick the default option on front
    $options = array_merge($default_option, $options);

    return $options;
  }

  protected function renderResultList(
    array $flags,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($flags, 'PhabricatorFlag');

    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($flags as $flag) {
      $id = $flag->getID();
      $phid = $flag->getObjectPHID();

      $class = PhabricatorFlagColor::getCSSClass($flag->getColor());

      $flag_icon = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-flag-icon '.$class,
        ),
        '');

      $item = id(new PHUIObjectItemView())
        ->addHeadIcon($flag_icon)
        ->setHeader($flag->getHandle()->getFullName())
        ->setHref($flag->getHandle()->getURI());

      $status_open = PhabricatorObjectHandle::STATUS_OPEN;
      if ($flag->getHandle()->getStatus() != $status_open) {
        $item->setDisabled(true);
      }

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-pencil')
          ->setHref($this->getApplicationURI("edit/{$phid}/"))
          ->setWorkflow(true));

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-times')
          ->setHref($this->getApplicationURI("delete/{$id}/"))
          ->setWorkflow(true));

      if ($flag->getNote()) {
        $item->addAttribute($flag->getNote());
      }

      $item->addIcon(
        'none',
        phabricator_datetime($flag->getDateCreated(), $viewer));

      $list->addItem($item);
    }

    return $list;
  }


}
