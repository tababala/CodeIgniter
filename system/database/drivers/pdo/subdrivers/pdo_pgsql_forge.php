<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.2.4 or newer
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Open Software License version 3.0
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is
 * bundled with this package in the files license.txt / license.rst.  It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@ellislab.com so we can send you a copy immediately.
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2012, EllisLab, Inc. (http://ellislab.com/)
 * @license		http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link		http://codeigniter.com
 * @since		Version 2.1.0
 * @filesource
 */

/**
 * PDO PostgreSQL Forge Class
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_pdo_pgsql_forge extends CI_DB_pdo_forge {

	/**
	 * Create Table
	 *
	 * @param	string	the table name
	 * @param	bool	should 'IF NOT EXISTS' be added to the SQL
	 * @return	mixed
	 */
	protected function _create_table($table, $if_not_exists)
	{
		$sql = 'CREATE TABLE ';

		if ($if_not_exists === TRUE)
		{
			// Supported as of PostgreSQL 9.1
			if (version_compare($this->db->version(), '9.0', '>'))
			{
				$sql .= 'IF NOT EXISTS ';
			}
			elseif ($this->db->table_exists($table))
			{
				return TRUE;
			}
		}

		return $sql
			.$this->db->escape_identifiers($table).' ('
			.$this->_process_fields()
			.$this->_process_primary_keys()
			."\n);";
	}

	// --------------------------------------------------------------------

	/**
	 * Alter table query
	 *
	 * Generates a platform-specific query so that a table can be altered
	 * Called by add_column(), drop_column(), and column_alter(),
	 *
	 * @param	string	the ALTER type (ADD, DROP, CHANGE)
	 * @param	string	the column name
	 * @param	string	the table name
	 * @param	string	the column definition
	 * @param	string	the default value
	 * @param	bool	should 'NOT NULL' be added
	 * @param	string	the field after which we should add the new field
	 * @return	string
	 */
	protected function _alter_table($alter_type, $table, $fields, $after_field = '')
 	{
 		$sql = 'ALTER TABLE '.$this->db->escape_identifiers($table).' '.$alter_type.' ';

 		// DROP has everything it needs now.
 		if ($alter_type === 'DROP')
 		{
 			return $sql.$this->db->escape_identifiers($fields);
 		}

 		return $sql.$this->_process_fields()
			.($after_field !== '' ? ' AFTER '.$this->db->escape_identifiers($after_field) : '');
 	}

	// --------------------------------------------------------------------

	/**
	 * Process fields
	 *
	 * @return	string
	 */
	protected function _process_fields()
	{
		foreach ($this->fields as $field => $attributes)
		{
			$attrs = array_change_key_case($attributes, CASE_UPPER);

			if (empty($attributes['TYPE']))
			{
				unset($this->fields[$field]);
				continue;
			}

			$this->fields[$field] = empty($attributes['NAME'])
						? "\n\t".$this->db->escape_identifiers($field)
						: "\n\t".$this->db->escape_identifiers($attributes['NAME']);

			if (empty($attributes['UNSIGNED']) OR $attributes['UNSIGNED'] !== TRUE)
			{
				$attributes['UNSIGNED'] = FALSE;
			}

			switch (strtoupper($attributes['TYPE']))
			{
				case 'MEDIUMINT':
					$attributes['TYPE'] = 'INTEGER';
					$attributes['UNSIGNED'] = FALSE;
				case 'TINYINT':
					if ($attributes['UNSIGNED']) === TRUE)
					{
						$attributes['TYPE'] = 'SMALLINT';
						$attributes['UNSIGNED'] = FALSE;
					}
				case 'INT2':
				case 'SMALLINT':
					if ($attributes['UNSIGNED']) === TRUE)
					{
						$attributes['TYPE'] = 'INTEGER';
						$attributes['UNSIGNED'] = FALSE;
					}
				case 'INT':
				case 'INT4':
				case 'INTEGER':
					if ($attributes['UNSIGNED']) === TRUE)
					{
						$attributes['TYPE'] = 'BIGINT';
						$attributes['UNSIGNED'] = FALSE;
					}

					// AUTO_INCREMENT in PostgreSQL is implemented via the SERIAL data type
					if ( ! empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === TRUE)
					{
						$attributes['TYPE'] = 'SERIAL';
						$attributes['AUTO_INCREMENT'] = FALSE;
					}
				case 'INT8':
				case 'BIGINT':
					if ($attributes['UNSIGNED'] === TRUE)
					{
						$attributes['TYPE'] = 'NUMERIC';
						$attributes['UNSIGNED'] = FALSE;
					}

					// And there's BIGSERIAL as well
					if ( ! empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === TRUE)
					{
						$attributes['TYPE'] = 'BIGSERIAL';
						$attributes['AUTO_INCREMENT'] = FALSE;
					}
				case 'CHAR':
				case 'CHARACTER':
				case 'VARCHAR':
				case 'CHARACTER VARYING':
					empty($attributes['CONSTRAINT']) OR $attributes['CONSTRAINT'] = (int) $attributes['CONSTRAINT'];
					break;
				case 'DECIMAL':
				case 'NUMERIC':
					if ( ! empty($attributes['CONSTRAINT']) && is_array($attributes['CONSTRAINT']))
					{
						$attributes['CONSTRAINT'] = implode(',', $attributes['CONSTRAINT']);
					}
					break;
				case 'DOUBLE':
					$attributes['TYPE'] = 'DOUBLE PRECISION';
					break;
				case 'DATETIME':
					$attributes['TYPE'] = 'TIMESTAMP';
					break;
				case 'LONGTEXT':
					$attributes['TYPE'] = 'TEXT';
					break;
				case 'BLOB':
					$attributes['TYPE'] = 'BYTEA';
					break;
				case 'SET':
				case 'ENUM':
					$attributes['TYPE'] = 'ENUM';
					empty($attributes['CONSTRAINT']) OR $attributes['CONSTRAINT'] = implode(',', $this->db->escape($attributes['CONSTRAINT']));
					break;
				default:
					break;
			}

			$this->fields[$field] .= ' '.$attributes['TYPE'];
			empty($attributes['CONSTRAINT']) OR $this->fields[$field] .= '('.$attributes['CONSTRAINT'].')';

			if (isset($attributes['DEFAULT']))
			{
				$this->fields[$field] .= ' DEFAULT '.$this->db->escape($attributes['DEFAULT']);
			}

			$this->fields[$field] .= (empty($attributes['NULL']) && $attributes['NULL'] === TRUE)
						? ' NULL' : ' NOT NULL';

			if ( ! empty($attributes['UNIQUE']) && $attributes['UNIQUE'] === TRUE)
			{
				$this->fields[$field] .= ' UNIQUE';
			}
		}

		if (empty($this->fields))
		{
			return FALSE;
		}

		return implode(',', $this->fields);
	}

}

/* End of file pdo_pgsql_forge.php */
/* Location: ./system/database/drivers/pdo/subdrivers/pdo_pgsql_forge.php */