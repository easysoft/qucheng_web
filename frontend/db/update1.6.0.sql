ALTER TABLE `q_instance` ADD COLUMN `ldapSettings` text AFTER `domain`;
ALTER TABLE `q_instance` ADD COLUMN `ldapSnippetName` char(30) NULL AFTER `domain`;
