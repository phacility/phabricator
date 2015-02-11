<?php

final class PhabricatorApplicationLaunchView extends AphrontTagView {

  private $application;
  private $status;

  public function setApplication(PhabricatorApplication $application) {
    $this->application = $application;
    return $this;
  }

  public function setApplicationStatus(array $status) {
    $this->status = $status;
    return $this;
  }

  protected function getTagName() {
    return $this->application ? 'a' : 'div';
  }

  protected function getTagAttributes() {
    $application = $this->application;
    return array(
      'class' => array('phabricator-application-launch-container'),
      'href'  => $application ? $application->getBaseURI() : null,
    );
  }

  protected function getTagContent() {
    $application = $this->application;

    require_celerity_resource('phabricator-application-launch-view-css');

    $content = array();
    $icon = null;
    if ($application) {
      $content[] = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-application-launch-name',
        ),
        $application->getName());

      $content[] = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-application-launch-description',
        ),
        $application->getShortDescription());

      $counts = array();
      $text = array();
      if ($this->status) {
        foreach ($this->status as $status) {
          $type = $status->getType();
          $counts[$type] = idx($counts, $type, 0) + $status->getCount();
          if ($status->getCount()) {
            $text[] = $status->getText();
          }
        }
      }

      $attention = PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION;
      $warning = PhabricatorApplicationStatusView::TYPE_WARNING;
      if (!empty($counts[$attention]) || !empty($counts[$warning])) {
        $count = idx($counts, $attention, 0);
        $count1 = $count2 = '';
        if ($count > 0) {
          $count1 = phutil_tag(
          'span',
          array(
            'class' => 'phabricator-application-attention-count',
          ),
          PhabricatorApplication::formatStatusCount($count));
        }


        if (!empty($counts[$warning])) {
          $count2 = phutil_tag(
          'span',
          array(
            'class' => 'phabricator-application-warning-count',
          ),
          PhabricatorApplication::formatStatusCount($counts[$warning]));
        }
        if (nonempty($count1) && nonempty($count2)) {
          $numbers = array($count1, ' / ', $count2);
        } else {
          $numbers = array($count1, $count2);
        }

        Javelin::initBehavior('phabricator-tooltips');
        $content[] = javelin_tag(
          'span',
          array(
            'sigil' => 'has-tooltip',
            'meta' => array(
              'tip' => implode("\n", $text),
              'size' => 300,
              'align' => 'E',
            ),
            'class' => 'phabricator-application-launch-attention',
          ),
          $numbers);
      }

      $classes = array();
      $classes[] = 'phabricator-application-launch-icon';
      $styles = array();

      if ($application->getIconURI()) {
        $styles[] = 'background-image: url('.$application->getIconURI().')';
      } else {
        $classes[] = $application->getFontIcon();
        $classes[] = 'phui-icon-view';
        $classes[] = 'phui-font-fa';
      }

      $icon = phutil_tag(
        'span',
        array(
          'class' => implode(' ', $classes),
          'style' => nonempty(implode('; ', $styles), null),
        ),
        '');
    }

    return array(
      $icon,
      $content,
    );
  }

}
