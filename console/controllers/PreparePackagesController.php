<?php


namespace yii\console\controllers;

use Yii;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\FileHelper;

class PreparePackagesController extends Controller
{
    public $migrationPath = ['@app/migrations'];

    public $env = 'prod';

    public $defaultAction = 'prepare';

    protected $replaceValues = [];

    public function options($actionID)
    {
        return array_merge(
            parent::options($actionID),
            ['migrationPath', 'env']
        );
    }

    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'p' => 'migrationPath',
            'e' => 'env'
        ]);
    }

    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            if (empty($this->migrationPath)) {
                throw new InvalidConfigException('`migrationPath` should be specified.');
            }

            if (is_array($this->migrationPath)) {
                foreach ($this->migrationPath as $i => $path) {
                    $this->migrationPath[$i] = Yii::getAlias($path);
                }
            } elseif ($this->migrationPath !== null) {
                $path = Yii::getAlias($this->migrationPath);
                if (!is_dir($path)) {
                    FileHelper::createDirectory($path);
                }
                $this->migrationPath = $path;
            }

            $this->replaceValues = array_key_exists($this->env, Yii::$app->params)
                ? Yii::$app->params[$this->env]
                : Yii::$app->params;

            $version = Yii::getVersion();
            $this->stdout("Yii Packages Tool (based on Yii v{$version})\n\n");

            return true;
        }

        return false;
    }

    public function actionPrepare(){
        $packages = $this->getPackagesList();
        if (empty($packages)) {
            $this->stdout("No packages.install found.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }
        $packagesNames = array_filter($packages, function($package){
            return strpos(strtolower($package), '_body') === false;
        });
        $n = count($packagesNames);
        $this->stdout("Total $n " . ($n === 1 ? 'package' : 'packages') . " to be prepared:\n", Console::FG_YELLOW);
        foreach ($packagesNames as $package) {
            $this->stdout("\t$package\n");
        }
        $this->stdout("\n");
        if ($this->confirm('Prepare the above ' . ($n === 1 ? 'package' : 'packages') . '?')) {
            foreach ($packages as $package) {
                $this->prepare($package);
            }
            $this->stdout("\nPackages prepared successfully.\n", Console::FG_GREEN);
        }
    }

    protected function prepare($package){
        $filename = str_replace('packages.install', 'packages', $package);
        FileHelper::createDirectory(dirname($filename));
        $handleInput = fopen($package, 'r');
        $handleOutput = fopen($filename, 'w');
        if ($handleInput && $handleOutput){
            while (($line = fgets($handleInput)) !== false){

                $line = preg_replace_callback('/\{([^\}]+)\}/i', function($matches){
                    if (array_key_exists($matches[1], $this->replaceValues)){
                        return $this->replaceValues[$matches[1]];
                    }
                    return $matches[0];
                }, $line);
                fwrite($handleOutput, $line);
            }
            fclose($handleInput);
            fclose($handleOutput);
        }
    }

    protected function getPackagesList(){
        $migrationPaths = [];
        if (is_array($this->migrationPath)) {
            $migrationPaths = $this->migrationPath;
        } elseif (!empty($this->migrationPath)) {
            $migrationPaths[] = $this->migrationPath;
        }

        $packages = [];
        foreach ($migrationPaths as $migrationPath) {
            if (!file_exists($migrationPath . DIRECTORY_SEPARATOR . 'packages.install')) {
                continue;
            }
            $packages = array_merge($packages, FileHelper::findFiles($migrationPath . DIRECTORY_SEPARATOR . 'packages.install', [
                'only'=>['*.sql'],'recursive'=>true
            ]));
        }
        return array_values($packages);
    }
}