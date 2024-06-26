<?php
/**
 * This source file is part of the open source project
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2023, Packet Tide, LLC (https://www.packettide.com)
 * @license   https://expressionengine.com/license Licensed under Apache License, Version 2.0
 */

namespace ExpressionEngine\Service\Database\Backup;

use Exception;
use ExpressionEngine\Library\Filesystem\Filesystem;

/**
 * Database Backup class
 */
class Backup
{
    /**
     * @var Filesystem object
     */
    protected $filesystem;

    /**
     * @var Backup\Query Database Query object
     */
    protected $query;

    /**
     * @var String Full path to write backup to
     */
    protected $file_path;

    /**
     * @var boolean When TRUE, writes a file that has one query per line with no
     * linebreaks in those queries for easy line-by-line consumption by a
     * restore script
     */
    protected $compact_file = false;

    /**
     * @var int Maximum number of rows to work with/process at a given operation,
     * e.g. this is the max number of rows we will ask to be queried at once,
     * and this is roughly how many rows will be written to a file before we
     * decide to advise the caller to start a new request, should they be backing
     * up via a web interface
     */
    protected $row_limit = 4000;

    /**
     * @var int Number of rows exported in the current session for when we need
     * to export a database conservatively
     */
    protected $rows_exported = 0;

    /**
     * @var array Tables to backup
     */
    protected $tables_to_backup = [];

    /**
     * Constructor
     *
     * @param	Backup\Query     $query     Query object for generating query strings
     * @param	Filesystem       $filesytem Filesystem object for writing to files
     * @param	string           $file_path Path to write SQL file to
     * @param	int              $row_limit Override $row_limit class property
     */
    public function __construct(Filesystem $filesystem, Query $query, $file_path, $row_limit)
    {
        $this->filesystem = $filesystem;
        $this->query = $query;
        $this->file_path = $file_path;

        if ($row_limit) {
            $this->row_limit = $row_limit;
        }
    }

    /**
     * Set max row limit; this is mainly for unit testing purposes as ideally
     * this property will already be set to a reasonable default
     *
     * @param	int	$limit	Max number of rows to deal with at once
     */
    public function setRowLimit($limit)
    {
        $this->row_limit = $limit;
    }

    /**
     * Sets an array of tables to backup, if not backing up all tables in the database
     *
     * @param	array	$tables	Array of table names
     */
    public function setTablesToBackup($tables)
    {
        $this->tables_to_backup = $tables;
    }

    /**
     * Gets an array of tables to backup
     *
     * @return	array	Array of table names
     */
    protected function getTables()
    {
        if (empty($this->tables_to_backup)) {
            return array_keys($this->query->getTables());
        }

        //make sure we only try to backup existing tables
        $this->tables_to_backup = array_filter($this->tables_to_backup, function ($table) {
            return in_array($table, array_keys($this->query->getTables()));
        });

        return $this->tables_to_backup;
    }

    /**
     * Gets an array of tables to backup (with some information data)
     *
     * @see Backup::getTables()
     * @return array Array of tables data
     */
    protected function getTablesInformation()
    {
        $tablesInformation = $this->query->getTables();
        if (empty($this->tables_to_backup)) {
            return $tablesInformation;
        }
        $tablesNames = array_keys($tablesInformation);

        //make sure we only try to backup existing tables
        $this->tables_to_backup = array_intersect($this->tables_to_backup, $tablesNames);
        $tablesInformation = array_intersect_key($tablesInformation, array_flip($this->tables_to_backup));

        return $tablesInformation;
    }

    /**
     * Class will write a file with comments and helpful whitespace formatting
     */
    public function makePrettyFile()
    {
        $this->compact_file = false;
        $this->query->makePrettyQueries();
    }

    /**
     * Class will write a file that has one query per line with no linebreaks in
     * those queries for easy line-by-line consumption by a restore script
     */
    public function makeCompactFile()
    {
        $this->compact_file = true;
        $this->query->makeCompactQueries();
    }

    /**
     * Runs the entire database backup routine
     */
    public function run()
    {
        $this->startFile();
        $this->writeDropAndCreateStatements();
        $this->writeAllTableInserts();
    }

    /**
     * Creates/truncates any existing backup file at the specified path and
     * inserts a header
     */
    public function startFile()
    {
        // Make sure we have enough space first
        $freeSpace = $this->filesystem->getFreeDiskSpace(dirname($this->file_path));
        if ($freeSpace === false) {
            throw new \Exception("Could not determine free disk space", 1);
        }
        $db_size = $this->getDatabaseSize();
        if ($db_size > $freeSpace) {
            $db_size = round($db_size / 1048576, 1);

            throw new \Exception("There is not enough free disk space to write your backup. {$db_size}MB needed.", 1);
        }

        // Truncate file
        $this->filesystem->write($this->file_path, '', true);
        $this->writeSeparator('Database backup generated by ExpressionEngine');

        $char_set = $this->query->getCharset();
        $this->writeChunk("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';");
        $this->writeChunk("SET @OLD_TIME_ZONE=@@TIME_ZONE;");
        $this->writeChunk("SET TIME_ZONE='+00:00';");
        $this->writeChunk("SET NAMES {$char_set};");
        $this->writeChunk("SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;");
        $this->writeChunk("SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;");
        $this->writeChunk("SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0;");
    }

    /**
     * Adds a comment to verify the file is complete, and resets temporary MySQL setting overrides
     * @return void
     */
    public function endFile()
    {
        $this->writeChunk("SET SQL_MODE=@OLD_SQL_MODE;");
        $this->writeChunk("SET TIME_ZONE=@OLD_TIME_ZONE;");
        $this->writeChunk("SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;");
        $this->writeChunk("SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;");
        $this->writeChunk("SET SQL_NOTES=@OLD_SQL_NOTES;");

        $this->writeSeparator('Database backup completed by ExpressionEngine on ' . date('Y-m-d h:i:sT'));
    }

    /**
     * Adds up the size of all available tables and returns the result
     *
     * @return	int	Size of database in bytes
     */
    protected function getDatabaseSize()
    {
        $tables = $this->query->getTables();

        $total_size = 0;
        foreach ($tables as $table => $specs) {
            $total_size += $specs['size'];
        }

        return $total_size;
    }

    /**
     * Writes the DROP IF EXISTS and CREATE TABLE statements for each table
     */
    public function writeDropAndCreateStatements()
    {
        $tables = $this->getTablesInformation();

        $this->writeSeparator('Drop old tables if exists');

        foreach ($tables as $tableName => $table) {
            $this->writeChunk($this->query->getDropStatement($tableName));
        }

        $this->writeSeparator('Create tables and their structure');

        foreach ($tables as $name => $structure) {
            switch ($structure['type']) {
                case Query::TABLE_STRUCTURE:
                    $create = $this->query->getCreateForTable($name);
                    break;
                case Query::VIEW_STRUCTURE:
                    $create = $this->query->getCreateForView($name);
                    break;
                default:
                    throw new Exception("There is no implementation of 'get create' for type {$structure['type']}. Name: $name");
                /** @see Query::getTables() */
            }

            // Add an extra linebreak if not a compact file
            if (! $this->compact_file) {
                $create .= "\n";
            }

            $this->writeChunk($create);
        }
    }

    /**
     * Writes ALL table INSERTs
     */
    public function writeAllTableInserts()
    {
        $this->writeSeparator('Populate tables with their data');

        foreach ($this->getTables() as $table) {
            $returned = $this->writeInsertsForTableWithOffset($table);

            if ($returned !== null && $returned['next_offset'] > 0) {
                $returned = $this->writeInsertsForTableWithOffset($table, $returned['next_offset']);
            }
        }
    }

    /**
     * Writes partial INSERTs for a given table, with the idea being a backup
     * can be split up across multiple requests for large databases
     *
     * @param	string	$table_name	Name of table to start the backup from
     * @param	int		$offset		Offset to start the backup from
     * @return	mixed	FALSE if no more work to do, otherwise an array telling
     * the caller which table and offset they need to start at next time, e.g.:
     *	[
     *		'table_name' => 'exp_some_table'
     *		'offset'     => 5000
     *	]
     */
    public function writeTableInsertsConservatively($table_name = null, $offset = 0)
    {
        $tables = $this->getTables();

        // Table specified? Chop off the beginning of the tables array until we
        // we get to the specified table and start the loop from there
        if (! empty($table_name)) {
            $tables = array_slice($tables, array_search($table_name, $tables));
        }

        $this->rows_exported = 0;
        foreach ($tables as $table) {
            // Keep under our row limit
            $limit = $this->row_limit - $this->rows_exported;

            $returned = $this->writeInsertsForTableWithOffset($table, $offset, $limit);

            // No more rows to export in this table
            if ($returned === null) {
                $offset = 0;

                continue;
            }

            $this->rows_exported += $returned['rows_exported'];
            $offset = $returned['next_offset'];

            // Have we exported what we consider to be the most number of rows
            // we should reasonably export in one request?
            if ($this->rows_exported >= $this->row_limit) {
                // Previous table is finished, start a fresh request with the
                // next table
                if ($offset == 0) {
                    // Find the next table in the array
                    $next_table = array_slice($tables, array_search($table, $tables) + 1, 1);

                    if (! isset($next_table[0])) {
                        return false;
                    }

                    return [
                        'table_name' => $next_table[0],
                        'offset' => 0
                    ];
                }
                // There is more of this table to export that we weren't able to,
                // let the caller know
                else {
                    return [
                        'table_name' => $table,
                        'offset' => $offset
                    ];
                }
            }

            $offset = 0;
        }

        return false;
    }

    /**
     * Writes partial INSERTs for a given table, with the idea being a backup
     * can be split up across multiple requests for large databases
     *
     * @param	string	$table_name	Table name
     * @param	int		$offset		Offset to start the backup from
     * @return	array	Array of information to tell the caller the offset the
     * table should be queried from next, and also the number of rows that were
     * exported during the call, e.g.:
     *	[
     *		'next_offset' => 0,
     *		'rows_exported' => 50
     *	]
     * Returns NULL if no rows can be exported given the offset/limit criteria
     */
    public function writeInsertsForTableWithOffset($table_name, $offset = 0, $limit = 0)
    {
        // At least apply the row limit to prevent selecting a million-row table
        // all at once
        $limit = ($limit !== 0) ? $limit : $this->row_limit;

        $inserts = $this->query->getInsertsForTable($table_name, $offset, $limit);

        // No more rows to export in this table
        if (empty($inserts)) {
            return null;
        }

        $this->writeChunk($inserts['insert_string']);

        // Add another line break if not compact
        if (! $this->compact_file) {
            $this->writeChunk('');
        }

        return [
            'next_offset' => $offset + $limit,
            'rows_exported' => $inserts['rows_exported']
        ];
    }

    /**
     * Writes a chunk of text to the file followed by a newline
     *
     * @param	string	$chunk	Chunk to write to the file. Sloth love Chunk.
     */
    protected function writeChunk($chunk)
    {
        $this->filesystem->write($this->file_path, $chunk . "\n", false, true);
    }

    /**
     * Writes a pretty(ish) separator to the file with a given string of text,
     * usually to mark a new section in the file
     *
     * @param	string	$text	Text to include in the separater
     */
    protected function writeSeparator($text)
    {
        if ($this->compact_file) {
            return;
        }

        $separator = <<<EOT

--
-- $text
--

EOT;
        $this->writeChunk($separator);
    }
}

// EOF
