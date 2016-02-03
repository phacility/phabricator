<?php

final class PhabricatorMotivatorProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'motivator';

  public function getPanelTypeIcon() {
    return 'fa-coffee';
  }

  public function getPanelTypeName() {
    return pht('Motivator');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfilePanelConfiguration $config) {

    $options = $this->getOptions();
    $name = idx($options, $config->getPanelProperty('source'));
    if ($name !== null) {
      return pht('Motivator: %s', $name);
    } else {
      return pht('Motivator');
    }
  }

  public function buildEditEngineFields(
    PhabricatorProfilePanelConfiguration $config) {
    return array(
      id(new PhabricatorInstructionsEditField())
        ->setValue(
          pht(
            'Motivate your team with inspirational quotes from great minds. '.
            'This panel shows a new quote every day.')),
      id(new PhabricatorSelectEditField())
        ->setKey('source')
        ->setLabel(pht('Source'))
        ->setOptions($this->getOptions()),
    );
  }

  private function getOptions() {
    return array(
      'catfacts' => pht('Cat Facts'),
    );
  }

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {

    $source = $config->getPanelProperty('source');

    switch ($source) {
      case 'catfacts':
      default:
        $facts = $this->getCatFacts();
        break;
    }

    $fact = $this->selectFact($facts);

    switch ($source) {
      case 'catfacts':
      default:
        $fact = array(
          id(new PHUIIconView())->setIcon('fa-paw'),
          ' ',
          $fact,
        );
        break;
    }

    $fact = phutil_tag(
      'div',
      array(
        'class' => 'phui-motivator',
      ),
      $fact);

    $item = $this->newItem()
      ->appendChild($fact);

    return array(
      $item,
    );
  }

  private function getCatFacts() {
    return array(
      pht('Cats purr when they are happy, upset, or asleep.'),
      pht('The first cats evolved on the savanah about 8,000 years ago.'),
      pht(
        'Cats have a tail, two feet, between one and three ears, and two '.
        'other feet.'),
      pht('Cats use their keen sense of smell to avoid feeling empathy.'),
      pht('The first cats evolved in swamps about 65 years ago.'),
      pht(
        'You can tell how warm a cat is by examining the coloration: cooler '.
        'areas are darker.'),
      pht(
        'Cat tails are flexible because they contain thousands of tiny '.
        'bones.'),
      pht(
        'A cattail is a wetland plant with an appearance that resembles '.
        'the tail of a cat.'),
      pht(
        'Cats must eat a diet rich in fish to replace the tiny bones in '.
        'their tails.'),
      pht('Cats are stealthy predators and nearly invisible to radar.'),
      pht(
        'Cats use a special type of magnetism to help them land on their '.
        'feet.'),
      pht(
        'A cat can run seven times faster than a human, but only for a '.
        'short distance.'),
      pht(
        'The largest recorded cat was nearly 11 inches long from nose to '.
        'tail.'),
      pht(
        'Not all cats can retract their claws, but most of them can.'),
      pht(
        'In the wild, cats and racoons sometimes hunt together in packs.'),
      pht(
        'The Spanish word for cat is "cato". The biggest cat is called '.
        '"el cato".'),
      pht(
        'The Japanese word for cat is "kome", which is also the word for '.
        'rice. Japanese cats love to eat rice, so the two are synonymous.'),
      pht('Cats have five pointy ends.'),
      pht('cat -A can find mice hiding in files.'),
      pht('A cat\'s visual, olfactory, and auditory senses, '.
        'Contribute to their hunting skills and natural defenses.'),
      pht(
        'Cats with high self-esteem seek out high perches '.
        'to launch their attacks. Watch out!'),
      pht('Cats prefer vanilla ice cream.'),
      pht('Taco cat spelled backwards is taco cat.'),
    );
  }

  private function selectFact(array $facts) {
    // This is a simple pseudorandom number generator that avoids touching
    // srand(), because it would seed it to a highly predictable value. It
    // selects a new fact every day.

    $seed = ((int)date('Y') * 366) + (int)date('z');
    for ($ii = 0; $ii < 32; $ii++) {
      $seed = ((1664525 * $seed) + 1013904223) % (1 << 31);
    }

    return $facts[$seed % count($facts)];
  }


}
