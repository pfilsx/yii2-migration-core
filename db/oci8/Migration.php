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
     * @inheritdoc
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
    }
    /**
     * @inheritdoc
     */
    public function dropTable($table)
    {
        $table = strtoupper($table);
        parent::dropTable($table);
    }


    /**
     * Create package from sql file
     * @param string $package - package name(filename, like PKG_REPORTS)
     * @throws ErrorException
     */
    public function createPackage($package)
    {
        $uppedPackage = strtoupper($package);
        $headFileContent = $this->getFileContent($this->packagesDirectory, $uppedPackage);
        $bodyFileContent = $this->getFileContent($this->packagesDirectory, $uppedPackage . '_BODY');
        $this->execute($headFileContent);
        $this->execute($bodyFileContent);
    }

    /**
     * Drop package from db
     * @param string $package - package name(filename, like PKG_REPORTS)
     */
    public function dropPackage($package)
    {
        $this->execute(sprintf('DROP PACKAGE %s', strtoupper($package)));
    }

    /**
     * Update package to new version
     * @param string $package - package name(filename, like PKG_REPORTS)
     * @throws ErrorException
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function updatePackage($package)
    {
        $uppedPackage = strtoupper($package);
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
     * Downgrade package to backup
     * @param string $package - package name(like PKG_REPORTS)
     */
    public function undoPackage($package)
    {
        $uppedPackage = strtoupper($package);
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

    /**
     *
     * @param $function
     * @throws ErrorException
     * @see Migration::createPackage()
     */
    public function createFunction($function)
    {
        $functionFileContent = $this->getFileContent($this->functionsDirectory, strtoupper($function));
        $this->execute($functionFileContent);
    }

    /**
     * @param $function
     * @throws ErrorException
     * @throws Exception
     * @see Migration::updatePackage()
     */
    public function updateFunction($function)
    {
        $uppedFunction = strtoupper($function);
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

    /**
     * @param $function
     * @throws ErrorException
     * @see Migration::undoPackage()
     */
    public function undoFunction($function)
    {
        $uppedFunction = strtoupper($function);
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

    /**
     * @param $function
     * @see Migration::dropPackage()
     */
    public function dropFunction($function)
    {
        $this->execute(sprintf('DROP FUNCTION %s', strtoupper($function)));
    }

    /**
     * @param $procedure
     * @throws ErrorException
     * @see Migration::createPackage()
     */
    public function createProcedure($procedure)
    {
        $procedureFileContent = $this->getFileContent($this->proceduresDirectory, strtoupper($procedure));
        $this->execute($procedureFileContent);
    }

    /**
     * @param $procedure
     * @throws ErrorException
     * @throws Exception
     * @see Migration::updatePackage()
     */
    public function updateProcedure($procedure)
    {
        $uppedProcedure = strtoupper($procedure);
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

    /**
     * @param $procedure
     * @throws ErrorException
     * @see Migration::undoPackage()
     */
    public function undoProcedure($procedure)
    {
        $uppedProcedure = strtoupper($procedure);
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

    /**
     * @param $procedure
     * @see Migration::dropPackage()
     */
    public function dropProcedure($procedure)
    {
        $this->execute(sprintf('DROP PROCEDURE %s', strtoupper($procedure)));
    }

    /**
     * @param $view
     * @see Migration::createPackage()
     */
    public function createView($view)
    {
        $viewFileContent = $this->getFileContent($this->viewsDirectory, strtoupper($view));
        $this->execute($viewFileContent);
    }

    /**
     * @param $view
     * @see Migration::updatePackage()
     */
    public function updateView($view)
    {
        $uppedView = strtoupper($view);
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
     * @see Migration::undoPackage()
     */
    public function undoView($view)
    {
        $uppedView = strtoupper($view);
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
     * @see Migration::dropPackage()
     */
    public function dropView($view)
    {
        $this->execute(sprintf('DROP VIEW %s', strtoupper($view)));
    }

    public function interval($from = 'DAY', $to = 'SECOND'){
        $from = strtoupper($from);
        $to = strtoupper($to);
        return $this->getDb()->getSchema()
            ->createColumnSchemaBuilder(Schema::TYPE_INTERVAL)->append("$from TO $to");
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