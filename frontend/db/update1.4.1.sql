set sql_mode = '';
-- REPLACE INTO `q_user` (`account`, `realname`, `nickname`, `password`, `company`, `commiter`) VALUES ('demo', 'demo', 'demo', md5('demo'), 0, '');
REPLACE INTO `q_user` (`company`, `type`, `dept`, `account`, `password`, `role`, `realname`, `pinyin`, `nickname`, `commiter`, `avatar`, `birthday`, `gender`, `email`, `skype`, `qq`, `mobile`, `phone`, `weixin`, `dingding`, `slack`, `whatsapp`, `address`, `zipcode`, `nature`, `analysis`, `strategy`, `join`, `visits`, `visions`, `ip`, `last`, `fails`, `locked`, `feedback`, `ranzhi`, `ldap`, `score`, `scoreLevel`, `deleted`, `clientStatus`, `clientLang`) VALUES (0,'inside',0,'demo',md5('demo'),'','demo','','','','','1970-01-01','f','','','','','','','','','','','','','','','1970-01-01',406,'','10.10.16.8',1660699163,0,'0000-00-00 00:00:00','0','','',0,0,'0','offline','zh-cn');

REPLACE INTO `q_config` (`owner`, `module`, `section`, `key`, `value`) VALUES ('system', 'common', 'global', 'allowAnonymousAccess', 'off');
