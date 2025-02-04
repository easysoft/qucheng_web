<?php
/**
 * The model file of backup module of QuCheng.
 *
 * @copyright   Copyright 2021-2022 北京渠成软件有限公司(BeiJing QuCheng Software Co,LTD, www.qucheng.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author      Jianhua Wang <wangjianhua@easycorp.ltd>
 * @package     backup
 * @version     $Id$
 * @link        https://www.qucheng.com
 */
class backupModel extends model
{
    /**
     * Backup SQL.
     *
     * @param  string    $backupFile
     * @access public
     * @return object
     */
    public function backSQL($backupFile, $backupType = 'manual')
    {
        $zdb = $this->app->loadClass('zdb');
        $dumpStatus = $zdb->dump($backupFile);
        if($dumpStatus->result === true) $this->processSQLSummary($backupFile, $backupType);
        return $dumpStatus;
    }

    /**
     * Backup file.
     *
     * @param  string    $backupFile
     * @access public
     * @return object
     */
    public function backFile($backupFile)
    {
        $zfile  = $this->app->loadClass('zfile');
        $return = new stdclass();
        $return->result = true;
        $return->error  = '';

        if(!is_dir($backupFile)) mkdir($backupFile, 0777, true);

        $tmpLogFile = $this->getTmpLogFile($backupFile);
        $dataDir    = $this->app->getAppRoot() . 'www/data/';
        $count      = $zfile->getCount($dataDir);
        file_put_contents($tmpLogFile, json_encode(array('allCount' => $count)));

        $result = $zfile->copyDir($dataDir, $backupFile, $logLevel = false, $tmpLogFile);
        $this->processSummary($backupFile, $result['count'], $result['size'], $result['errorFiles'], $count);
        unlink($tmpLogFile);

        return $return;
    }

    /**
     * Backup code.
     *
     * @param  string    $backupFile
     * @access public
     * @return object
     */
    public function backCode($backupFile)
    {
        $zfile  = $this->app->loadClass('zfile');
        $return = new stdclass();
        $return->result = true;
        $return->error  = '';

        $tmpLogFile  = $this->getTmpLogFile($backupFile);
        $appRoot     = $this->app->getAppRoot();
        $fileList    = glob($appRoot . '*');
        $wwwFileList = glob($appRoot . 'www/*');

        $tmpFile  = array_search($appRoot . 'tmp', $fileList);
        $wwwFile  = array_search($appRoot . 'www', $fileList);
        $dataFile = array_search($appRoot . 'www/data', $wwwFileList);
        unset($fileList[$tmpFile]);
        unset($fileList[$wwwFile]);
        unset($wwwFileList[$dataFile]);

        $fileList = array_merge($fileList, $wwwFileList);

        if(!is_dir($backupFile)) mkdir($backupFile, 0777, true);

        $allCount = 0;
        foreach($fileList as $codeFile) $allCount += $zfile->getCount($codeFile);
        file_put_contents($tmpLogFile, json_encode(array('allCount' => $allCount)));

        $copiedCount = 0;
        $copiedSize  = 0;
        $errorFiles  = array();
        foreach($fileList as $codeFile)
        {
            $file = trim(str_replace($appRoot, '', $codeFile), DS);
            if(is_dir($codeFile))
            {
                if(!is_dir($backupFile . DS . $file)) mkdir($backupFile . DS . $file, 0777, true);
                $result = $zfile->copyDir($codeFile, $backupFile . DS . $file, $logLevel = false, $tmpLogFile);
                $copiedCount += $result['count'];
                $copiedSize  += $result['size'];
                $errorFiles  += $result['errorFiles'];
            }
            else
            {
                $dirName = dirname($file);
                if(!is_dir($backupFile . DS . $dirName)) mkdir($backupFile . DS . $dirName, 0777, true);
                if($zfile->copyFile($codeFile, $backupFile . DS . $file))
                {
                    $copiedCount += 1;
                    $copiedSize  += filesize($codeFile);
                }
                else
                {
                    $errorFiles[] = $codeFile;
                }
            }
        }

        $this->processSummary($backupFile, $copiedCount, $copiedSize, $errorFiles, $allCount);
        unlink($tmpLogFile);

        return $return;
    }

    /**
     * Restore SQL.
     *
     * @param  string    $backupFile
     * @access public
     * @return object
     */
    public function restoreSQL($backupFile)
    {
        $zdb    = $this->app->loadClass('zdb');
        $nosafe = strpos($this->config->backup->setting, 'nosafe') !== false;

        $backupDir    = dirname($backupFile);
        $fileName     = date('YmdHis') . mt_rand(0, 9);
        $backFileName = "{$backupDir}/{$fileName}.sql";
        if(!$nosafe) $backFileName .= '.php';

        $result = $this->backSQL($backFileName, 'restore');
        if($result->result and !$nosafe) $this->addFileHeader($backFileName);

        $allTables = $zdb->getAllTables();
        foreach($allTables as $tableName => $tableType)
        {
            try
            {
                $this->dbh->query("DROP $tableType IF EXISTS `$tableName`");
            }
            catch(PDOException $e){}
        }

        $importResult = $zdb->import($backupFile);

        if($importResult && $importResult->result)
        {
            $this->loadModel('instance')->restoreInstanceList();
            $this->processRestoreSummary('sql', 'done');
        }

        return $importResult;
    }

    /**
     * Restore File.
     *
     * @param  string    $backupFile
     * @access public
     * @return object
     */
    public function restoreFile($backupFile)
    {
        $return = new stdclass();
        $return->result = true;
        $return->error  = '';

        if(is_file($backupFile))
        {
            $oldDir = getcwd();
            chdir($this->app->getTmpRoot());
            $this->app->loadClass('pclzip', true);
            $zip = new pclzip($backupFile);
            if($zip->extract(PCLZIP_OPT_PATH, $this->app->getAppRoot() . 'www/data/', PCLZIP_OPT_TEMP_FILE_ON) == 0)
            {
                $return->result = false;
                $return->error  = $zip->errorInfo();
            }
            chdir($oldDir);
        }
        elseif(is_dir($backupFile))
        {
            $zfile = $this->app->loadClass('zfile');
            $zfile->copyDir($backupFile, $this->app->getAppRoot() . 'www/data/', $showDetails = false);
        }

        $this->processRestoreSummary('file', 'done');

        return $return;
    }

    /**
     * Add file header.
     *
     * @param  string    $fileName
     * @access public
     * @return bool
     */
    public function addFileHeader($fileName)
    {
        $firstline = false;
        $die       = "<?php die();?" . ">\n";
        $fileSize  = filesize($fileName);

        $fh    = fopen($fileName, 'c+');
        $delta = strlen($die);
        while(true)
        {
            $offset = ftell($fh);
            $line   = fread($fh, 1024 * 1024);
            if(!$firstline)
            {
                $line = $die . $line;
                $firstline = true;
            }
            else
            {
                $line = $compensate . $line;
            }

            $compensate = fread($fh, $delta);
            fseek($fh, $offset);
            fwrite($fh, $line);

            if(ftell($fh) >= $fileSize)
            {
                fwrite($fh, $compensate);
                break;
            }
        }
        fclose($fh);
        return true;
    }

    /**
     * Remove file header.
     *
     * @param  string    $fileName
     * @access public
     * @return bool
     */
    public function removeFileHeader($fileName)
    {
        $firstline = false;
        $die       = "<?php die();?" . ">\n";
        $fileSize  = filesize($fileName);

        $fh = fopen($fileName, 'c+');
        while(true)
        {
            $offset = ftell($fh);
            if($firstline and $delta) fseek($fh, $offset + $delta);
            $line = fread($fh, 1024 * 1024);
            if(!$firstline)
            {
                $firstline    = true;
                $beforeLength = strlen($line);
                $line         = str_replace($die, '', $line);
                $afterLength  = strlen($line);
                $delta        = $beforeLength - $afterLength;
                if($delta == 0)
                {
                    fclose($fh);
                    return true;
                }
            }
            fseek($fh, $offset);
            fwrite($fh, $line);

            if(ftell($fh) >= $fileSize - $delta) break;
        }
        ftruncate($fh, ($fileSize - $delta));
        fclose($fh);
        return true;
    }

    /**
     * Get dir size.
     *
     * @param  string    $backup
     * @access public
     * @return int
     */
    public function getBackupSummary($backup)
    {
        $zfile = $this->app->loadClass('zfile');
        if(is_file($backup))
        {
            $summary = array();
            $summary['allCount'] = 1;
            $summary['count']    = 1;
            $summary['size']     = $zfile->getFileSize($backup);

            return $summary;
        }

        $summaryFile = dirname($backup) . DS . 'summary';
        if(!file_exists($summaryFile)) return array();

        $summary = json_decode(file_get_contents(dirname($backup) . DS . 'summary'), 'true');
        return isset($summary[basename($backup)]) ? $summary[basename($backup)] : array();
    }

    /**
     * Get backup account and backup type.
     *
     * @param  string  $file
     * @access public
     * @return array
     */
    public function getSQLSummary($file)
    {
        $summaryFile = $this->getBackupPath() . DS . 'summary';
        $sqlSummary = json_decode(file_get_contents($summaryFile), true);
        return isset($sqlSummary[basename($file)]) ? $sqlSummary[basename($file)] : array();
    }

    /**
     * Get backup path.
     *
     * @access public
     * @return string
     */
    public function getBackupPath()
    {
        $backupPath = empty($this->config->backup->settingDir) ? $this->app->getTmpRoot() . 'backup' . DS : $this->config->backup->settingDir;
        return rtrim(str_replace('\\', '/', $backupPath), '/') . '/';
    }

    /**
     * Get backup file.
     *
     * @param  string    $name
     * @param  string    $type
     * @access public
     * @return string
     */
    public function getBackupFile($name, $type)
    {
        $backupPath = $this->getBackupPath();
        if($type == 'sql')
        {
            if(file_exists($backupPath . $name . ".{$type}")) return $backupPath . $name . ".{$type}";
            if(file_exists($backupPath . $name . ".{$type}.php")) return $backupPath . $name . ".{$type}.php";
        }
        else
        {
            if(file_exists($backupPath . $name . ".{$type}")) return $backupPath . $name . ".{$type}";
            if(file_exists($backupPath . $name . ".{$type}.zip")) return $backupPath . $name . ".{$type}.zip";
            if(file_exists($backupPath . $name . ".{$type}.zip.php")) return $backupPath . $name . ".{$type}.zip.php";
        }

        return false;
    }

    /**
     * Get tmp log file.
     *
     * @param  string $backupFile
     * @access public
     * @return string
     */
    public function getTmpLogFile($backupFile)
    {
        $backupDir  = dirname($backupFile);
        return $backupDir . DS . basename($backupFile) . '.tmp.summary';
    }

    /**
     * Get backup dir progress.
     *
     * @param  string $backup
     * @access public
     * @return array
     */
    public function getBackupDirProgress($backup)
    {
        $tmpLogFile = $this->getTmpLogFile($backup);
        if(file_exists($tmpLogFile)) return json_decode(file_get_contents($tmpLogFile), true);
        return array();
    }

    /**
     * Process filesize.
     *
     * @param  int    $fileSize
     * @access public
     * @return string
     */
    public function processFileSize($fileSize)
    {
        $bit = 'KB';
        $fileSize = round($fileSize / 1024, 2);
        if($fileSize >= 1024)
        {
            $bit = 'MB';
            $fileSize = round($fileSize / 1024, 2);
        }
        if($fileSize >= 1024)
        {
            $bit = 'GB';
            $fileSize = round($fileSize / 1024, 2);
        }

        return $fileSize . $bit;
    }

    /**
     * Process backup summary.
     *
     * @param  string $file
     * @param  int    $count
     * @param  int    $size
     * @param  array  $errorFiles
     * @param  int    $allCount
     * @param  string $action  add|delete
     * @access public
     * @return bool
     */
    public function processSummary($file, $count, $size, $errorFiles = array(), $allCount = 0, $action = 'add')
    {
        $backupPath = dirname($file);
        $fileName   = basename($file);

        $summaryFile = $backupPath . DS . 'summary';
        if(!file_exists($summaryFile) and !touch($summaryFile)) return false;

        $summary = json_decode(file_get_contents($summaryFile), true);
        if(empty($summary)) $summary = array();

        if($action == 'add')
        {
            $summary[$fileName]['allCount']   = $allCount;
            $summary[$fileName]['errorFiles'] = $errorFiles;
            $summary[$fileName]['count']      = $count;
            $summary[$fileName]['size']       = $size;
        }
        else
        {
            unset($summary[$fileName]);
        }

        if(file_put_contents($summaryFile, json_encode($summary))) return true;
        return false;
    }

    /**
     * Process restore summay.
     *
     * @param  string $restoreType
     * @param  string $status
     * @param  string $action
     * @access public
     * @return bool
     */
    public function processRestoreSummary($restoreType = 'sql', $status = 'done', $action = 'add')
    {
        $summaryFile = $this->getBackupPath() . DS . 'restoreSummary';
        if(!file_exists($summaryFile) and !touch($summaryFile)) return false;

        $summary = json_decode(file_get_contents($summaryFile), true);
        if(empty($summary)) $summary = array();

        if($action == 'add')
        {
            $summary[$restoreType] = $status;
        }
        else
        {
            $summary = array();
        }
        if(file_put_contents($summaryFile, json_encode($summary))) return true;
        return false;

    }

    /**
     * Save backup account and backup type.
     *
     * @param  string $file
     * @param  string $type
     * @param  string $action
     * @access public
     * @return bool
     */
    public function processSQLSummary($file, $type = 'manual', $action = 'add')
    {
        $backupPath = dirname($file);
        $fileName   = basename($file);

        $summaryFile = $backupPath . DS . 'summary';
        if(!file_exists($summaryFile) and !touch($summaryFile)) return false;

        $summary = json_decode(file_get_contents($summaryFile), true);
        if(empty($summary)) $summary = array();

        if($action == 'add')
        {
            $summary[$fileName]['account']    = $this->app->user->account == 'guest' ? '' : $this->app->user->account;
            $summary[$fileName]['backupType'] = $type;
        }
        else
        {
            unset($summary[$fileName]);
        }

        if(file_put_contents($summaryFile, json_encode($summary))) return true;
        return false;
    }

    /**
     * Check upgrade process is overtime (5 miniutes) or not.
     *
     * @access public
     * @return mixed
     */
    public function isGradeOvertime()
    {
        $upgradedAt = $this->loadModel('setting')->getItem('owner=system&module=backup&section=global&key=upgradedAt');

        return (time() - intval($upgradedAt)) > 300;
    }
}
