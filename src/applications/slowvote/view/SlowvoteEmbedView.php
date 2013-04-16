<?php

/**
 * @group slowvote
 */
final class SlowvoteEmbedView extends AphrontView {

  private $poll;
  private $options;
  private $viewerChoices;

  public function setPoll(PhabricatorSlowvotePoll $poll) {
    $this->poll = $poll;
    return $this;
  }

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function setViewerChoices(array $viewer_choices) {
    $this->viewerChoices = $viewer_choices;
    return $this;
  }

  public function render() {

    if (!$this->poll) {
      throw new Exception("Call setPoll() before render()!");
    }

    if (!$this->options) {
      throw new Exception("Call setOptions() before render()!");
    }

    if ($this->poll->getShuffle()) {
      shuffle($this->options);
    }

    require_celerity_resource('phabricator-slowvote-css');

    $user_choices = array();
    if (!empty($this->viewerChoices)) {
      $user_choices = mpull($this->viewerChoices, null, 'getOptionID');
    }

    $options = array();
    $ribbon_colors = array('#DF0101', '#DF7401', '#D7DF01', '#74DF00',
      '#01DF01', '#01DF74', '#01DFD7', '#0174DF', '#0101DF', '#5F04B4',
      '#B404AE');
    shuffle($ribbon_colors);

    foreach ($this->options as $option) {
      $classes = 'phabricator-slowvote-embed-option-text';

      $selected = '';

      if (idx($user_choices, $option->getID(), false)) {
        $classes .= ' phabricator-slowvote-embed-option-selected';
        $selected = '@';
      }

      $option_text = phutil_tag(
        'div',
        array(
          'class' => $classes
        ),
        $selected.$option->getName());

      $options[] = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-slowvote-embed-option',
          'style' =>
            sprintf('border-left: 7px solid %s;', array_shift($ribbon_colors))
        ),
        array($option_text));
    }

    $link_to_slowvote = phutil_tag(
      'a',
      array(
        'href' => '/V'.$this->poll->getID()
      ),
      $this->poll->getQuestion());

    $header = phutil_tag(
      'div',
      array(),
      array('V'.$this->poll->getID().': ', $link_to_slowvote));

    $body = phutil_tag(
      'div',
      array(),
      $options);

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-slowvote-embed'
      ),
      array($header, $body));
  }
}
