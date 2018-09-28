<?php


namespace yii\db\oci8;


use Yii;
use yii\base\InvalidArgumentException;
use yii\db\Connection;

/**
 * Class Command
 * @package yii\db\oci8
 *
 */
class Command extends \yii\db\Command
{
    /**
     * @inheritdoc
     */
    public function createTable($table, $columns, $options = null){
        $result = parent::createTable($table, $columns, $options);
        foreach ($columns as $key => $column) {
            if (is_object($column) && $column->autoIncrement === true) {
                $result->execute();
                $sql = $this->db->getQueryBuilder()->createSequence("SEQ_{$table}_ID");
                $this->db->createCommand($sql)->execute();
                $result = $this->db->createCommand(sprintf(
                    '
                        CREATE OR REPLACE TRIGGER "TRG_%s_ID"
                           BEFORE INSERT ON "%s"
                           FOR EACH ROW
                        BEGIN
                           IF INSERTING THEN
                              IF :NEW."%s" IS NULL THEN
                                 SELECT SEQ_%s_ID.NEXTVAL INTO :NEW."%s" FROM DUAL;
                              END IF;
                           END IF;
                        END;
                    ',
                    $table, $table, $key, $table, $key
                ));
                break;
            }
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function dropTable($table){
        $tableSchema = $this->db->getTableSchema($table);
        if ($tableSchema === null) {
            throw new InvalidArgumentException("Unknown table: $table");
        }

        if (
            $tableSchema->sequenceName !== null &&
            $tableSchema->sequenceName == "SEQ_{$table}_ID"
        ) {
            $this->db->createCommand($this->db->getQueryBuilder()->dropSequence("SEQ_{$table}_ID"))->execute();
        }
        return parent::dropTable($table);
    }
}