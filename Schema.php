<?php
/**
 * @Author Apa Oww
 */

namespace apaoww\oci8;

use yii\base\InvalidCallException;
use yii\db\Connection;
use yii\db\TableSchema;
use yii;

/**
 * Schema is the class for retrieving metadata from an Oracle database
 *
 * @property string $lastInsertID The row ID of the last row inserted, or the last value retrieved from the
 * sequence object. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Schema extends \yii\db\Schema
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->defaultSchema === null) {
            $this->defaultSchema = strtoupper($this->db->username);
        }
    }

    /**
     * @inheritdoc
     */
    public function releaseSavepoint($name)
    {
        // does nothing as Oracle does not support this
    }

    /**
     * @inheritdoc
     */
    public function quoteSimpleTableName($name)
    {
        return strpos($name, '"') !== false ? $name : '"' . $name . '"';
    }

    /**
     * @inheritdoc
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this->db);
    }

    /**
     * @inheritdoc
     */
    public function loadTableSchema($name)
    {
        $table = new TableSchema();
        $this->resolveTableNames($table, $name);

        if ($this->findColumns($table)) {
            $this->findConstraints($table);

            return $table;
        } else {
            return null;
        }
    }

    /**
     * Resolves the table name and schema name (if any).
     *
     * @param TableSchema $table the table metadata object
     * @param string $name the table name
     */
    protected function resolveTableNames($table, $name)
    {
        $parts = explode('.', str_replace('"', '', $name));
        if (isset($parts[1])) {
            $table->schemaName = $parts[0];
            $table->name = $parts[1];
        } else {
            $table->schemaName = $this->defaultSchema;
            $table->name = $name;
        }

        $table->fullName = $table->schemaName !== $this->defaultSchema ? $table->schemaName . '.' . $table->name : $table->name;
    }

    /**
     * Collects the table column metadata.
     * @param TableSchema $table the table schema
     * @return boolean whether the table exists
     */
    protected function findColumns($table)
    {
        $schemaName = $table->schemaName;
        $tableName = $table->name;


        /*Search contraint only on user_constraint*/
        $sql = <<<EOD
SELECT
    tc.column_name,
    tc.data_type,
    tc.data_precision,
    tc.data_scale,
    tc.data_length,
    tc.nullable,
    tc.data_default,
    /* to identified pk using this sub query quite slow, the only solution is
    to put PK on column comment as PK will use php strpos to identified PK
    (
        SELECT
            d.constraint_type
        FROM
            user_cons_columns c,
            user_constraints d
        WHERE
            c.owner = :schemaName
            AND d.owner = c.owner
            AND   d.constraint_name = c.constraint_name
            AND   c.table_name =:tableName
            AND   c.column_name = tc.column_name
            AND   d.constraint_type = 'P'
    ) AS key,*/
    cc.comments column_comment 
FROM
    user_col_comments cc
    JOIN user_tab_columns tc ON cc.column_name = tc.column_name
                                AND cc.table_name = tc.table_name
WHERE
    cc.table_name = upper(:tableName)
EOD;


        try {
            $columns = $this->db->createCommand($sql, [
                ':tableName' => $table->name,
               // ':schemaName' => $table->schemaName,
            ])->queryAll();
        } catch (\Exception $e) {
            return false;
        }

        if (empty($columns)) {
            return false;
        }

        foreach ($columns as $column) {
            if ($this->db->slavePdo->getAttribute(\PDO::ATTR_CASE) === \PDO::CASE_LOWER) {
                $column = array_change_key_case($column, CASE_UPPER);
            }
            $c = $this->createColumn($column);
            $table->columns[$c->name] = $c;
            if ($c->isPrimaryKey) {
                $table->primaryKey[] = $c->name;
                $table->sequenceName = $this->getTableSequenceName($table->name);
                if (!empty($table->sequenceName))
                    $c->autoIncrement = true;
            }
        }
        return true;
    }

    /**
     * Sequence name of table
     *
     * @param $tablename
     * @internal param \yii\db\TableSchema $table ->name the table schema
     * @return string whether the sequence exists
     */
    protected function getTableSequenceName($tablename)
    {

        $seq_name_sql = "select ud.referenced_name as sequence_name
                        from   user_dependencies ud
                               join user_triggers ut on (ut.trigger_name = ud.name)
                        where ut.table_name='{$tablename}'
                              and ud.type='TRIGGER'
                              and ud.referenced_type='SEQUENCE'";
        return $this->db->createCommand($seq_name_sql)->queryScalar();
    }

    /**
     * @Overrides method in class 'Schema'
     * @see http://www.php.net/manual/en/function.PDO-lastInsertId.php -> Oracle does not support this
     *
     * Returns the ID of the last inserted row or sequence value.
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @throws InvalidCallException if the DB connection is not active
     */
    public function getLastInsertID($sequenceName = '')
    {
        if ($this->db->isActive) {
            // get the last insert id from the master connection
            if ($sequenceName != null) {
                return $this->db->useMaster(function (Connection $db) use ($sequenceName) {
                    return $db->createCommand("SELECT {$sequenceName}.CURRVAL FROM DUAL")->queryScalar();
                });
            } else {
                return '';
            }

        } else {
            throw new InvalidCallException('DB Connection is not active.');
        }
    }

    /**
     * Creates ColumnSchema instance
     *
     * @param array $column
     * @return ColumnSchema
     */
    protected function createColumn($column)
    {
        $c = $this->createColumnSchema();
        $c->name = $column['COLUMN_NAME'];
        $c->allowNull = $column['NULLABLE'] === 'Y';
        $c->isPrimaryKey = strpos($column['COLUMN_COMMENT'], 'PK') !== false;
        $c->comment = $column['COLUMN_COMMENT'] === null ? '' : $column['COLUMN_COMMENT'];

        $this->extractColumnType($c, $column['DATA_TYPE']);
        $this->extractColumnSize($c, $column['DATA_TYPE']);

        $c->phpType = $this->getColumnPhpType($c);

        if (!$c->isPrimaryKey) {
            if (stripos($column['DATA_DEFAULT'], 'timestamp') !== false) {
                $c->defaultValue = null;
            } else {
                $c->defaultValue = $c->phpTypecast($column['DATA_DEFAULT']);
            }
        }

        return $c;
    }

    /**
     * Finds constraints and fills them into TableSchema object passed
     * @param TableSchema $table
     */
    protected function findConstraints($table)
    {
        /*        $sql = <<<EOD
                SELECT D.constraint_type as CONSTRAINT_TYPE, C.COLUMN_NAME, C.position, D.r_constraint_name,
                        E.table_name as table_ref, f.column_name as column_ref,
                        C.table_name
                FROM ALL_CONS_COLUMNS C
                inner join ALL_constraints D on D.OWNER = C.OWNER and D.constraint_name = C.constraint_name
                left join ALL_constraints E on E.OWNER = D.r_OWNER and E.constraint_name = D.r_constraint_name
                left join ALL_cons_columns F on F.OWNER = E.OWNER and F.constraint_name = E.constraint_name and F.position = c.position
                WHERE C.OWNER = :schemaName
                   and C.table_name = :tableName
                   and D.constraint_type <> 'P'
                order by d.constraint_name, c.position
        EOD;*/

        /*Using sub-query on select happend to be faster than using left join*/
        $sql = <<<EOD
SELECT 
    c.constraint_name,
    c.column_name,
    d.r_constraint_name ,
    (select table_name from all_constraints e where e.constraint_name = d.r_constraint_name) referenced_table_name
    , (select column_name from all_cons_columns f where f.constraint_name = d.r_constraint_name  AND f.column_name = c.column_name) referenced_column_name
FROM
    all_cons_columns c
    INNER JOIN user_constraints d ON d.owner = c.owner
                                    AND d.constraint_name = c.constraint_name
WHERE
    c.owner = :schemaName
    AND   c.table_name = :tableName
    AND   d.constraint_type = 'R'
    AND   d.owner = :schemaName
EOD;
        try {
            $rows = $this->db->createCommand($sql, [':tableName' => $table->name, ':schemaName' => $table->schemaName])->queryAll();
            $constraints = [];
            foreach ($rows as $row) {

                $constraints[$row['CONSTRAINT_NAME']]['referenced_table_name'] = $row['REFERENCED_TABLE_NAME'];
                $constraints[$row['CONSTRAINT_NAME']]['columns'][$row['COLUMN_NAME']] = $row['REFERENCED_COLUMN_NAME'];
            }
            $table->foreignKeys = [];
            foreach ($constraints as $constraint) {
                $table->foreignKeys[] = array_merge(
                    [$constraint['referenced_table_name']],
                    $constraint['columns']
                );
            }
        } catch (\Exception $e) {
            $previous = $e->getPrevious();
            if (!$previous instanceof \PDOException || strpos($previous->getMessage(), 'SQLSTATE[42S02') === false) {
                throw $e;
            }

            // table does not exist, try to determine the foreign keys using the table creation sql
            $sql = $this->getCreateTableSql($table);
            $regexp = '/FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
            if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fks = array_map('trim', explode(',', str_replace('`', '', $match[1])));
                    $pks = array_map('trim', explode(',', str_replace('`', '', $match[3])));
                    $constraint = [str_replace('`', '', $match[2])];
                    foreach ($fks as $k => $name) {
                        $constraint[$name] = $pks[$k];
                    }
                    $table->foreignKeys[md5(serialize($constraint))] = $constraint;
                }
                $table->foreignKeys = array_values($table->foreignKeys);
            }
        }

    }
/**
     * Gets the CREATE TABLE sql string.
     * @param TableSchema $table the table metadata
     * @return string $sql the result of 'SHOW CREATE TABLE'
     */
    protected function getCreateTableSql($table)
    {
        $row = $this->db->createCommand("SELECT dbms_metadata.get_ddl('TABLE', :tableName) CREATE_TABLE FROM dual",  [ ":tableName" => $table->name ])->queryOne();
        if (isset($row['CREATE_TABLE'])) {
            $sql = $row['CREATE_TABLE'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }

        return $sql;
    }
    /**
     * @inheritdoc
     */
    protected function findTableNames($schema = '')
    {
        if ($schema === '') {
            $sql = <<<EOD
SELECT table_name, '{$schema}' as table_schema FROM user_tables
EOD;
            $command = $this->db->createCommand($sql);
        } else {
            $sql = <<<EOD
SELECT object_name as table_name, owner as table_schema FROM all_objects
WHERE object_type = 'TABLE' AND owner=:schema
EOD;
            $command = $this->db->createCommand($sql);
            $command->bindParam(':schema', $schema);
        }

        $rows = $command->queryAll();
        $names = [];
        foreach ($rows as $row) {
            $names[] = $row['TABLE_NAME'];
        }

        return $names;
    }

    /**
     * Extracts the data types for the given column
     * @param ColumnSchema $column
     * @param string $dbType DB type
     */
    protected function extractColumnType($column, $dbType)
    {
        $column->dbType = $dbType;

        if (strpos($dbType, 'FLOAT') !== false) {
            $column->type = 'double';
        } elseif (strpos($dbType, 'NUMBER') !== false || strpos($dbType, 'INTEGER') !== false) {
            if (strpos($dbType, '(') && preg_match('/\((.*)\)/', $dbType, $matches)) {
                $values = explode(',', $matches[1]);
                if (isset($values[1]) && (((int)$values[1]) > 0)) {
                    $column->type = 'double';
                } else {
                    $column->type = 'integer';
                }
            } else {
                $column->type = 'double';
            }
        } elseif (strpos($dbType, 'BLOB') !== false) {
            $column->type = 'binary';
        } elseif (strpos($dbType, 'CLOB') !== false) {
            $column->type = 'text';
        } else {
            $column->type = 'string';
        }
    }

    /**
     * Extracts size, precision and scale information from column's DB type.
     * @param ColumnSchema $column
     * @param string $dbType the column's DB type
     */
    protected function extractColumnSize($column, $dbType)
    {
        if (strpos($dbType, '(') && preg_match('/\((.*)\)/', $dbType, $matches)) {
            $values = explode(',', $matches[1]);
            $column->size = $column->precision = (int)$values[0];
            if (isset($values[1])) {
                $column->scale = (int)$values[1];
            }
        }
    }

    /**
     * @return \yii\db\ColumnSchema
     * @throws \yii\base\InvalidConfigException
     */
    protected function createColumnSchema()
    {
        return Yii::createObject('apaoww\oci8\ColumnSchema');
    }
}
