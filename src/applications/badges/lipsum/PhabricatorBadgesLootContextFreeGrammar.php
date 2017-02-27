<?php

final class PhabricatorBadgesLootContextFreeGrammar
  extends PhutilContextFreeGrammar {

  protected function getRules() {
    return array(
      'start' => array(
        '[jewelry]',
      ),
      'jewelry' => array(
        'Ring [jewelry-suffix]',
        'Ring [jewelry-suffix]',
        '[jewelry-prefix] Ring',
        '[jewelry-prefix] Ring',
        'Amulet [jewelry-suffix]',
        'Amulet [jewelry-suffix]',
        '[jewelry-prefix] Amulet',
        '[jewelry-prefix] Amulet',
        '[jewelry-prefix] Ring [jewelry-suffix]',
        '[jewelry-prefix] Amulet [jewelry-suffix]',
        '[unique-jewelry]',
      ),
      'jewelry-prefix' => array(
        '[mana-prefix]',
      ),

      'jewelry-suffix' => array(
        '[dexterity-suffix]',
        '[dexterity-suffix-jewelry]',
      ),
      'mana-prefix' => array(
        'Hyena\'s (-<11-25> Mana)',
        'Frog\'s (-<1-10> Mana)',
        'Spider\'s (+<10-15> Mana)',
        'Raven\'s (+<15-20> Mana)',
        'Snake\'s (+<21-30> Mana)',
        'Serpent\'s (+<31-40> Mana)',
        'Drake\'s (+<41-50> Mana)',
        'Dragon\'s (+<51-60> Mana)',
      ),
      'dexterity-suffix' => array(
        'of Paralysis (-<6-10> Dexterity)',
        'of Atrophy (-<1-5> Dexterity)',
        'of Dexterity (+<1-5> Dexterity)',
        'of Skill (+<6-10> Dexterity)',
        'of Accuracy (+<11-15> Dexterity)',
        'of Precision (+<16-20> Dexterity)',
      ),
      'dexterity-suffix-jewelry' => array(
        '[dexterity-suffix]',
        '[dexterity-suffix]',
        '[dexterity-suffix]',
        '[dexterity-suffix]',
        '[dexterity-suffix]',
        '[dexterity-suffix]',
        '[dexterity-suffix]',
        '[dexterity-suffix]',
        '[dexterity-suffix]',
        'of Perfection (+<21-30> Dexterity)',
      ),
      'unique-jewelry' => array(
        '[jewelry]',
        '[jewelry]',
        '[jewelry]',
        '[jewelry]',
        '[jewelry]',
        '[jewelry]',
        '[jewelry]',
        '[jewelry]',
        '[unique-ring]',
        '[unique-amulet]',
      ),
      'unique-ring' => array(
        'The Bleeder',
        'The Bramble',
        'Constricting Ring',
        'Empyrean Band',
        'Ring of Engagement',
        'Ring of Regha',
      ),
      'unique-amulet' => array(
        'Optic Amulet',
      ),
    );
  }

}
