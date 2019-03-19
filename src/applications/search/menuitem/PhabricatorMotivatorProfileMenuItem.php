<?php

final class PhabricatorMotivatorProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'motivator';

  public function getMenuItemTypeIcon() {
    return 'fa-coffee';
  }

  public function getMenuItemTypeName() {
    return pht('Motivator');
  }

  public function canAddToObject($object) {
    return ($object instanceof PhabricatorHomeApplication);
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {

    $options = $this->getOptions();
    $name = idx($options, $config->getMenuItemProperty('source'));
    if ($name !== null) {
      return pht('Motivator: %s', $name);
    } else {
      return pht('Motivator');
    }
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorInstructionsEditField())
        ->setValue(
          pht(
            'Motivate your team with inspirational quotes from great minds. '.
            'This menu item shows a new quote every day.')),
      id(new PhabricatorSelectEditField())
        ->setKey('source')
        ->setLabel(pht('Source'))
        ->setOptions($this->getOptions()),
    );
  }

  private function getOptions() {
    return array(
      'catfacts' => pht('Cat Facts'),
      'dogfacts' => pht('Dog Facts'),
      'sciencefacts' => pht('Science Facts'),
      'historyfacts' => pht('History Facts'),
      'foodfacts' => pht('Food Facts'),
    );
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $source = $config->getMenuItemProperty('source');

    switch ($source) {
      case 'catfacts':
        $facts = $this->getCatFacts();
        $fact_name = pht('Cat Facts');
        $fact_icon = 'fa-paw';
        break;
      case 'dogfacts':
        $facts = $this->getDogFacts();
        $fact_name = pht('Dog Facts');
        $fact_icon = 'fa-dog';
        break;
      case 'sciencefacts':
        $facts = $this->getScienceFacts();
        $fact_name = pht('Science Facts');
        $fact_icon = 'fa-atom';
        break;
      case 'historyfacts':
        $facts = $this->getHistoryFacts();
        $fact_name = pht('History Facts');
        $fact_icon = 'fa-monument';
        break;
      case 'foodfacts':
        $facts = $this->getFoodFacts();
        $fact_name = pht('Food Facts');
        $fact_icon = 'fa-utensils';
        break;
      default:
        $facts = $this->getCatFacts();
        $fact_name = pht('Cat Facts');
        $fact_icon = 'fa-paw';
        break;
    }

    $fact_text = $this->selectFact($facts);

    $item = $this->newItem()
      ->setName($fact_name)
      ->setIcon($fact_icon)
      ->setTooltip($fact_text)
      ->setHref('#');

    return array(
      $item,
    );
  }

  private function getCatFacts() {
    return array(
      pht('Cats purr when they are happy, upset, or asleep.'),
      pht('The first cats evolved on the savannah about 8,000 years ago.'),
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
        'In the wild, cats and raccoons sometimes hunt together in packs.'),
      pht(
        'The Spanish word for cat is "cato". The biggest cat is called '.
        '"el cato".'),
      pht(
        'The Japanese word for cat is "kome", which is also the word for '.
        'rice. Japanese cats love to eat rice, so the two are synonymous.'),
      pht('Cats have five pointy ends.'),
      pht('A cat can find mice hiding in files.'),
      pht('A cat\'s visual, olfactory, and auditory senses, '.
        'Contribute to their hunting skills and natural defenses.'),
      pht(
        'Cats with high self-esteem seek out high perches '.
        'to launch their attacks. Watch out!'),
      pht('Cats prefer vanilla ice cream.'),
      pht('Taco cat spelled backwards is taco cat.'),
      pht(
        'Cats will often bring you their prey because they feel sorry '.
        'for your inability to hunt.'),
      pht('Cats spend most of their time plotting to kill their owner.'),
      pht('Outside of the CAT scan, cats have made almost no contributions '.
        'to modern medicine.'),
      pht('In ancient Egypt, the cat-god Horus watched over all cats.'),
      pht('The word "catastrophe" has no etymological relationship to the '.
          'word "cat".'),
      pht('Many cats appear black in low light, suffering a -2 modifier to '.
          'luck rolls.'),
      pht('The popular trivia game "World of Warcraft" features a race of '.
          'cat people called the Khajiit.'),
    );
  }

private function getDogFacts() {
    return array(
        pht('Dogs have a sense of time. They can even predict future events, '.
               'such as regular walk times.'),
        pht('A study at UCSD claims that your dog can genuinely get jealous when. '.
               'when they see you display affection for another creature.'),
        pht('The United States has the highest pet dog population in the world. '.
               'Approximately 75.8 million, in fact.'),
        pht('Seeing eye dogs are trained to do their "business" on command. This '.
                'way their owner can clean it up a bit easier.'),
        pht('The Saluki is the worlds oldest dog breed. They appear in ancient '.
                'Egyptian tombs dating back to 2100 B.C.'),\
        pht('Dachshunds were originally bred to fight badgers.'),
        pht('More than half of all U.S. Presidents have owned dogs.'),
        pht('Your dog can smell your feelings.'),
        pht('Dogs have wet noses because it helps to absorb scent chemicals.'),
        pht('Dogs only have sweat glands in their paws.'),
        pht('On average, a dog’s mouth exerts 320 pounds of pressure.'),
        pht('Giving your dog some of your clothing can help curb separation '.
                'anxiety').
        pht('The basenji is the only breed of dog that can not bark, but they '.
                'can yodel!').
        pht('A dog named Duke is the mayor of Cormorant, Minnesota.'),
        pht('Dogs see in more than black & white. They can also see blue '.
                'and yellow!'),
        pht('Bloodhounds are able to trace scents that are over 300 hours old!'),
        pht('Newfoundlands are excellent swimmer because they have webbed feet.'),
        pht('Dalmation puppies are born completely white and grow their spots '.
                'over time.'),
        pht('Dogs have at least 18 muscles in each ear!'),
        pht('Big happy "helicopter" tail wagging is one sign of a really nice dog'),
        pht('Tired puppies get cranky just like little kids. If you have a fussy '.
                'puppy, try nap time.'),
        pht('New puppies have heat sensors in their noses to help find their '.
                'moms while their eyes and ears are closed.'),
        pht('Dogs have three eyelids, including one to keep their eyes moist '.
                'and protected.'),
        pht('In 1969, Lassie was the first animal inducted into the Animal Hall '.
                'of Fame.'),
        pht('The Alaskan Malamute can withstand temperatures as low as 70 '.
                ' degrees (fahrenheit) below zero.'),
        pht('Spiked dog collars were used to protect dogs\' throats from wolf '.
                'attacks in ancient Greece.'),
        pht('Revolutionary War soldiers sometimes brought their dogs with them '.
                'into battle. Such was the case with George Washington and his '.
                'dog, Sweetlips.')
        
    );
  }

private function getScienceFacts() {
    return array(
      pht('Babies have around 100 more bones than adults.'),
      pht('The Eiffel Tower can be 15cm taller during the summer due to expanding metal from the heat.'),
      pht('20% of Earth\'s oxygen is produced by the Amazon Rainforest'),
      pht('Some metals are so reactive that they can explode on contact with water.'),
      pht('A teaspoonful of neutron star would weigh 6 billion tons!'),
      pht('Hawaii moves 7.5cm closer to Alaska every year.'),
      pht('Chark is made of trillions of microscopic plankton fossils.'),
      pht('Polar bears are nearly inddetectable by infrared cameras.'),
      pht('It takes 8 minutes, 19 seconds for light to travel rom the Sun to the Earth.'),
      pht('If you took out all of the empty space in our atoms, the human race could fit in the volume of a sugar cube.'),
      pht('Stomach acit is strong enough to dissolve stainless steel!'),
      pht('Venus is the only planet to spin clockwise.'),
      pht('A flea can accelerate faster than the Space Shuttle'),
      pht('There is enough DNA in the average person\'s body to stretch from the Sun to Pluto and back -- 17 times.'),
      pht('It takes a photon up to 40,000 years to travel from the core of the sun to its\'s surface, but only 8 minutes to travel the rest of the way to Earth'),
      pht('There are 8 times as many atoms in a teaspoonful of water as there is teaspoonfuls of water in the Atlantic Ocean.'),
      pht('In an entire lifetime, the average person walks the equivalent of five times around the world.'),
      pht('There are over two dozen states of matter (that we know of).'),
      pht('Killer whales are actually dolphins.'),
      pht('Grasshoppers have ears in their bellies.'),
      pht('You can\'t taste food without saliva.'),
      pht('Octopuses have three hearts, nine brains, and blue blood.'),
      pht('An individual blood cell takes about 60 seconds to make a complete circuit of the body.'),
      pht('The known universe is made up of around 50,000,000,000 galaxies.'),
      pht('Oxygen gas is colorless and odorless, but in its\' liquid and solid forms, it can appear pale blue.'),
      pht('Only one letter doesn\'t appear on the periodic table: The letter "J".'),
      pht('Potassium decays, meaning that is\'s radioactive, meaning that bananas are radioactive. Don\'t worry. You\'d have to eat 10,000,000 bananas to die of radiation poisoning.'),
      pht('Because of the Mpemba effect, hot water freezes faster than cold water and cold water heats faster than hot water.'),
      pht('Only one type of mammal can fly: Bats.'),
      pht('Superfluids can move without friction.'),
      pht('We still don\'t know everything about the human body. A new organ was discovered just recently (in 2017). The "Mesentery".'),
      pht('Zealandia, an underwater land-mass fits all of the criteria for being a drowned continent!'),
      pht('Lungs do more than just help us breathe -- they also make blood!'),
      pht('Time Crystals are a newer state of matter that potentially defy the laws of physics.'),
      pht('Great apes, including chimpzees and orangutans, have absolutely no appreciation of music whatsoever.'),
      pht('Bees have been shown to understand the concept of 0 and can count to 4.'),
    );
  }

private function getHistoryFacts() {
    return array(
      pht('In 1926, Arctic explorer Peter Freuchen was trapped under an avalanche while on an expedition. He escaped from death by fashioning a shiv out of his own feces and amputating his foot.'),
      pht('In ancient Egypt, servants were smeared with honey to attract flies away from the pharaoh.'),
      pht('Roman Catholics in Bavaria founded a secret society in 1740 called the Order of the Pug. New members had to wear dog collars and scratch at the door to get in.'),
      pht('The first fax was sent while people were still traveling the Oregon Trail.'),
      pht('Before Abraham Lincoln became a politician, he was a champion wrestler. With more than 300 bouts under his belt, Lincoln only lost one match in his career and was inducted into the National Wrestling Hall Of Fame in 1992.'),
      pht('In the Victorian era, it was popular for people to photograph relatives after they had died, often placing them in lifelike poses.'),
      pht('All British tanks since 1945 have included equipment to make tea.'),
      pht('One of history\'s most successful pirates was a Chinese prostitute named Ching Shih. She commanded a fleet of over 1,500 ships and 80,000 sailors.'),
      pht('Roman Emperor Gaius, also known as Caligula, made one of his favorite horses a senator.')
      pht('On his death bed, Voltaire was asked to renounce Satan by a visiting priest. He replied, "This is no time to be making new enemies."'),
      pht('The town of Salem, New Jersey once held a trial against tomatoes in 1820 because of the widespread belief they were poisonous. The case ended after Colonel Robert Gibbon Johnson ate a basket of tomatoes without ill consequence.'),
      pht('Potatoes were only introduced to Ireland in the late 1500s after being discovered by Spanish Conquistadors in Peru.'),
      pht('Jeanette Rankin became the first female member of Congress in America in 1916, four years before women were given the right to vote.'),
      pht('In 1493, Columbus thought he saw mermaids -- they were "not as pretty as they are depicted, for somehow in the face they look like men." It\'s suspected he saw a manatee.'),
      pht('At the height of his popularity, Charlie Chaplin entered a Charlie Chaplin look-a-like competition in San Francisco. He came in 20th place.'),
      pht('History\'s shortest war was between England and Zanzibar. It lasted only 38 minutes.'),
      pht('In 1998, 1,200 bones from some ten human bodies were found in the basement of Ben Franklin\'s house. The bodies were used in the study of human anatomy, scholars believe.'),
      pht('Seven of the ten deadliest wars in human history have been in China. The Taiping Rebellion alone had twice as many deaths as World War 1.'),
      pht('Between 1900 and 1920, Tug of War was an Olympic event.'),
      pht('Thomas Jefferson and John Adams died hours apart on the same day, July 4, 1826, the 50th anniversary of American independence.'),
      pht('Serial killer Ted Bundy once saved a young child from drowning and also received a commendation from the Seattle Police Department for chasing down a purse snatcher.'),
      pht('The Civil War began on the farm of Wilmer McLean, who then moved more than a hundred miles away to escape the fighting, only to have the war end inside his new house at Appomattox.'),
      pht('One of history\'s longest wars likely lasted for 335 years and was between the Netherlands and the Isles of Sicily. Not a single person was killed.'),
      pht('The current 50 star U.S. flag was designed by 17-year-old Robert Heft for a school project. He received a B-.'),
      pht('Romans used urine as mouthwash. Urine contains ammonia, which is one of the best natural cleaning agents on the planet.'),
      pht('Lord Byron kept a pet bear in his dorm room while studying at Cambridge University.'),
      pht('Turkeys were once worshipped like gods by the mayans'),
      pht('Paul Revere neveractually shouted, "The Brisish Are Coming!"'),
      pht('Napoleon was once attacked by a horde of bunnies.'),
      pht('The U.S. government literally poisoned alcohol during prohibition.'),
      pht('Using forks used to be seen as sacrilegious in Italy in the 11th Century.'),
      pht('The last Queen of Eqypt, Cleopatra wasn\'t Egyptian. As best as Historians can tell, she was Greek and a descendant of Alexander the Great\'s Macedonian general Ptolemy.'),
      pht('Ketchup was sold in the 1830s as medicine to treat indigestion.'),
      pht('George Washington wasn\'t the first person to be on the face of the $1 bill. The first was Salmon P. Chase, the Secretary of Treasury during the Civial War in 1862.'),
    );
  }

private function getFoodFacts() {
    return array(
      pht('The oldest evidence for soup is from 6,000 B.C. and calls for hippopotamus and sparrow meat.'),
      pht('Pringles once had a lawsuit trying to prove that they weren\'t really potato chips.'),
      pht('Pound cake got its name from its original recipe, which called for a pound each of butter, eggs, sugar, and flour.'),
      pht('Ripe cranberries will bounce like rubber balls.'),
      pht('An average ear of corn has an even number of rows, usually 16.'),
      pht('Most wasabi consumed is not real wasabi, but colored horseradish.'),
      pht('Central Appalachia\'s tooth decay problem is referred to as Mountain Dew mouth, due to the beverage\'s popularity in the region.'),
      pht('Apples belong to the rose family, as do pears and plums.'),
      pht('Oklahoma\'s state vegetable is the watermelon.'),
      pht('One of the most popular pizza toppings in Brazil is green peas.'),
      pht('About 70% of olive oil being sold is not actually pure olive oil.'),
      pht('Real aged balsamic vinegar actually costs anywhere from $75 to $400 or more.'),
      pht('Store bought 100% "real" orange juice is 100% artificially flavored.'),
      pht('The most expensive pizza in the world costs $12,000 and takes 72 hours to make.'),
      pht('The winner of the 2013 Nathan\'s Hot Dog Eating contest consumed 69 hot dogs in 10 minutes.'),
      pht('The Dunkin\' Donuts in South Korea offer doughnut flavors such as Kimchi Croquette and Glazed Garlic.'),
      pht('Chocolate was once used as currency.'),
      pht('There is an amusement park in Tokyo that offers Raw Horse Flesh-flavored ice cream.'),
      pht('The tea bag was created by accident, as tea bags were originally sent as samples.'),
      pht('A Cinnabon® Classic has less sugar than a 20-oz. bottle of Pepsi.'),
      pht('Castoreum, which is used as vanilla flavoring in candies, baked goods, etc., is actually a secretion from the anal glands of beavers.'),
      pht('Humans are born craving sugar.'),
      pht('Radishes are members of the same family as cabbages.'),
      pht('The red food-coloring carmine — used in Skittles and other candies — is made from boiled cochineal bugs, a type of beetle.'),
      pht('Casu Marzu is a cheese found in Sardinia that is purposely infested with maggots.'),
      pht('The softening agent L-cysteine — used in some bread — is made from human hair and duck feathers.'),
      pht('The potentially fatal brain mushroom is considered a delicacy in Scandinavia, Eastern Europe, and the upper Great Lakes region of North America.'),
      pht('If improperly prepared, fugu, or puffer fish, can kill you since it contains a toxin 1,200 times deadlier than cyanide.'),
      pht('It is almost impossible to find out what all the ingredients are that Papa John\'s uses in its pizzas.'),
      pht('Coconut water can be used as blood plasma.'),
      pht('Milt, which is a delicacy around the world, is fish sperm.'),
      pht('McDonald\'s sells 75 hamburgers every second of every day.'),
      pht('Ranch dressing contains titanium dioxide, which is used to make it appear whiter. The same ingredient is used in sunscreen and paint for the same effect.'),
      pht('Three plates of food at a Chinese buffet will net you about 3,000 calories.'),
      pht('To make jelly beans shiny, shellac is used, which is made from Kerria lacca insect excretions.'),
      pht('One fast food hamburger may contain meat from 100 different cows.'),
      pht('Ketchup was used as a medicine in the 1800s to treat diarrhea, among other things.'),
      pht('Fruit-flavored snacks are made with the same wax used on cars.'),
      pht('Peanuts aren\'t nuts, they\'re legumes.'),
      pht('No matter what color Fruit Loop you eat, they all taste the same.'),
      pht('The most expensive fruit in the world is the Japanese Yubari cantaloupe, and two melons once sold at auction for $23,500.'),
      pht('Arachibutyrophobia is the fear of peanut butter sticking to the roof of your mouth.'),
      pht('When taken in large doses nutmeg works as a hallucinogen.'),
      pht('Eating bananas can help fight depression.'),
      pht('Canola oil was originally called rapeseed oil, but rechristened by the Canadian oil industry in 1978 to avoid negative connotations. "Canola" is short for "Canadian oil."'),
      pht('Honey is made from nectar and bee vomit.'),
      pht('Yams and sweet potatoes are not the same thing.'),
      pht('Chuck E. Cheese pizza restaurants were created by the inventor of the Atari video game system, Nolan Bushnell.'),
      pht('The twists in pretzels are meant to look like arms crossed in prayer.'),
      pht('"SPAM" is short for spiced ham.'),
      pht('To add nutrition, a lot of milk, juice, and yogurts enrich the food with EPA and DHA omega-3 fatty acids. In other words, your OJ contains fish oil.'),
      pht('There\'s an enzyme in pineapple called bromelain that helps to break down proteins and can also ruin your tastebuds.'),
      pht('Apples float in water, because 25% of their volume is made of air.'),
      pht('The popsicle was invented by an 11-year-old in 1905.'),
      pht('Crackers, like Saltines, have small holes in them to prevent air bubbles from ruining the baking process.'),
      pht('The reason why peppers taste hot is because of a chemical compound called capsaicin, which bonds to your sensory nerves and tricks them into thinking your mouth is actually being burned.'),
      pht('One of the most hydrating foods to eat is the cucumber, which is 96% water.'),
      pht('There are 7,500 varieties of apples grown throughout the world, and if you tried a new variety each day, it would take you 20 years to try them all.'),
      pht('The most popular carrots used to be purple.'),
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
