-- Add release schedule fields to tracks table
-- Run this migration to add release_date, release_time, and worldwide_release columns

ALTER TABLE `tracks` 
ADD COLUMN `release_date` DATE NULL AFTER `audio_style`,
ADD COLUMN `release_time` VARCHAR(20) NULL AFTER `release_date`,
ADD COLUMN `worldwide_release` TINYINT(1) NOT NULL DEFAULT 0 AFTER `release_time`;

