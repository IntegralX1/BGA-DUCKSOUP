-- ======================================================
-- Duck Soup: The Restaurant Game
-- Migration: restaurant_card_migration.sql
-- Adds effect_json column to restaurant_card table
-- to support tiered Critic card payout grids.
-- Run once against the BGA game database.
-- ======================================================

ALTER TABLE `restaurant_card`
    ADD COLUMN `effect_json` TEXT NULL DEFAULT NULL
        COMMENT 'JSON payout grid for Critic cards; NULL for all other card types'
    AFTER `effect_value`;
