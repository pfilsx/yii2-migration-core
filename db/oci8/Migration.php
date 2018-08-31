<?php


namespace yii\db\oci8;


use Yii;
use yii\base\ErrorException;
use yii\base\InvalidArgumentException;
use yii\db\Exception;
use yii\db\Query;

class Migration extends \yii\db\Migration
{
    const TABLE_NAME_MAX_SIZE = 23;

    public $packagesDirectory = 'packages';
    public $functionsDirectory = 'functions';
    public $proceduresDirectory = 'procedures';
    public $viewsDirectory = 'views';
    public $migratePackagesTable = 'migration_packages';

    public function init()
    {
        parent::init();
        if ($this->db->getSchema()->getTableSchema($this->migratePackagesTable, true) === null) {
            $this->db->createCommand()->createTable($this->migratePackagesTable, [
                'version' => 'VARCHAR2(180 BYTE) NOT NULL',
                'apply_time' => 'NUMBER(10, 0)',
                'package' => 'VARCHAR2(50 BYTE)',
                'backup' => 'CLOB'
            ])->execute();
        }
    }

    /**
     * @param string $table
     * @param array $columns
     * @param null $options
     */
    public function createTable($table, $columns, $options = null)
    {
        if (strlen($table) > self::TABLE_NAME_MAX_SIZE) {
            throw new InvalidArgumentException('Table name is too long. Max length is ' . self::TABLE_NAME_MAX_SIZE . ' symbols');
        }

        $table = strtoupper($table);

        $uppedColumns = [];
        foreach ($columns as $key => $column) {
            $uppedColumns[strtoupper($key)] = $column;
        }
        $columns = $uppedColumns;

        parent::createTable($table, $columns, $options);
        $this->createComments($table, $columns);
    }

    public function addColumn($table, $column, $type)
    {
        parent::addColumn($table, $column, $type);
        $this->createComments($table, [$column => $type]);
    }

    public function alterColumn($table, $column, $type)
    {
        parent::alterColumn($table, $column, $type);
        $this->createComments($table, [$column => $type]);
    }

    /**
     * @param string $table
     */
    public function dropTable($table)
    {
        $table = strtoupper($table);
        parent::dropTable($table);
    }


    /**
     * @param string $package
     * @throws ErrorException
     */
    public function createPackage($package)
    {
        $fileName = $package;
        if (strpos(strtoupper($package), 'PKG_') !== 0) {
            $fileName = 'PKG_' . $fileName;
        }
        $uppedPackage = strtoupper($fileName);
        $headFileContent = $this->getFileContent($this->packagesDirectory, $uppedPackage);
        $bodyFileContent = $this->getFileContent($this->packagesDirectory, $uppedPackage . '_BODY');
        $this->execute($headFileContent);
        $this->execute($bodyFileContent);
    }

    /**
     * @param string $package
     */
    public function dropPackage($package)
    {
        $fileName = $package;
        if (strpos(strtoupper($package), 'PKG_') !== 0) {
            $fileName = 'PKG_' . $fileName;
        }
        $this->execute(sprintf('DROP PACKAGE %s', strtoupper($fileName)));
    }

    /**
     * @param string $package
     * @throws ErrorException
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function updatePackage($package)
    {
        $fileName = $package;
        if (strpos(strtoupper($package), 'PKG_') !== 0) {
            $fileName = 'PKG_' . $fileName;
        }
        $uppedPackage = strtoupper($fileName);
        $headFileContent = $this->getFileContent($this->packagesDirectory, $uppedPackage);
        $bodyFileContent = $this->getFileContent($this->packagesDirectory, $uppedPackage . '_BODY');

        try {
            $currentPackageSql = $this->db
                ->createCommand("SELECT DBMS_METADATA.GET_DDL('PACKAGE', '${uppedPackage}') as SQL FROM dual")
                ->queryScalar();
        } catch (Exception $e) {
            $currentPackageSql = null;
        }
        $this->db->createCommand()->insert($this->migratePackagesTable, [
            'version' => get_class($this),
            'apply_time' => time(),
            'package' => $uppedPackage,
            'backup' => $currentPackageSql
        ])->execute();

        $this->execute($headFileContent);
        $this->execute($bodyFileContent);



    }

    /**
     * @param string $package
     */
    public function undoPackage($package)
    {
        $fileName = $package;
        if (strpos(strtoupper($package), 'PKG_') !== 0) {
            $fileName = 'PKG_' . $fileName;
        }
        $uppedPackage = strtoupper($fileName);
        $version = get_class($this);

        $backupPackage = (new Query())
            ->from($this->migratePackagesTable)
            ->where(['version' => $version, 'package' => $uppedPackage])
            ->one($this->db);

        if (empty($backupPackage['backup'])) {
            throw new ErrorException('backup sql not found');
        }

        $backupPackageSql = $backupPackage['backup'];

        // check body exists
        $packageComponents = preg_split('/CREATE\s+OR\s+REPLACE\s+PACKAGE\s+BODY/', $backupPackageSql);

        $this->execute(trim($packageComponents[0]));

        if (!empty($packageComponents[1])) {
            $this->execute(trim('CREATE OR REPLACE PACKAGE BODY' . $packageComponents[1]));
        }

        $this->execute(sprintf(
            'DELETE FROM "%s" WHERE "version"=\'%s\' AND "package"=\'%s\'',
            $this->migratePackagesTable, $version, $uppedPackage
        ));

    }

    public function createFunction($function)
    {
        $fileName = $function;
        if (strpos(strtoupper($function), 'FNC_') !== 0) {
            $fileName = 'FNC_' . $fileName;
        }
        $functionFileContent = $this->getFileContent($this->functionsDirectory, strtoupper($fileName));
        $this->execute($functionFileContent);
    }

    public function updateFunction($function)
    {
        $fileName = $function;
        if (strpos(strtoupper($function), 'FNC_') !== 0) {
            $fileName = 'FNC_' . $fileName;
        }
        $uppedFunction = strtoupper($fileName);
        $functionFileContent = $this->getFileContent($this->functionsDirectory, $uppedFunction);

        try {
            $currentFunctionSql = $this->db
                ->createCommand("SELECT DBMS_METADATA.GET_DDL('FUNCTION', '${uppedFunction}') as SQL FROM dual")
                ->queryScalar();
        } catch (Exception $e) {
            $currentFunctionSql = null;
        }
        $this->db->createCommand()->insert($this->migratePackagesTable, [
            'version' => get_class($this),
            'apply_time' => time(),
            'package' => $uppedFunction,
            'backup' => $currentFunctionSql
        ])->execute();

        $this->execute($functionFileContent);
    }

    public function undoFunction($function)
    {
        $fileName = $function;
        if (strpos(strtoupper($function), 'FNC_') !== 0) {
            $fileName = 'FNC_' . $fileName;
        }
        $uppedFunction = strtoupper($fileName);
        $version = get_class($this);

        $backupFunction = (new Query())
            ->from($this->migratePackagesTable)
            ->where(['version' => $version, 'package' => $uppedFunction])
            ->one($this->db);

        if (empty($backupFunction['backup'])) {
            throw new ErrorException('backup sql not found');
        }

        $backupFunctionSql = $backupFunction['backup'];

        $this->execute(trim($backupFunctionSql));

        $this->execute(sprintf(
            'DELETE FROM "%s" WHERE "version"=\'%s\' AND "package"=\'%s\'',
            $this->migratePackagesTable, $version, $uppedFunction
        ));
    }

    public function deleteFunction($function)
    {
        $fileName = $function;
        if (strpos(strtoupper($function), 'FNC_') !== 0) {
            $fileName = 'FNC_' . $fileName;
        }
        $this->execute(sprintf('DROP FUNCTION %s', strtoupper($fileName)));
    }

    public function createProcedure($procedure)
    {
        $fileName = $procedure;
        if (strpos(strtoupper($procedure), 'SP_') !== 0) {
            $fileName = 'SP_' . $fileName;
        }
        $procedureFileContent = $this->getFileContent($this->proceduresDirectory, strtoupper($fileName));
        $this->execute($procedureFileContent);
    }

    public function updateProcedure($procedure)
    {
        $fileName = $procedure;
        if (strpos(strtoupper($procedure), 'SP_') !== 0) {
            $fileName = 'SP_' . $fileName;
        }
        $uppedProcedure = strtoupper($fileName);
        $procedureFileContent = $this->getFileContent($this->proceduresDirectory, $uppedProcedure);

        try {
            $currentProcedureSql = $this->db
                ->createCommand("SELECT DBMS_METADATA.GET_DDL('PROCEDURE', '${uppedProcedure}') as SQL FROM dual")
                ->queryScalar();
        } catch (Exception $e) {
            $currentProcedureSql = null;
        }
        $this->db->createCommand()->insert($this->migratePackagesTable, [
            'version' => get_class($this),
            'apply_time' => time(),
            'package' => $uppedProcedure,
            'backup' => $currentProcedureSql
        ])->execute();

        $this->execute($procedureFileContent);
    }

    public function undoProcedure($procedure)
    {
        $fileName = $procedure;
        if (strpos(strtoupper($procedure), 'SP_') !== 0) {
            $fileName = 'SP_' . $fileName;
        }
        $uppedProcedure = strtoupper($fileName);
        $version = get_class($this);

        $backupProcedure = (new Query())
            ->from($this->migratePackagesTable)
            ->where(['version' => $version, 'package' => $uppedProcedure])
            ->one($this->db);

        if (empty($backupProcedure['backup'])) {
            throw new ErrorException('backup sql not found');
        }

        $backupProcedureSql = $backupProcedure['backup'];

        $this->execute(trim($backupProcedureSql));

        $this->execute(sprintf(
            'DELETE FROM "%s" WHERE "version"=\'%s\' AND "package"=\'%s\'',
            $this->migratePackagesTable, $version, $uppedProcedure
        ));
    }

    public function deleteProcedure($procedure)
    {
        $fileName = $procedure;
        if (strpos(strtoupper($procedure), 'SP_') !== 0) {
            $fileName = 'SP_' . $fileName;
        }
        $this->execute(sprintf('DROP PROCEDURE %s', strtoupper($fileName)));
    }

    /**
     * @param $view
     */
    public function createView($view)
    {
        $fileName = $view;
        if (strpos(strtoupper($view), 'VW_') !== 0) {
            $fileName = 'VW_' . $fileName;
        }
        $viewFileContent = $this->getFileContent($this->viewsDirectory, strtoupper($fileName));
        $this->execute($viewFileContent);
    }

    /**
     * @param $view
     */
    public function updateView($view)
    {
        $fileName = $view;
        if (strpos(strtoupper($view), 'VW_') !== 0) {
            $fileName = 'VW_' . $fileName;
        }
        $uppedView = strtoupper($fileName);
        $viewFileContent = $this->getFileContent($this->viewsDirectory, $uppedView);

        try {
            $currentViewSql = $this->db
                ->createCommand("SELECT DBMS_METADATA.GET_DDL('VIEW', '${uppedView}') as SQL FROM dual")
                ->queryScalar();
        } catch (Exception $e) {
            $currentViewSql = null;
        }
        $this->db->createCommand()->insert($this->migratePackagesTable, [
            'version' => get_class($this),
            'apply_time' => time(),
            'package' => $uppedView,
            'backup' => $currentViewSql
        ])->execute();
        $this->execute($viewFileContent);
    }

    /**
     * @param $view
     */
    public function undoView($view)
    {
        $fileName = $view;
        if (strpos(strtoupper($view), 'VW_') !== 0) {
            $fileName = 'VW_' . $fileName;
        }
        $uppedView = strtoupper($fileName);
        $version = get_class($this);

        $backupView = (new Query())
            ->from($this->migratePackagesTable)
            ->where(['version' => $version, 'package' => $uppedView])
            ->one($this->db);

        if (empty($backupView['backup'])) {
            throw new ErrorException('backup sql not found');
        }

        $backupViewSql = $backupView['backup'];

        $this->execute(trim($backupViewSql));

        $this->execute(sprintf(
            'DELETE FROM "%s" WHERE "version"=\'%s\' AND "package"=\'%s\'',
            $this->migratePackagesTable, $version, $uppedView
        ));
    }

    /**
     * @param $view
     */
    public function deleteView($view)
    {
        $fileName = $view;
        if (strpos(strtoupper($view), 'VW_') !== 0) {
            $fileName = 'VW_' . $fileName;
        }
        $this->execute(sprintf('DROP VIEW %s', strtoupper($fileName)));
    }

    /**
     * @param string $table
     * @param array $columns
     */
    private function createAutoIncrements($table, $columns)
    {
        foreach ($columns as $key => $column) {
            if (is_object($column) && $column->autoIncrement === true) {
                $this->execute(sprintf(
                    'CREATE SEQUENCE "SEQ_%s_ID" MINVALUE 1 START WITH 1 INCREMENT BY 1 NOCACHE',
                    $table
                ));
                $this->execute(sprintf(
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
                $columns[$key]->comment = '_autoIncremented';
            }
        }
    }

    /**
     * @param string $table
     * @param array $columns
     */
    private function createComments($table, $columns)
    {
        foreach ($columns as $key => $column) {
            if (!empty($column->comment)) {
                $this->execute(sprintf('COMMENT ON COLUMN "%s"."%s" IS \'%s\'', $table, $key, $column->comment));
            }
        }
    }

    /**
     * Получить содержимое DDL файла
     * @param string $directory
     * @param string $fileName
     * @return string
     */
    protected function getFileContent($directory, $fileName)
    {
        $paths = Yii::$app->controller->migrationPath;
        if (is_array($paths)) {
            foreach ($paths as $path) {
                $file = $path . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $fileName . '.sql';
                if (file_exists($file)) {
                    return trim(file_get_contents($file));
                }
            }
        } else {
            $file = $paths . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $fileName . '.sql';
            if (file_exists($file)) {
                return trim(file_get_contents($file));
            }
        }
        throw new ErrorException('File not found: ' . $file);
    }

}