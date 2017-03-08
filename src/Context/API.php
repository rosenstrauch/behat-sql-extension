<?php

namespace Genesis\SQLExtension\Context;

use Behat\Behat\Context\Step\Given;
use Behat\Gherkin\Node\TableNode;
use Exception;

/*
 * This file is part of the Behat\SQLExtension
 *
 * (c) Abdul Wahab Qureshi <its.inevitable@hotmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * SQL Context.
 *
 * @author Abdul Wahab Qureshi <its.inevitable@hotmail.com>
 */
class API extends SQLHandler implements Interfaces\APIInterface
{
    /**
     * {@inheritDoc}
     */
    public function insert($table, array $values)
    {
        $this->debugLog('------- I HAVE WHERE -------');
        $this->debugLog('Trying to select existing record.');

        // Normalize data.
        $this->setEntity($table);

        // $this->debugLog('No record found, trying to insert.');

        $query = $this->convertToQuery($values);
        $values = $this->resolveQuery($query);

        // If the record does not already exist, create it.
        list($columnNames, $columnValues) = $this->getTableColumns($this->getEntity(), $values);

        // Build up the sql.
        $this->setCommandType('insert');
        $sql = "INSERT INTO {$this->getEntity()} ({$columnNames}) VALUES ({$columnValues})";
        $statement = $this->execute($sql);

        // Throw exception if no rows were effected.
        $this->throwErrorIfNoRowsAffected($statement, Interfaces\SQLHandlerInterface::IGNORE_DUPLICATE);
        $this->setKeywordsFromId($this->getLastId());

        $this->get('dbManager')->closeStatement($statement);

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($table, array $columns)
    {
        $this->debugLog('------- I DONT HAVE WHERE -------');

        if (! $columns) {
            throw new Exception('You must provide a where clause!');
        }

        $this->setEntity($table);
        $this->setCommandType('delete');

        $query = $this->convertToQuery($columns);
        $columns = $this->resolveQuery($query);

        $searchConditionOperator = $this->get('sqlBuilder')->getSearchConditionOperatorForColumns($query);
        $whereClause = $this->constructSQLClause($this->getCommandType(), $searchConditionOperator, $columns);

        // Construct the delete statement.
        $sql = "DELETE FROM {$this->getEntity()} WHERE {$whereClause}";

        // Execute statement.
        $statement = $this->execute($sql);

        // Throw an exception if errors are found.
        $this->throwExceptionIfErrors($statement);
        $this->get('dbManager')->closeStatement($statement);

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function update($table, array $with, array $columns)
    {
        $this->debugLog('------- I HAVE AN EXISTING WITH WHERE -------');

        if (! $columns) {
            throw new Exception('You must provide a where clause!');
        }

        $this->setEntity($table);
        $this->setCommandType('update');

        // Build up the update clause.
        $query = $this->convertToQuery($with);
        $with = $this->resolveQuery($query);
        $updateClause = $this->constructSQLClause($this->getCommandType(), ', ', $with);

        $query = $this->convertToQuery($columns);
        $columns = $this->resolveQuery($query);

        $searchConditionOperator = $this->get('sqlBuilder')->getSearchConditionOperatorForColumns($query);
        $whereClause = $this->constructSQLClause($this->getCommandType(), $searchConditionOperator, $columns);

        // Build up the update statement.
        $sql = "UPDATE {$this->getEntity()} SET {$updateClause} WHERE {$whereClause}";

        // Execute statement.
        $statement = $this->execute($sql);

        // If no exception is throw, save the last id.
        $this->setKeywordsFromCriteria(
            $this->getEntity(),
            $whereClause
        );

        $this->get('dbManager')->closeStatement($statement);

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function select($table, array $columns)
    {
        $this->debugLog('------- I HAVE AN EXISTING WHERE -------');

        $this->setEntity($table);
        $this->setCommandType('select');

        $query = $this->convertToQuery($columns);
        $columns = $this->resolveQuery($query);

        $searchConditionOperator = $this->get('sqlBuilder')->getSearchConditionOperatorForColumns($query);
        $selectWhereClause = $this->constructSQLClause($this->getCommandType(), $searchConditionOperator, $columns);

        // Execute sql for setting last id.
        return $this->setKeywordsFromCriteria(
            $this->getEntity(),
            $selectWhereClause
        );
    }

    /**
     * {@inheritDoc}
     */
    public function assertExists($table, array $with)
    {
        $this->debugLog('------- I SHOULD HAVE A WITH -------');
        $this->setEntity($table);
        $this->setCommandType('select');

        $query = $this->convertToQuery($with);
        $selectWhereClause = $this->resolveQueryToSQLClause($this->getCommandType(), $query);

        // Create the sql to be inserted.
        $sql = "SELECT * FROM {$this->getEntity()} WHERE {$selectWhereClause}";

        // Execute the sql query, if the query throws a generic not found error,
        // catch it and give it some context.
        $statement = $this->execute($sql);
        if (! $this->hasFetchedRows($statement)) {
            throw new Exceptions\RecordNotFoundException(
                $selectWhereClause,
                $this->getEntity()
            );
        }

        $this->get('dbManager')->closeStatement($statement);

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function assertNotExists($table, array $with)
    {
        $this->debugLog('------- I SHOULD NOT HAVE A WHERE -------');

        $this->setEntity($table);
        $this->setCommandType('select');

        $query = $this->convertToQuery($with);
        $selectWhereClause = $this->resolveQueryToSQLClause($this->getCommandType(), $query);

        // Create the sql to be inserted.
        $sql = "SELECT * FROM {$this->getEntity()} WHERE {$selectWhereClause}";

        // Execute the sql query, if the query throws a generic not found error,
        // catch it and give it some context.
        $statement = $this->execute($sql);

        if ($this->hasFetchedRows($statement)) {
            throw new Exceptions\RecordFoundException(
                $selectWhereClause,
                $this->getEntity()
            );
        }

        $this->get('dbManager')->closeStatement($statement);

        return $sql;
    }
}
