<?php

final class ManiphestTaskGraph
  extends PhabricatorObjectGraph {

  private $seedMaps = array();
  private $isStandalone;

  protected function getEdgeTypes() {
    return array(
      ManiphestTaskDependedOnByTaskEdgeType::EDGECONST,
      ManiphestTaskDependsOnTaskEdgeType::EDGECONST,
    );
  }

  protected function getParentEdgeType() {
    return ManiphestTaskDependsOnTaskEdgeType::EDGECONST;
  }

  protected function newQuery() {
    return new ManiphestTaskQuery();
  }

  protected function isClosed($object) {
    return $object->isClosed();
  }

  public function setIsStandalone($is_standalone) {
    $this->isStandalone = $is_standalone;
    return $this;
  }

  public function getIsStandalone() {
    return $this->isStandalone;
  }

  protected function newTableRow($phid, $object, $trace) {
    $viewer = $this->getViewer();

    Javelin::initBehavior('phui-hovercards');

    if ($object) {
      $status = $object->getStatus();
      $priority = $object->getPriority();
      $status_icon = ManiphestTaskStatus::getStatusIcon($status);
      $status_name = ManiphestTaskStatus::getTaskStatusName($status);

      $priority_color = ManiphestTaskPriority::getTaskPriorityColor($priority);
      if ($object->isClosed()) {
        $priority_color = 'grey';
      }

      $status = array(
        id(new PHUIIconView())->setIcon($status_icon, $priority_color),
        ' ',
        $status_name,
      );

      $owner_phid = $object->getOwnerPHID();
      if ($owner_phid) {
        $assigned = $viewer->renderHandle($owner_phid);
      } else {
        $assigned = phutil_tag('em', array(), pht('None'));
      }

      $link = javelin_tag(
        'a',
        array(
          'href' => $object->getURI(),
          'sigil' => 'hovercard',
          'meta' => array(
            'hovercardSpec' => array(
              'objectPHID' => $object->getPHID(),
            ),
          ),
        ),
        $object->getTitle());

      $link = array(
        phutil_tag(
          'span',
          array(
            'class' => 'object-name',
          ),
          $object->getMonogram()),
        ' ',
        $link,
      );

      $subtype_tag = null;

      $subtype = $object->newSubtypeObject();
      if ($subtype && $subtype->hasTagView()) {
        $subtype_tag = $subtype->newTagView()
          ->setSlimShady(true);
      }
    } else {
      $status = null;
      $assigned = null;
      $subtype_tag = null;
      $link = $viewer->renderHandle($phid);
    }

    if ($this->isParentTask($phid)) {
      $marker = 'fa-chevron-circle-up bluegrey';
      $marker_tip = pht('Direct Parent');
    } else if ($this->isChildTask($phid)) {
      $marker = 'fa-chevron-circle-down bluegrey';
      $marker_tip = pht('Direct Subtask');
    } else {
      $marker = null;
    }

    if ($marker) {
      $marker = id(new PHUIIconView())
        ->setIcon($marker)
        ->addSigil('has-tooltip')
        ->setMetadata(
          array(
            'tip' => $marker_tip,
            'align' => 'E',
          ));
    }

    return array(
      $marker,
      $trace,
      $status,
      $subtype_tag,
      $assigned,
      $link,
    );
  }

  protected function newTable(AphrontTableView $table) {
    $subtype_map = id(new ManiphestTask())->newEditEngineSubtypeMap();
    $has_subtypes = ($subtype_map->getCount() > 1);

    return $table
      ->setHeaders(
        array(
          null,
          null,
          pht('Status'),
          pht('Subtype'),
          pht('Assigned'),
          pht('Task'),
        ))
      ->setColumnClasses(
        array(
          'nudgeright',
          'threads',
          'graph-status',
          null,
          null,
          'wide pri object-link',
        ))
      ->setColumnVisibility(
        array(
          true,
          !$this->getRenderOnlyAdjacentNodes(),
          true,
          $has_subtypes,
        ))
      ->setDeviceVisibility(
        array(
          true,

          // On mobile, we only show the actual graph drawing if we're on the
          // standalone page, since it can take over the screen otherwise.
          $this->getIsStandalone(),
          true,

          // On mobile, don't show subtypes since they're relatively less
          // important and we're more pressured for space.
          false,
        ));
  }

  private function isParentTask($task_phid) {
    $map = $this->getSeedMap(ManiphestTaskDependedOnByTaskEdgeType::EDGECONST);
    return isset($map[$task_phid]);
  }

  private function isChildTask($task_phid) {
    $map = $this->getSeedMap(ManiphestTaskDependsOnTaskEdgeType::EDGECONST);
    return isset($map[$task_phid]);
  }

  private function getSeedMap($type) {
    if (!isset($this->seedMaps[$type])) {
      $maps = $this->getEdges($type);
      $phids = idx($maps, $this->getSeedPHID(), array());
      $phids = array_fuse($phids);
      $this->seedMaps[$type] = $phids;
    }

    return $this->seedMaps[$type];
  }

  protected function newEllipsisRow() {
    return array(
      null,
      null,
      null,
      null,
      null,
      pht("\xC2\xB7 \xC2\xB7 \xC2\xB7"),
    );
  }


}
