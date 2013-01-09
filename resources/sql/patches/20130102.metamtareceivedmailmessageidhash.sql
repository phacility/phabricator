ALTER TABLE `{$NAMESPACE}_metamta`.`metamta_receivedmail`
  ADD `messageIDHash` CHAR(12) BINARY NOT NULL,
  ADD KEY `key_messageIDHash` (`messageIDHash`);
