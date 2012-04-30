CREATE TABLE {$NAMESPACE}_file.`file_imagemacro` (
       `id` int unsigned NOT NULL auto_increment PRIMARY KEY,
       `filePHID` varchar(64) NOT NULL,
       `name` varchar(255) NOT NULL
);
