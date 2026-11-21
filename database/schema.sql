-- RolForge database schema
-- Generated from the current RolForge database.
-- The legacy `usuarios` table has been removed.

CREATE DATABASE IF NOT EXISTS `rolforge` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rolforge`;

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `characters` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `experience` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `strength` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `dexterity` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `constitution` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `intelligence` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `wisdom` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `charisma` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `hp` int(11) NOT NULL DEFAULT 0,
  `max_hp` int(11) NOT NULL DEFAULT 0,
  `temporary_hp` int(11) NOT NULL DEFAULT 0,
  `armor_class` int(11) NOT NULL DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `character_choices` (
  `id` int(10) UNSIGNED NOT NULL,
  `character_id` int(10) UNSIGNED NOT NULL,
  `source_type` varchar(50) NOT NULL,
  `source_id` varchar(255) NOT NULL,
  `choice_key` varchar(100) NOT NULL,
  `value` varchar(255) NOT NULL,
  `value_source` varchar(50) DEFAULT NULL,
  `value_source_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `character_data` (
  `id` int(10) UNSIGNED NOT NULL,
  `character_id` int(10) UNSIGNED NOT NULL,
  `data_key` varchar(100) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `character_entities` (
  `id` int(10) UNSIGNED NOT NULL,
  `character_id` int(10) UNSIGNED NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_name` varchar(150) NOT NULL,
  `source` varchar(50) NOT NULL,
  `source_id` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `character_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `character_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `source` varchar(50) DEFAULT NULL,
  `source_id` varchar(255) DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `equipped` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `character_proficiencies` (
  `id` int(10) UNSIGNED NOT NULL,
  `character_id` int(10) UNSIGNED NOT NULL,
  `proficiency_type` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `source` varchar(50) DEFAULT NULL,
  `source_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `character_skills` (
  `id` int(10) UNSIGNED NOT NULL,
  `character_id` int(10) UNSIGNED NOT NULL,
  `skill` varchar(50) NOT NULL,
  `proficient` tinyint(1) NOT NULL DEFAULT 0,
  `expertise` tinyint(1) NOT NULL DEFAULT 0,
  `bonus` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `character_spells` (
  `id` int(10) UNSIGNED NOT NULL,
  `character_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `source` varchar(50) NOT NULL,
  `source_id` varchar(255) NOT NULL,
  `prepared` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personajes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `sistema` varchar(100) NOT NULL,
  `datos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`datos`)),
  `publico` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `characters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `character_choices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_choices_character` (`character_id`),
  ADD KEY `idx_choices_source` (`source_type`,`source_id`);

ALTER TABLE `character_data`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_character_data` (`character_id`,`data_key`);

ALTER TABLE `character_entities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_character_entities_character` (`character_id`),
  ADD KEY `idx_character_entities_source` (`source`,`source_id`);

ALTER TABLE `character_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_character_items` (`character_id`);

ALTER TABLE `character_proficiencies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proficiencies_character` (`character_id`);

ALTER TABLE `character_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_character_skill` (`character_id`,`skill`);

ALTER TABLE `character_spells`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_character_spells` (`character_id`);

ALTER TABLE `personajes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `characters`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

ALTER TABLE `character_choices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=241;

ALTER TABLE `character_data`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

ALTER TABLE `character_entities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

ALTER TABLE `character_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

ALTER TABLE `character_proficiencies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

ALTER TABLE `character_skills`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=271;

ALTER TABLE `character_spells`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

ALTER TABLE `personajes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

ALTER TABLE `characters`
  ADD CONSTRAINT `characters_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `character_choices`
  ADD CONSTRAINT `character_choices_ibfk_1` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE;

ALTER TABLE `character_data`
  ADD CONSTRAINT `character_data_ibfk_1` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE;

ALTER TABLE `character_entities`
  ADD CONSTRAINT `character_entities_ibfk_1` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE;

ALTER TABLE `character_items`
  ADD CONSTRAINT `character_items_ibfk_1` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE;

ALTER TABLE `character_proficiencies`
  ADD CONSTRAINT `character_proficiencies_ibfk_1` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE;

ALTER TABLE `character_skills`
  ADD CONSTRAINT `character_skills_ibfk_1` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE;

ALTER TABLE `character_spells`
  ADD CONSTRAINT `character_spells_ibfk_1` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE;

ALTER TABLE `personajes`
  ADD CONSTRAINT `personajes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS=1;
