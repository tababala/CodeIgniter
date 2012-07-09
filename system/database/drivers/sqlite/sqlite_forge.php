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
 * @since		Version 1.0
 * @filesource
 */

/**
 * SQLite Forge Class
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_sqlite_forge extends CI_DB_forge {

	/**
	 * Create database
	 *
	 * @param	string	the database name
	 * @return	bool
	 */
	public function create_database($db_name = '')
	{
		// In SQLite, a database is created when you connect to the database.
		// We'll return TRUE so that an error isn't generated
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Drop database
	 *
	 * @param	string	the database name (ignored)
	 * @return	bool
	 */
	public function drop_database($db_name = '')
	{
		if ( ! @file_exists($this->db->database) OR ! @unlink($this->db->database))
		{
			return ($this->db->db_debug) ? $this->db->display_error('db_unable_to_drop') : FALSE;
		}
		elseif ( ! empty($this->db->data_cache['db_names']))
		{
			$key = array_search(strtolower($this->db->database), array_map('strtolower', $this->db->data_cache['db_names']), TRUE);
			if ($key !== FALSE)
			{
				unset($this->db->data_cache['db_names'][$key]);
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Create Table
	 *
	 * @param	string	the table name
	 * @param	bool	should 'IF NOT EXISTS' be added to the SQL
	 * @return	mixed
	 */
	protected function _create_table($table, $fields, $primary_keys, $keys, $if_not_exists)
	{
		$sql = 'CREATE TABLE ';

		// IF NOT EXISTS is not supported in SQLite 2
		if ($if_not_exists === TRUE && $this->db->table_exists($table))
		{
			return TRUE;
		}

		$sql .= $this->db->escape_identifiers($table).' (';
		$current_field_count = 0;

		foreach ($this->fields as $field => $attributes)
		{
			// Numeric field names aren't allowed in databases, so if the key is
			// numeric, we know it was assigned by PHP and the developer manually
			// entered the field information, so we'll simply add it to the list
			if (is_numeric($field))
			{
				$sql .= "\n\t".$attributes;
			}
			else
			{
				$attributes = array_change_key_case($attributes, CASE_UPPER);

				$sql .= "\n\t".$this->db->escape_identifiers($field).' '.$attributes['TYPE'];

				empty($attributes['CONSTRAINT']) OR $sql .= '('.$attributes['CONSTRAINT'].')';

				if ( ! empty($attributes['UNSIGNED']) && $attributes['UNSIGNED'] === TRUE)
				{
					$sql .= ' UNSIGNED';
				}

				if (isset($attributes['DEFAULT']))
				{
					$sql .= " DEFAULT '".$attributes['DEFAULT']."'";
				}


				$sql .= ( ! empty($attributes['NULL']) && $attributes['NULL'] === TRUE)
					? ' NULL' : ' NOT NULL';

				if ( ! empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === TRUE)
				{
					$sql .= ' AUTO_INCREMENT';
				}
			}

			// don't add a comma on the end of the last field
			if (++$current_field_count < count($this->fields))
			{
				$sql .= ',';
			}
		}

		return $sql
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
	protected function _alter_table($alter_type, $table, $column_name, $column_definition = '', $default_value = '', $null = '', $after_field = '')
	{
		/* SQLite only supports adding new columns and it does
		 * NOT support the AFTER statement. Each new column will
		 * be added as the last one in the table.
		 */
		if ($alter_type !== 'ADD COLUMN')
		{
			// Not supported
			return FALSE;
		}

		return 'ALTER TABLE '.$this->db->escape_identifiers($table).' '.$alter_type.' '.$this->db->escape_identifiers($column_name)
			.' '.$column_definition
			.($default_value != '' ? " DEFAULT '".$default_value."'" : '')
			// If NOT NULL is specified, the field must have a DEFAULT value other than NULL
			.(($null !== NULL && $default_value !== 'NULL') ? ' NOT NULL' : ' NULL');
	}

}

/* End of file sqlite_forge.php */
/* Location: ./system/database/drivers/sqlite/sqlite_forge.php */