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
 * @since		Version 2.0.3
 * @filesource
 */

/**
 * SQLSRV Forge Class
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_sqlsrv_forge extends CI_DB_forge {

	/**
	 * Create Table
	 *
	 * @param	string	the table name
	 * @param	bool	should 'IF NOT EXISTS' be added to the SQL
	 * @return	string
	 */
	protected function _create_table($table, $if_not_exists)
	{
		$sql = ($if_not_exists === TRUE)
			? "IF NOT EXISTS (SELECT * FROM sysobjects WHERE ID = object_id(N'".$table."') AND OBJECTPROPERTY(id, N'IsUserTable') = 1)\n"
			: '';

		return $sql
			.'CREATE TABLE '.$this->db->escape_identifiers($table).' ('
			.$this->_process_fields()
			.$this->_process_primary_keys()
			."\n);";
	}

	// --------------------------------------------------------------------

	/**
	 * Drop Table
	 *
	 * Generates a platform-specific DROP TABLE string
	 *
	 * @param	string	the table name
	 * @param	bool
	 * @return	string
	 */
	protected function _drop_table($table, $if_exists)
	{
		$sql = 'DROP TABLE '.$this->db->escape_identifiers($table);

		return ($if_exists)
			? "IF EXISTS (SELECT * FROM sysobjects WHERE ID = object_id(N'".$table."') AND OBJECTPROPERTY(id, N'IsUserTable') = 1) ".$sql;
			: $sql;
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
	protected function _alter_table($alter_type, $table, $column_name, $column_definition = '', $default_value = '', $null = '', $after_field = '')
	{
		$sql = 'ALTER TABLE '.$this->db->escape_identifiers($table).' '.$alter_type.' '.$this->db->escape_identifiers($column_name);

		// DROP has everything it needs now.
		if ($alter_type === 'DROP')
		{
			return $sql;
		}

		return $sql.' '.$column_definition
			.($default_value != '' ? ' DEFAULT "'.$default_value.'"' : '')
			.($null === NULL ? ' NULL' : ' NOT NULL')
			.($after_field != '' ? ' AFTER '.$this->db->escape_identifiers($after_field) : '');
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

			switch (strtoupper($attributes['TYPE']))
			{
				case 'CHAR':
				case 'NCHAR':
				case 'VARCHAR':
				case 'NVARCHAR':
				case 'BINARY':
				case 'VARBINARY':
				case 'FLOAT':
					empty($attributes['CONSTRAINT']) OR $attributes['CONSTRAINT'] = (int) $attributes['CONSTRAINT'];
					if ( ! empty($attributes['UNSIGNED']) && $attributes['UNSIGNED'] === TRUE)
					{
						$attributes['TYPE'] = 'REAL';
						$attributes['UNSIGNED'] = FALSE;
					}
					break;
				case 'MEDIUMINT':
				case 'INTEGER':
					$attributes['TYPE'] = 'INT';
					$attributes['UNSIGNED'] = FALSE;
				case 'TINYINT':
					if ( ! empty($attributes['UNSIGNED']) && $attributes['UNSIGNED'] === TRUE)
					{
						$attributes['TYPE'] = 'SMALLINT';
						$attributes['UNSIGNED'] = FALSE;
					}
				case 'SMALLINT':
					if ( ! empty($attributes['UNSIGNED']) && $attributes['UNSIGNED'] === TRUE)
					{
						$attributes['TYPE'] = 'INT';
						$attributes['UNSIGNED'] = FALSE;
					}
				case 'INT':
					if ( ! empty($attributes['UNSIGNED']) && $attributes['UNSIGNED'] === TRUE)
					{
						$attributes['TYPE'] = 'BIGINT';
						$attributes['UNSIGNED'] = FALSE;
					}
				case 'BIGINT':
				case 'REAL':
				case 'BIT':
				case 'TEXT':
				case 'NTEXT':
					empty($attributes['CONSTRAINT']) OR $attributes['CONSTRAINT'] = FALSE;
					break;
				case 'DECIMAL':
				case 'NUMERIC':
					empty($attributes['CONSTRAINT']) OR $this->fields[$field] .= implode(',', $attributes['CONSTRAINT']);
					break;
				default:
					break;
			}

			$this->fields[$field] .= ' '.$attributes['TYPE'];

			if ( ! empty($attributes['CONSTRAINT']))
			{
				$this->fields[$field] .= '('.$attributes['CONSTRAINT'].')';
			}

			if (array_key_exists('DEFAULT', $attributes))
			{
				if ($attributes['DEFAULT'] === NULL)
				{
					// Override the NULL attribute if that's our default
					$attributes['NULL'] = TRUE;
					$attributes['DEFAULT'] = 'NULL';
				}
				else
				{
					$attributes['DEFAULT'] = $this->db->escape($attributes['DEFAULT']);
				}
			}

			$this->fields[$field] .= (empty($attributes['NULL']) && $attributes['NULL'] === TRUE)
						? ' NULL' : ' NOT NULL';

			if (isset($attributes['DEFAULT']))
			{
				$this->fields[$field] .= ' DEFAULT '.$attributes['DEFAULT'];
			}

			if ( ! empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === TRUE && stripos($attributes['TYPE'], 'int') !== FALSE)
			{
				$this->fields[$field] .= ' IDENTITY(1,1)';
			}

			if ( ! empty($attributes['UNIQUE']) && $attributes['UNIQUE'] === TRUE)
			{
				$sql .= ' UNIQUE';
			}
		}

		if (empty($this->fields))
		{
			return FALSE;
		}

		return implode(',', $this->fields);
	}

}

/* End of file sqlsrv_forge.php */
/* Location: ./system/database/drivers/sqlsrv/sqlsrv_forge.php */