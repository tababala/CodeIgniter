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
 * PDO MySQL Forge Class
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_pdo_mysql_forge extends CI_DB_pdo_forge {

	protected $_create_database = 'CREATE DATABASE %s CHARACTER SET %s COLLATE %s';

	/**
	 * Create Table
	 *
	 * @param	string	the table name
	 * @param	bool	should 'IF NOT EXISTS' be added to the SQL
	 * @return	string
	 */
	protected function _create_table($table, $if_not_exists)
	{
		$sql = 'CREATE TABLE ';

		if ($if_not_exists === TRUE)
		{
			$sql .= 'IF NOT EXISTS ';
		}

		$sql .= $this->db->escape_identifiers($table).' ('
			.$this->_process_fields()
			.$this->_process_primary_keys()
			.$this->_process_indexes();

		return $sql."\n) DEFAULT CHARACTER SET ".$this->db->char_set.' COLLATE '.$this->db->dbcollat.';';
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
	 * @param	array	fields
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

			$this->fields[$field] .= ' '.$attributes['TYPE'];

			switch (strtolower($attributes['TYPE']))
			{
				case 'TINYINT':
				case 'SMALLINT':
				case 'MEDIUMINT':
				case 'INT':
				case 'INTEGER':
				case 'BIGINT':
				case 'BIT':
				case 'CHAR':
				case 'VARCHAR':
				case 'BINARY':
				case 'VARBINARY':
					empty($attributes['CONSTRAINT']) OR $this->fields[$field] .= '('.(int) $attributes['CONSTRAINT'].')';
					break;
				case 'REAL':
				case 'DOUBLE':
				case 'FLOAT':
				case 'DECIMAL':
				case 'NUMERIC':
					empty($attributes['CONSTRAINT']) OR $this->fields[$field] .= '('.implode(',', $attributes['CONSTRAINT']).')';
					break;
				case 'ENUM':
				case 'SET':
					empty($attributes['CONSTRAINT']) OR $this->fields[$field] .= '('.implode(',', $this->db->escape($attributes['CONSTRAINT'])).')';
					break;
				default:
					break;
			}

			if ( ! empty($attributes['UNSIGNED']) && $attributes['UNSIGNED'] === TRUE)
			{
				$this->fields[$field] .= ' UNSIGNED';
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
				$this->fields[$field] .= ' AUTO_INCREMENT';
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

	// --------------------------------------------------------------------

	/**
	 * Process indexes
	 *
	 * @param	string	(ignored)
	 * @return	string
	 */
	protected function _process_indexes($table = NULL)
	{
		$sql = '';

		for ($i = 0, $c = count($this->keys); $i < $c; $i++)
		{
			if ( ! isset($this->fields[$this->keys[$i]]))
			{
				unset($this->keys[$i]);
				continue;
			}

			is_array($this->keys[$i]) OR $this->keys[$i] = array($this->keys[$i]);

			$sql .= ",\n\tKEY ".$this->db->escape_identifiers(implode('_', $this->keys[$i]))
				.' ('.implode(', ', $this->db->escape_identifiers($this->keys[$i])).')';
		}

		$this->keys = array();

		return $sql;
	}

}

/* End of file pdo_mysql_forge.php */
/* Location: ./system/database/drivers/pdo/subdrivers/pdo_mysql_forge.php */