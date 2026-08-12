DELIMITER $$
DROP PROCEDURE IF EXISTS add_col_if_not_exists$$
CREATE PROCEDURE add_col_if_not_exists(
    IN p_table_name VARCHAR(100),
    IN p_col_name   VARCHAR(100),
    IN p_alter_stmt TEXT
)
BEGIN
    DECLARE col_exists INT;
    SELECT COUNT(*) INTO col_exists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table_name
      AND COLUMN_NAME  = p_col_name;
    IF col_exists = 0 THEN
        SET @ddl = p_alter_stmt;
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DROP PROCEDURE IF EXISTS add_index_if_not_exists$$
CREATE PROCEDURE add_index_if_not_exists(
    IN p_table_name VARCHAR(100),
    IN p_index_name VARCHAR(100),
    IN p_alter_stmt TEXT
)
BEGIN
    DECLARE idx_exists INT;
    SELECT COUNT(*) INTO idx_exists
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME    = p_table_name
      AND INDEX_NAME     = p_index_name;
    IF idx_exists = 0 THEN
        SET @ddl = p_alter_stmt;
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL add_col_if_not_exists('tblpos_product_bom', 'requires_modifier_type',
    'ALTER TABLE `tblpos_product_bom` ADD COLUMN `requires_modifier_type` ENUM(''modifier'',''item_modifier_option'') NULL');
CALL add_col_if_not_exists('tblpos_product_bom', 'requires_modifier_id',
    'ALTER TABLE `tblpos_product_bom` ADD COLUMN `requires_modifier_id` INT UNSIGNED NULL');
CALL add_index_if_not_exists('tblpos_product_bom', 'requires_modifier_id',
    'ALTER TABLE `tblpos_product_bom` ADD KEY `requires_modifier_id` (`requires_modifier_id`)');

DROP PROCEDURE IF EXISTS add_col_if_not_exists;
DROP PROCEDURE IF EXISTS add_index_if_not_exists;
