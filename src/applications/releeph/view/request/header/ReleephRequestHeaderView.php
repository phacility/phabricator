<?php

final class ReleephRequestHeaderView extends AphrontView {

  const THROW_PARAM = '__releeph_throw';

  private $aphrontRequest;
  private $releephRequest;
  private $releephBranch;
  private $releephProject;
  private $originType;
  private $fieldGroups;

  public function setAphrontRequest(AphrontRequest $request) {
    $this->aphrontRequest = $request;
    return $this;
  }

  public function setReleephProject(ReleephProject $rp) {
    $this->releephProject = $rp;
    return $this;
  }

  public function setReleephBranch(ReleephBranch $rb) {
    $this->releephBranch = $rb;
    return $this;
  }

  public function setReleephRequest(ReleephRequest $rr) {
    $this->releephRequest = $rr;
    return $this;
  }

  public function setOriginType($origin) {
    // For the Edit controller
    $this->originType = $origin;
    return $this;
  }

  public function setReleephFieldGroups(array $field_groups) {
    $this->fieldGroups = $field_groups;
    return $this;
  }

  protected function getOrigin() {
    return $this->originType;
  }

  public function render() {
    require_celerity_resource('releeph-core');
    $all_properties_table = $this->renderFields();

    require_celerity_resource('releeph-colors');
    $status = $this->releephRequest->getStatus();
    $rr_div_class =
      'releeph-request-header '.
      'releeph-request-header-border '.
      'releeph-border-color-'.ReleephRequest::getStatusClassSuffixFor($status);

    $hidden_link = phutil_tag(
      'a',
      array(
        'href'        => '/RQ'.$this->releephRequest->getID(),
        'target'      => '_blank',
        'data-sigil'  => 'hidden-link',
      ),
      '');

    $focus_char = phutil_tag(
      'div',
      array(
        'class' => 'focus-char',
        'data-sigil' => 'focus-char',
      ),
      "\xE2\x98\x86");

    $rr_div = phutil_tag(
      'div',
      array(
        'data-sigil' => 'releeph-request-header',
        'class' => $rr_div_class,
      ),
      array(
        phutil_tag(
          'div',
          array(),
          array(
            phutil_tag(
              'h1',
              array(),
              array(
                $focus_char,
                $this->renderTitleLink(),
                $hidden_link
              )),
            $all_properties_table,
          )),
        phutil_tag(
          'div',
          array(
            'class' => 'button-divider',
          ),
          $this->renderActionButtonsTable())));

    return $rr_div;
  }

  private function renderFields() {
    $field_row_groups = $this->fieldGroups;

    $trs = array();
    foreach ($field_row_groups as $field_column_group) {
      $tds = array();
      foreach ($field_column_group as $side => $fields) {
        $rows = array();
        foreach ($fields as $field) {
          $rows[] = $this->renderOneField($field);
        }
        $pane = phutil_tag(
          'table',
          array(
            'class' => 'fields',
          ),
          $rows);
        $tds[] = phutil_tag(
          'td',
          array(
            'class' => 'side '.$side,
          ),
          $pane);
      }
      $trs[] = phutil_tag(
        'tr',
        array(),
        $tds);
    }

    return phutil_tag(
      'table',
      array(
        'class' => 'panes',
      ),
      $trs);
  }

  private function renderOneField(ReleephFieldSpecification $field) {
    $field
      ->setUser($this->user)
      ->setReleephProject($this->releephProject)
      ->setReleephBranch($this->releephBranch)
      ->setReleephRequest($this->releephRequest);

    $label = $field->renderLabelForHeaderView();
    try {
      $value = $field->renderValueForHeaderView();
    } catch (Exception $ex) {
      if ($this->aphrontRequest->getInt(self::THROW_PARAM)) {
        throw $ex;
      } else {
        $value = $this->renderExceptionIcon($ex);
      }
    }

    if ($value) {
      if (!$label) {
        return phutil_tag(
          'tr',
          array(),
          phutil_tag('td', array('colspan' => 2), $value));
      } else {
        return phutil_tag(
          'tr',
          array(),
          array(
            phutil_tag('th', array(), $label),
            phutil_tag('td', array(), $value)));
      }
    }
  }

  private function renderExceptionIcon(Exception $ex) {
    Javelin::initBehavior('phabricator-tooltips');
    require_celerity_resource('aphront-tooltip-css');
    $throw_uri = $this
      ->aphrontRequest
      ->getRequestURI()
      ->setQueryParam(self::THROW_PARAM, 1);

    $message = $ex->getMessage();
    if (!$message) {
      $message = get_class($ex).' with no message.';
    }

    return javelin_tag(
      'a',
      array(
        'class' => 'releeph-field-error',
        'sigil' => 'has-tooltip',
        'meta'  => array(
          'tip'   => $message,
          'size'  => 400,
          'align' => 'E',
        ),
        'href' => $throw_uri,
      ),
      '!!!');
  }

  private function renderTitleLink() {
    $rq_id = $this->releephRequest->getID();
    $summary = $this->releephRequest->getSummaryForDisplay();
    return phutil_tag(
      'a',
      array(
        'href' => '/RQ'.$rq_id,
      ),
      hsprintf(
        'RQ%d: %s',
        $rq_id,
        $summary));
  }

  private function renderActionButtonsTable() {
    $left_buttons = array();
    $right_buttons = array();

    $user_phid = $this->user->getPHID();
    $is_pusher = $this->releephProject->isAuthoritativePHID($user_phid);
    $is_requestor = $this->releephRequest->getRequestUserPHID() === $user_phid;

    $current_intent = idx(
      $this->releephRequest->getUserIntents(),
      $this->user->getPHID());

    if ($is_pusher) {
      $left_buttons[] = $this->renderIntentButton(true, 'Approve', 'green');
      $left_buttons[] = $this->renderIntentButton(false, 'Reject');
    } else {
      if ($is_requestor) {
        $right_buttons[] = $this->renderIntentButton(true, 'Request');
        $right_buttons[] = $this->renderIntentButton(false, 'Remove');
      } else {
        $right_buttons[] = $this->renderIntentButton(true, 'Want');
        $right_buttons[] = $this->renderIntentButton(false, 'Pass');
      }
    }

    // Allow the pusher to mark a request as manually picked or reverted.
    if ($is_pusher || $is_requestor) {
      if ($this->releephRequest->getInBranch()) {
        $left_buttons[] = $this->renderActionButton(
          'Mark Manually Reverted',
          'mark-manually-reverted');
      } else {
        $left_buttons[] = $this->renderActionButton(
          'Mark Manually Picked',
          'mark-manually-picked');
      }
    }

    $right_buttons[] = phutil_tag(
      'a',
      array(
        'href' => '/releeph/request/edit/'.$this->releephRequest->getID().
                  '?origin='.$this->originType,
        'class' => 'small blue button',
      ),
      'Edit');

    if (!$left_buttons && !$right_buttons) {
      return;
    }

    $cells = array();
    foreach ($left_buttons as $button) {
      $cells[] = phutil_tag('td', array('align' => 'left'), $button);
    }
    $cells[] = phutil_tag('td', array('class' => 'wide'), '');
    foreach ($right_buttons as $button) {
      $cells[] = phutil_tag('td', array('align' => 'right'), $button);
    }

    $table = phutil_tag(
      'table',
      array(
        'class' => 'buttons',
      ),
      phutil_tag(
        'tr',
        array(),
        $cells));

    return $table;
  }

  private function renderIntentButton($want, $name, $class = null) {
    $current_intent = idx(
      $this->releephRequest->getUserIntents(),
      $this->user->getPHID());

    if ($current_intent) {
      // If this is a "want" button, and they already want it, disable the
      // button (and vice versa for the "pass" case.)
      if (($want && $current_intent == ReleephRequest::INTENT_WANT) ||
          (!$want && $current_intent == ReleephRequest::INTENT_PASS)) {

        $class .= ' disabled';
      }
    }

    $action = $want ? 'want' : 'pass';
    return $this->renderActionButton($name, $action, $class);
  }

  private function renderActionButton($name, $action, $class=null) {
    $attributes = array(
      'class' => 'small button '.$class,
      'sigil' => 'releeph-request-state-change '.$action,
      'meta'  => null,
    );

    if ($class != 'disabled') {
      // NB the trailing slash on $uri is critical, otherwise the URI will
      // redirect to one with a slash, which will turn our GET into a POST.
      $attributes['meta'] = sprintf(
        '/releeph/request/action/%s/%d/',
        $action,
        $this->releephRequest->getID());
    }

    return javelin_tag('a', $attributes, $name);
  }

}
