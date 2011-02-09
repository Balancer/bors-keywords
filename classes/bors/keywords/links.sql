-- Поменяйте <<project>> на имя вашего проекта

CREATE TABLE IF NOT EXISTS <<project>>_keyword_links (
  `keyword_id` INT(10) UNSIGNED NOT NULL,
  `target_class_id` INT(10) UNSIGNED NOT NULL,
  `target_class_name` VARCHAR(64) CHARACTER SET latin1 COLLATE latin1_general_ci,
  `target_object_id` VARCHAR(255),
  `target_create_time` INT(10) UNSIGNED,
  `target_modify_time` INT(10) UNSIGNED,
  `target_owner_id` INT(10) UNSIGNED,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `link_type_id` INT(10) UNSIGNED COMMENT 'Тип связи. Автоматическая, ручная и т.п.',
  `was_auto` INT(10) UNSIGNED COMMENT 'Связь была добавлена вручную или автоматом',
  `target_folder_id` INT(10) UNSIGNED COMMENT 'ID контейнера контейнеров. Например, forum_id',
  `target_container_class_name` VARCHAR(64) CHARACTER SET latin1 COLLATE latin1_general_ci,
  `target_container_class_id` INT(10) UNSIGNED,
  `target_container_object_id` VARCHAR(64) CHARACTER SET latin1 COLLATE latin1_general_ci,

  PRIMARY KEY (`keyword_id`,`target_class_id`,`target_object_id`),

  KEY `object` (`target_class_id`,`target_object_id`),
  KEY `keyword_id` (`keyword_id`),
  KEY `sort_order` (`sort_order`),
  KEY `target_owner_id` (`target_owner_id`),
  KEY `target_modify_time` (`target_modify_time`),
  KEY `target_create_time` (`target_create_time`),
  KEY `link_type_id` (`link_type_id`),
  KEY `was_auto` (`was_auto`),
  KEY `target_class_name` (`target_class_name`),
  KEY `keyword_id_3` (`keyword_id`,`target_class_id`,`target_object_id`,`target_create_time`),

  CONSTRAINT `bors_keywords_index_ibfk_1` FOREIGN KEY (`target_class_id`) REFERENCES `bors_class_names` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bors_keywords_index_ibfk_2` FOREIGN KEY (`target_class_name`) REFERENCES `bors_class_names` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bors_keywords_index_ibfk_3` FOREIGN KEY (`keyword_id`) REFERENCES <<project>>_keywords (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
