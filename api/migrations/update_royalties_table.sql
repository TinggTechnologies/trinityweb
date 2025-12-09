-- Update royalties table structure
-- Remove fees column and add split_share_deductions column

ALTER TABLE `royalties` 
DROP COLUMN `fees`,
ADD COLUMN `split_share_deductions` DECIMAL(10,2) DEFAULT 0.00 AFTER `adjustments`;

