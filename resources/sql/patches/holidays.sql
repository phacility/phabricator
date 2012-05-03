CREATE TABLE {$NAMESPACE}_calendar.calendar_holiday (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `day` date NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE `day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
