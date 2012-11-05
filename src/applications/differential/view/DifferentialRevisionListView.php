<?php

/**
 * Render a table of Differential revisions.
 */
final class DifferentialRevisionListView extends AphrontView {

  private $revisions;
  private $flags = array();
  private $drafts = array();
  private $handles;
  private $user;
  private $fields;
  private $highlightAge;
  const NO_DATA_STRING = 'No revisions found.';

  public function setFields(array $fields) {
    assert_instances_of($fields, 'DifferentialFieldSpecification');
    $this->fields = $fields;
    return $this;
  }

  public function setRevisions(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');
    $this->revisions = $revisions;
    return $this;
  }

  public function setHighlightAge($bool) {
    $this->highlightAge = $bool;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->fields as $field) {
      foreach ($this->revisions as $revision) {
        $phids[] = $field->getRequiredHandlePHIDsForRevisionList($revision);
      }
    }
    return array_mergev($phids);
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function loadAssets() {
    $user = $this->user;
    if (!$user) {
      throw new Exception("Call setUser() before loadAssets()!");
    }
    if ($this->revisions === null) {
      throw new Exception("Call setRevisions() before loadAssets()!");
    }

    $this->flags = id(new PhabricatorFlagQuery())
      ->withOwnerPHIDs(array($user->getPHID()))
      ->withObjectPHIDs(mpull($this->revisions, 'getPHID'))
      ->execute();

    $this->drafts = id(new DifferentialRevisionQuery())
      ->withIDs(mpull($this->revisions, 'getID'))
      ->withDraftRepliesByAuthors(array($user->getPHID()))
      ->execute();

    return $this;
  }

  public function render() {

    $user = $this->user;
    if (!$user) {
      throw new Exception("Call setUser() before render()!");
    }

    $fresh = null;
    $stale = null;

    if ($this->highlightAge) {
      $fresh = PhabricatorEnv::getEnvConfig('differential.days-fresh');
      if ($fresh) {
        $fresh = PhabricatorCalendarHoliday::getNthBusinessDay(
          time(),
          -$fresh);
      }

      $stale = PhabricatorEnv::getEnvConfig('differential.days-stale');
      if ($stale) {
        $stale = PhabricatorCalendarHoliday::getNthBusinessDay(
          time(),
          -$stale);
      }
    }

    Javelin::initBehavior('phabricator-tooltips', array());
    require_celerity_resource('aphront-tooltip-css');

    $flagged = mpull($this->flags, null, 'getObjectPHID');

    foreach ($this->fields as $field) {
      $field->setUser($this->user);
      $field->setHandles($this->handles);
    }

    $cell_classes = array();
    $rows = array();
    foreach ($this->revisions as $revision) {
      $phid = $revision->getPHID();
      $flag = '';
      if (isset($flagged[$phid])) {
        $class = PhabricatorFlagColor::getCSSClass($flagged[$phid]->getColor());
        $note = $flagged[$phid]->getNote();
        $flag = javelin_render_tag(
          'div',
          $note ? array(
            'class' => 'phabricator-flag-icon '.$class,
            'sigil' => 'has-tooltip',
            'meta'  => array(
              'tip'   => $note,
              'align' => 'N',
              'size'  => 240,
            ),
          ) : array(
            'class' => 'phabricator-flag-icon '.$class,
          ),
          '');

      } else if (array_key_exists($revision->getID(), $this->drafts)) {
        $src = '/rsrc/image/icon/fatcow/page_white_edit.png';
        $flag =
          '<a href="/D'.$revision->getID().'#comment-preview">'.
            phutil_render_tag(
              'img',
              array(
                'src' => celerity_get_resource_uri($src),
                'width' => 16,
                'height' => 16,
                'alt' => 'Draft',
                'title' => 'Draft Comment',
              )).
            '</a>';
      }

      $row = array($flag);

      $modified = $revision->getDateModified();

      foreach ($this->fields as $field) {
        if (($fresh || $stale) &&
            $field instanceof DifferentialDateModifiedFieldSpecification) {
          if ($stale && $modified < $stale) {
            $class = 'revision-age-old';
          } else if ($fresh && $modified < $fresh) {
            $class = 'revision-age-stale';
          } else {
            $class = 'revision-age-fresh';
          }
          $cell_classes[count($rows)][count($row)] = $class;
        }

        $row[] = $field->renderValueForRevisionList($revision);
      }

      $rows[] = $row;
    }

    $headers = array('');
    $classes = array('');
    foreach ($this->fields as $field) {
      $headers[] = $field->renderHeaderForRevisionList();
      $classes[] = $field->getColumnClassForRevisionList();
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders($headers);
    $table->setColumnClasses($classes);
    $table->setCellClasses($cell_classes);

    $table->setNoDataString(DifferentialRevisionListView::NO_DATA_STRING);

    require_celerity_resource('differential-revision-history-css');

    return $table->render();
  }

  public static function getDefaultFields() {
    $selector = DifferentialFieldSelector::newSelector();
    $fields = $selector->getFieldSpecifications();
    foreach ($fields as $key => $field) {
      if (!$field->shouldAppearOnRevisionList()) {
        unset($fields[$key]);
      }
    }

    if (!$fields) {
      throw new Exception(
        "Phabricator configuration has no fields that appear on the list ".
        "interface!");
    }

    return $selector->sortFieldsForRevisionList($fields);
  }

}
