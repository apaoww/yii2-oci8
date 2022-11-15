<?php

namespace apaoww\oci8;


use yii\db\Expression;
use yii\db\TableSchema;

class ESchemaOci extends \yii\db\oci\Schema
{

    protected function findColumns($table)
    {
        $sql = <<<'SQL'
SELECT
    A.COLUMN_NAME,
    A.DATA_TYPE,
    A.DATA_PRECISION,
    A.DATA_SCALE,
    (
      CASE A.CHAR_USED WHEN 'C' THEN A.CHAR_LENGTH
        ELSE A.DATA_LENGTH
      END
    ) AS DATA_LENGTH,
    A.NULLABLE,
    A.DATA_DEFAULT,
    COM.COMMENTS AS COLUMN_COMMENT
FROM ALL_TAB_COLUMNS A
    INNER JOIN ALL_OBJECTS B ON B.OWNER = A.OWNER AND LTRIM(B.OBJECT_NAME) = LTRIM(A.TABLE_NAME)
    LEFT JOIN ALL_COL_COMMENTS COM ON (A.OWNER = COM.OWNER AND A.TABLE_NAME = COM.TABLE_NAME AND A.COLUMN_NAME = COM.COLUMN_NAME)
WHERE
    A.OWNER = :schemaName
    AND B.OBJECT_TYPE IN ('TABLE', 'VIEW', 'MATERIALIZED VIEW')
    AND B.OBJECT_NAME = :tableName
ORDER BY A.COLUMN_ID
SQL;

        try {
            $columns = $this->db->createCommand($sql, [
                ':tableName' => $table->name,
                ':schemaName' => $table->schemaName,
            ])->queryAll();
        } catch (\Exception $e) {
            return false;
        }

        if (empty($columns)) {
            return false;
        }

        foreach ($columns as $key=> $column) {
            if ($this->db->slavePdo->getAttribute(\PDO::ATTR_CASE) === \PDO::CASE_LOWER) {
                $column = array_change_key_case($column, CASE_LOWER);
                $column = array_map('strtolower', $column);
            }
            $c = $this->createColumn($column);
            $table->columns[$c->name] = $c;
        }

        return true;
    }

    protected function createColumn($column)
    {
        $c = $this->createColumnSchema();
        $c->name = $column['column_name'];
        $c->allowNull = $column['nullable'] === 'Y';
        $c->isPrimaryKey = strpos($column['key'] ?? null, 'P') !== false;
        $c->comment = $column['column_comment'] === null ? '' : $column['column_comment'];
        $this->extractColumnType($c, $column['data_type'], $column['data_precision'], $column['data_scale'], $column['data_length']);
        $this->extractColumnSize($c, $column['data_type'], $column['data_precision'], $column['data_scale'], $column['data_length']);

        if (!$c->isPrimaryKey) {
            if (stripos($column['data_default'], 'timestamp') !== false) {
                $c->defaultValue = null;
            } else {
                $c->defaultValue = $c->phpTypecast($column['data_default']);
            }
        }
        return $c;
    }

    protected function extractColumnType($column, $dbType, $precision, $scale, $length)
    {
        $column->dbType = $dbType;

        if (strpos($dbType, 'FLOAT') !== false || strpos($dbType, 'DOUBLE') !== false) {
            $column->type = 'double';
        } elseif (strpos($dbType, 'NUMBER') !== false) {
            if ($scale === null || $scale > 0) {
                $column->type = 'decimal';
            } else {
                $column->type = 'integer';
            }
        } elseif (strpos($dbType, 'INTEGER') !== false) {
            $column->type = 'integer';
        } elseif (strpos($dbType, 'BLOB') !== false) {
            $column->type = 'binary';
        } elseif (strpos($dbType, 'CLOB') !== false) {
            $column->type = 'text';
        } elseif (strpos($dbType, 'TIMESTAMP') !== false) {
            $column->type = 'timestamp';
        } else {
            $column->type = 'string';
        }
    }

    protected function findConstraints($table)
    {
        $sql = <<<EOD
        SELECT D.constraint_type as CONSTRAINT_TYPE, C.COLUMN_NAME, C.position, D.r_constraint_name,
                E.table_name as table_ref, f.column_name as column_ref,
                C.table_name
        FROM ALL_CONS_COLUMNS C
        inner join ALL_constraints D on D.OWNER = C.OWNER and D.constraint_name = C.constraint_name
        left join ALL_constraints E on E.OWNER = D.r_OWNER and E.constraint_name = D.r_constraint_name
        left join ALL_cons_columns F on F.OWNER = E.OWNER and F.constraint_name = E.constraint_name and F.position = c.position
        WHERE C.OWNER = '{$table->schemaName}'
           and C.table_name = '{$table->name}'
           and D.constraint_type <> 'P'
        order by d.constraint_name, c.position
EOD;
        $command = $this->db->createCommand($sql);
        foreach ($command->queryAll() as $row) {
            if ($row['constraint_type'] === 'R') {
                $name = $row["column_name"];
                $table->foreignKeys[$name] = [$row["table_ref"], $row["column_ref"]];
            }
        }
    }

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
            $names[] = $row['table_name'];
        }
        return $names;
    }

    public function quoteSimpleColumnName($name)
    {
        return $name;
    }

}