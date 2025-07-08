-- Migration: Add title field to downloads table
-- Date: 2025-01-08

ALTER TABLE downloads ADD COLUMN IF NOT EXISTS title VARCHAR(255) DEFAULT NULL AFTER url;
ALTER TABLE downloads ADD COLUMN IF NOT EXISTS download_id VARCHAR(32) DEFAULT NULL AFTER id;
ALTER TABLE downloads ADD INDEX IF NOT EXISTS idx_download_id (download_id);
