<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

if (php_uname('s') != 'Linux') {
    define("OS_NAME", 'Windows');
    define("BIN_DIR", 'bin' . DIRECTORY_SEPARATOR);
    define("SHARE_DIR", 'share' . DIRECTORY_SEPARATOR);
} else {
    define("OS_NAME", 'Linux');
    define("BIN_DIR", '/opt/drainware/bin/');
    define("SHARE_DIR", '/usr/share/');
}

define("BIN_DOC2TXT", BIN_DIR . 'catdoc');
define("BIN_XLS2TXT", BIN_DIR . 'xls2csv');
define("BIN_PPT2TXT", BIN_DIR . 'catppt');
define("BIN_ODS2TXT", BIN_DIR . 'ods2txt');
define("BIN_ODT2TXT", BIN_DIR . 'odt2txt');
define("BIN_ODP2TXT", BIN_DIR . 'odp2txt');
define("BIN_PDF2TXT", BIN_DIR . 'pdftotext');
define("BIN_SSDEEP", BIN_DIR . 'ssdeep');
define("BIN_7Z", BIN_DIR . '7za');

class Tools {

    public static function createTempBackUp($file_path) {
        $tempfile = tempnam('', '');
        if (file_exists($tempfile)) {
            unlink($tempfile);
            $info = pathinfo($tempfile);
            $tempdir = $info['dirname'] . DIRECTORY_SEPARATOR . 'ZHJhaW53YXJl' . $info['basename'];
        }
        mkdir($tempdir);

        $src = $file_path;
        $dst = $tempdir . DIRECTORY_SEPARATOR . basename($file_path);
        copy($src, $dst);
        return $tempdir;
    }

    public static function removeTempBackUp($dir_path) {

        if (false != ($handle = opendir($dir_path))) {
            while (false !== ($file = readdir($handle))) {
                if ($file != '.' && $file != '..') {
                    if (is_dir($dir_path . DIRECTORY_SEPARATOR . $file)) {
                        Tools::removeTempBackUp($dir_path . DIRECTORY_SEPARATOR . $file);
                    } else {
                        unlink($dir_path . DIRECTORY_SEPARATOR . $file);
                    }
                }
            }
            closedir($handle);
            rmdir($dir_path);
        }
    }

    public static function useQuotes($file_path) {
        return '"' . $file_path . '"';
    }

    public static function getRelativeFileName($file_path) {
        $result = array();
        $pattern = '/.*ZHJhaW53YXJl.*.tmp\\' . DIRECTORY_SEPARATOR . '(?P<path>.*)/';
        preg_match_all($pattern, $file_path, $result);

        $relative_path = $result['path'][0];
        return $relative_path;
    }

    public static function readFile($file_path) {
        $content = '';

        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
        } else {
            $format = 'Error: %s doesnt exists';
            Tools::printError(sprintf($format, basename($file_path)));
        }

        return $content;
    }

    public static function getFileList($directory, $recursive = true) {
        $file_list = array();

        if (false != ($handle = opendir($directory))) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    if (is_dir($directory . DIRECTORY_SEPARATOR . $file)) {
                        if ($recursive) {
                            $file_list = array_merge($file_list, Tools::getFileList($directory . DIRECTORY_SEPARATOR . $file, $recursive));
                        }
                    } else {
                        $file = $directory . DIRECTORY_SEPARATOR . $file;
                        $file_list[] = preg_replace("/\/\//si", DIRECTORY_SEPARATOR, $file);
                    }
                }
            }
            closedir($handle);
        }
        return $file_list;
    }

    public static function get7zFileList($directory) {
        $zfiles = array();
        $files = Tools::getFileList($directory);
        foreach ($files as $file) {
            $fmt = new FileType($file);
            if ($fmt->getType() == 'compressed') {
                $zfiles[] = $file;
            }
        }
        return $zfiles;
    }

    public static function checkCompressedFile($file_name) {
        $test_result = array();
        $command = (Tools::useQuotes(BIN_7Z) . ' t -p"" ' . Tools::useQuotes($file_name));
        exec($command, $test_result);

        $test = 0;
        $result = array();
        $pattern = '/Everything is (?P<test>.*).*/';
        preg_match($pattern, implode("\n", $test_result), $result);

        if (!isset($result['test'])) {
            $test = -1;
            $pattern = '/Data Error in encrypted file\. Wrong (?P<test>.*)\?/';
            preg_match($pattern, implode("\n", $test_result), $result);

            if (!isset($result['test'])) {
                $test = -2;
            }
        }

        return $test;
    }

    public static function extractOfficeFile($file_name) {
        if (Tools::checkCompressedFile($file_name) == 0) {
            $command = BIN_7Z . ' x -p"" -o' . Tools::useQuotes($file_name . '.mof') . ' ' . Tools::useQuotes($file_name) . ' 2> trash';
            $result = array();
            exec($command, $result);
        } else {
            $format = 'Error: %s is locked';
            Tools::printError(sprintf($format, $file_name));
        }
    }

    public static function extractCompressFile($file_name_list, &$encrypted_matches, $recursive = false) {
        foreach ($file_name_list as $file_name) {
            $src_path_name = $file_name;
            $dst_path_name = $file_name . '.tmp';

            $chk_cprs_file = Tools::checkCompressedFile($file_name);
            $rel_path_name = Tools::getRelativeFileName($file_name);

            if ($chk_cprs_file == 0) {
                $result = array();
                $command = BIN_7Z . ' x -p"" -o' . Tools::useQuotes($dst_path_name) . ' ' . Tools::useQuotes($src_path_name);
                exec($command, $result);
                unlink($src_path_name);
                rename($dst_path_name, $src_path_name);
                if ($recursive) {
                    $zfile_list = Tools::get7zFileList($src_path_name);
                    if (sizeof($zfile_list) > 0) {
                        Tools::extractCompressFile($zfile_list, $encrypted_matches, true);
                    }
                }
            } elseif ($chk_cprs_file == -1) {
                unlink($src_path_name);
                $encrypted_matches[] = array('000000000000000000000000', 'block', 'high', 'encrypted', $rel_path_name);
            }
        }
    }

    public static function printError($message) {
        $stderr = fopen('php://stderr', 'a');
        fwrite($stderr, $message . "\n");
        fclose($stderr);
    }

    public static function object_to_array($obj) {
        $arr = array();
        $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
        foreach ($_arr as $key => $val) {
            $val = (is_array($val) || is_object($val)) ? Tools::object_to_array($val) : $val;
            $arr[$key] = $val;
        }
        return $arr;
    }

}

class FileType {

    var $file_types = array(
        'application/msword' => 'office',
        'application/vnd.ms-powerpoint' => 'powerpoint',
        'application/vnd.ms-excel' => 'excel',
        'application/vnd.ms-office' => 'office',
        'application/pdf' => 'pdf',
        'application/x-7z-compressed' => 'compressed',
        'application/zip' => 'compressed',
        'application/x-tar' => 'compressed',
        'application/x-bzip2' => 'compressed',
        'application/x-gzip' => 'compressed',
        'application/octet-stream' => 'compressed',
        'application/x-iso9660-image' => 'compressed',
        'text/plain' => 'text',
        'text/troff' => 'text',
        'text/x-python' => 'text',
        'text/x-pascal' => 'text',
        'text/x-java' => 'text',
        'text/html' => 'text',
        'text/text' => 'text',
    );

    public function __construct($file_name) {
        $this->fileInfo = finfo_open(FILEINFO_MIME_TYPE, SHARE_DIR . 'misc' . DIRECTORY_SEPARATOR . 'magic.mgc');
        $this->srcFileName = $file_name;
        $this->mimeType = null;
        $this->extension = null;
        $this->type = null;
    }

    public function getMimeType() {
        if (file_exists($this->srcFileName)) {
            $this->mimeType = finfo_file($this->fileInfo, $this->srcFileName);
        } else {
            $format = 'Error: %s does not exists';
            Tools::printError(sprintf($format, $this->srcFileName));
        }
        return $this->mimeType;
    }

    public function getExtension() {
        $info = pathinfo($this->srcFileName);
        if (isset($info['extension'])) {
            $this->extension = $info['extension'];
        }
        return $this->extension;
    }

    public function getTypeByExtension() {
        switch ($this->getExtension()) {
            case 'doc':
                $this->type = 'word';
                break;
            case 'ppt':
                $this->type = 'powerpoint';
                break;
            case 'xls':
                $this->type = 'excel';
                break;
            case 'docx':
                $this->type = 'word+';
                break;
            case 'pptx':
                $this->type = 'powerpoint+';
                break;
            case 'xlsx':
                $this->type = 'excel+';
                break;
            default:
                $this->type = $this->extension;
                break;
        }
    }

    public function getType() {
        if (isset($this->file_types[$this->getMimetype()])) {
            $this->type = $this->file_types[$this->mimeType];
            switch ($this->type) {
                case 'office':
                    $this->getTypeByExtension();
                    break;
                case 'powerpoint':
                    $this->type = $this->getExtension() == 'pptx' ? 'powerpoint+' : 'powerpoint';
                    break;
                case 'excel':
                    $this->type = $this->getExtension() == 'xlsx' ? 'excel+' : 'excel';
                    break;
                case 'compressed':
                    if (OS_NAME == 'Linux') {
                        $this->getTypeByExtension();
                    }
                    break;
                default:
                    break;
            }
        } else {
            $this->getTypeByExtension();
        }

        return $this->type;
    }

    public function getInfo() {
        printf('FileName : %s%s', $this->srcFileName, "\n");
        printf('MimeType : %s%s', $this->mimeType, "\n");
        printf('Extension: %s%s', $this->extension, "\n");
        printf('FileType : %s%s', $this->type, "\n");
        /*
          syslog(LOG_DEBUG, 'FILETYPE INFO');
          syslog(LOG_DEBUG, 'FileName : ' . $this->srcFileName);
          syslog(LOG_DEBUG, 'MimeType : ' . $this->mimeType);
          syslog(LOG_DEBUG, 'Extension: ' . $this->extension);
          syslog(LOG_DEBUG, 'FileType : ' . $this->type);
         */
    }

}

class HashesFile {

    public function __construct($file_path) {
        $this->srcFilePath = $file_path;
    }

    public function getMd5() {
        $md5 = md5_file($this->srcFilePath);
        return $md5;
    }

    public function getSha1() {
        $sha1 = sha1_file($this->srcFilePath);
        return $sha1;
    }

    public function getSsDeep() {
        $result = array();
        $command = BIN_SSDEEP . ' -sb ' . Tools::useQuotes($this->srcFilePath);
        exec($command, $result);

        $ssdeep_result = array();
        $pattern = '/(?P<value>.*),.*/';
        preg_match_all($pattern, $result[1], $ssdeep_result);

        $ssdeep = $ssdeep_result['value'][0];

        return $ssdeep;
    }

    public function checkSsdeepFile($hashFilePath) {
        $results = array();
        $command = BIN_SSDEEP . ' -sbm ' . Tools::useQuotes($hashFilePath) . ' ' . Tools::useQuotes($this->srcFilePath);
        exec($command, $results);

        $ssdeep_results = array();
        $pattern = '/matches .*:(?P<file>.*) \((?P<value>\d+)\)/';
        foreach ($results as $result) {
            $ssdeep_result = array();
            preg_match_all($pattern, $result, $ssdeep_result);
            $ssdeep = array();
            $ssdeep['file'] = $ssdeep_result['file'][0];
            $ssdeep['value'] = $ssdeep_result['value'][0];
            $ssdeep_results[] = $ssdeep;
        }

        return $ssdeep_results;
    }

}

class TransformFileToTxt {

    public function __construct($file_name) {
        $this->srcFileName = $file_name;
        $this->txtFileName = $file_name . '.txt';
        $this->mofFileName = $file_name . '.mof';
    }

    public function getTxtFileName() {
        return $this->txtFileName;
    }

    public function getTextFromDOCX() {
        $file_path = $this->mofFileName . DIRECTORY_SEPARATOR . 'word' . DIRECTORY_SEPARATOR . 'document.xml';
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);

            $result = array();
            $pattern = '/(<w:t(\s.*)?>)(?P<text>.*)(<\/w:t>)/U';
            $out = preg_match_all($pattern, $content, $result);

            if ($out !== false) {
                file_put_contents($this->txtFileName, implode('', $result['text']));
            } else {
                $this->txtFileName = null;
            }
        } else {
            $format = 'Warning: %s doesnt exists';
            Tools::printError(sprintf($format, basename($file_path)));
            $this->txtFileName = null;
        }
    }

    public function getTextFromPPTX() {
        $slides_dir = $this->mofFileName . DIRECTORY_SEPARATOR . 'ppt' . DIRECTORY_SEPARATOR . 'slides';
        $notesSlides_dir = $this->mofFileName . DIRECTORY_SEPARATOR . 'ppt' . DIRECTORY_SEPARATOR . 'notesSlides';

        if (file_exists($slides_dir)) {
            $slides_dir_count = count(Tools::getFileList($slides_dir, false));
            for ($index = 1; $index <= $slides_dir_count; $index++) {
                $file_path = $slides_dir . DIRECTORY_SEPARATOR . 'slide' . $index . '.xml';
                $content = file_get_contents($file_path);

                $result = array();
                $pattern = '/(<a:t(\s.*)?>)(?P<text>.*)(<\/a:t>)/U';
                $out = preg_match_all($pattern, $content, $result);

                if ($out !== false) {
                    file_put_contents($this->txtFileName, implode(' ', $result['text']), FILE_APPEND);
                    file_put_contents($this->txtFileName, "\n", FILE_APPEND);
                }
            }
        } else {
            $format = 'Warning: %s doesnt exists';
            Tools::printError(sprintf($format, basename($slides_dir)));
            $this->txtFileName = null;
        }

        if (file_exists($notesSlides_dir)) {
            $notesSlides_dir_count = count(Tools::getFileList($notesSlides_dir, false));
            for ($index = 1; $index <= $notesSlides_dir_count; $index++) {
                $file_path = $notesSlides_dir . DIRECTORY_SEPARATOR . 'notesSlide' . $index . '.xml';
                $content = file_get_contents($file_path);

                $result = array();
                $pattern = '/(<a:t(\s.*)?>)(?P<text>.*)(<\/a:t>)/U';
                $out = preg_match_all($pattern, $content, $result);

                if ($out !== false) {
                    file_put_contents($this->txtFileName, implode(' ', $result['text']), FILE_APPEND);
                    file_put_contents($this->txtFileName, "\n", FILE_APPEND);
                }
            }
        }
    }

    public function getTextFromXLSX() {
        $worksheet_dir = $this->mofFileName . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets';
        $sharedStrings_file = $this->mofFileName . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml';

        $sharedStrings = array();
        if (file_exists($sharedStrings_file)) {
            $sharedStrings_xml = simplexml_load_file($sharedStrings_file);
            foreach ($sharedStrings_xml as $si) {
                $sharedStrings[] = trim((string) $si->t);
            }
            $line = implode(',', $sharedStrings) . "\n";
            file_put_contents($this->txtFileName, $line, FILE_APPEND);
        }

        if (file_exists($worksheet_dir)) {
            $worksheet_dir_count = count(Tools::getFileList($worksheet_dir, false));
            for ($index = 1; $index <= $worksheet_dir_count; $index++) {
                $file_path = $worksheet_dir . DIRECTORY_SEPARATOR . 'sheet' . $index . '.xml';
                $sheet = simplexml_load_file($file_path);
                $lines = array();
                foreach ($sheet->sheetData->row as $row) {
                    $line = array();
                    foreach ($row->c as $column) {
                        if (isset($column->v)) {
                            if (!isset($column->attributes()->t)) {
                                $line[] = trim((string) $column->v);
                            }
                        }
                    }
                    if (sizeof($line) > 0) {
                        $lines[] = implode(',', $line) . "\n";
                    }
                }
                file_put_contents($this->txtFileName, $lines, FILE_APPEND);
            }
        } else {
            $format = 'Warning: %s doesnt exists';
            Tools::printError(sprintf($format, basename($worksheet_dir)));
            $this->txtFileName = null;
        }
    }

    public function getTextFromFile() {
        $result = array();
        $return = null;

        $ft = new FileType($this->srcFileName);
        switch ($ft->getType()) {
            case 'word':
                $command = BIN_DOC2TXT . ' ' . Tools::useQuotes($this->srcFileName) . ' > ' . Tools::useQuotes($this->txtFileName) . ' 2> trash';
                exec($command, $result, $return);
                if ($return != 0) {
                    $this->txtFileName = null;
                }
                break;
            case 'powerpoint':
                $command = BIN_PPT2TXT . ' ' . Tools::useQuotes($this->srcFileName) . ' > ' . Tools::useQuotes($this->txtFileName) . ' 2> trash';
                exec($command, $result, $return);
                if ($return != 0) {
                    $this->txtFileName = null;
                }
                break;
            case 'excel':
                $command = BIN_XLS2TXT . ' ' . Tools::useQuotes($this->srcFileName) . ' > ' . Tools::useQuotes($this->txtFileName) . ' 2> trash';
                exec($command, $result, $return);
                if ($return != 0) {
                    $this->txtFileName = null;
                }
                break;
            case 'word+':
                Tools::extractOfficeFile($this->srcFileName);
                $this->getTextFromDOCX();
                break;
            case 'powerpoint+':
                Tools::extractOfficeFile($this->srcFileName);
                $this->getTextFromPPTX();
                break;
            case 'excel+':
                Tools::extractOfficeFile($this->srcFileName);
                $this->getTextFromXLSX();
                break;
            case 'pdf':
                $command = BIN_PDF2TXT . ' ' . Tools::useQuotes($this->srcFileName) . ' ' . Tools::useQuotes($this->txtFileName) . ' 2> trash';
                exec($command, $result, $return);
                if ($return != 0) {
                    $this->txtFileName = null;
                }
                break;
            case 'text':
                rename($this->srcFileName, $this->txtFileName);
                break;
            default:
                $this->txtFileName = null;
                break;
        }

        return $this->txtFileName;
    }

}

class AnalyzeFile {

    public static $action_values = array(null => 0, 'log' => 1, 'alert' => 2, "block" => 3);
    public static $severity_values = array(null => 0, 'low' => 1, 'medium' => 2, 'high' => 3);

    public function __construct($file_path, $md5sha1 = true, $ssdeep = true, $regex = true) {
        $this->hashFilePath = $file_path . '.hsh';
        $this->chkmd5sha1 = $md5sha1;
        $this->chkssdeep = $ssdeep;
        $this->chkregex = $regex;
        $this->md5sha1 = array();
        $this->ssdeeps = array();
        $this->regexes = array();
        $this->pattern = null;
        $this->action = 1;
        $this->severity = 1;
        $this->screenshotSeverity = null;
        $this->blockEncryptFiles = null;
        $this->returnValue = 0;
    }

    public function setCheckers($md5sha1, $ssdeep, $regex) {
        $this->chkmd5sha1 = $md5sha1;
        $this->chkssdeep = $ssdeep;
        $this->chkregex = $regex;
    }

    public function getRegexes() {
        $patterns = array();
        foreach ($this->jsonRules['subconcepts'] as $value) {
            $value['type'] = "subconcept";
            $this->regexes[] = $value;
            $patterns[] = '(?i)(?P<c' . $value['id'] . '>(\s\w+){0,20}(\s|\.)(?P<' . $value['id'] . '>' . $value['subconcept'] . ')(\s|\.)(\w+\s){0,4})';
        }

        foreach ($this->jsonRules['rules'] as $value) {
            $value['type'] = "rule";
            $this->regexes[] = $value;
            $patterns[] = '(?i)(?P<c' . $value['id'] . '>(\s\w+){0,20}(\s|\.)(?P<' . $value['id'] . '>' . $value['rule'] . ')(\s|\.)(\w+\s){0,4})';
        }

        $this->pattern = '/' . implode('|', $patterns) . '/i';
    }

    public function getMd5Sha1Files() {
        foreach ($this->jsonRules['files'] as $value) {
            $value['type'] = "file";
            $this->md5sha1[] = $value;
        }
    }

    public function getSsDeepFiles() {
        $fp = fopen($this->hashFilePath, 'w');
        fwrite($fp, 'ssdeep,1.1--blocksize:hash:hash,filename' . "\n");
        foreach ($this->jsonRules['files'] as $value) {
            fwrite($fp, $value['ssdeep'] . ',"' . $value['file'] . '"' . "\n");
            $value['type'] = "file";
            $this->ssdeeps[] = $value;
        }
        fclose($fp);
    }

    public function getBlockEncryptFiles() {
        $this->blockEncryptFiles = $this->jsonRules['block_encrypted'];
        return $this->blockEncryptFiles;
    }

    public function setAction($action, $severity) {
        if ($this->action < 3) {
            if (AnalyzeFile::$action_values[$action] > $this->action) {
                $this->action = AnalyzeFile::$action_values[$action];
                $this->severity = AnalyzeFile::$severity_values[$severity];
            } elseif (AnalyzeFile::$action_values[$action] == $this->action) {
                if (AnalyzeFile::$severity_values[$severity] > $this->severity) {
                    $this->severity = AnalyzeFile::$severity_values[$severity];
                }
            }
        } elseif ($this->severity < 3) {
            if (AnalyzeFile::$severity_values[$severity] > $this->severity) {
                $this->severity = AnalyzeFile::$severity_values[$severity];
            }
        }
    }

    public function checkMd5Sha1() {
        $hf = new HashesFile($this->fileName);
        $md5FileValue = $hf->getMd5();
        $sha1FileValue = $hf->getSha1();
        foreach ($this->md5sha1 as $md5sha1) {
            if ($md5FileValue == $md5sha1['md5']) {
                if ($sha1FileValue == $md5sha1['sha1']) {
                    $this->analyzeMatch[] = array($md5sha1['id'], $md5sha1['action'], $md5sha1['severity'], $md5sha1['policies_id'], 'Md5 & Sha1', $md5sha1['type']);
                    $this->setAction($md5sha1['action'], $md5sha1['severity']);
                    $this->chkssdeep = false;
                }
            }
        }
    }

    public function transformFileTxt() {
        if ($this->chkssdeep || $this->chkregex) {
            $ftxt = new TransformFileToTxt($this->fileName);
            $this->txtFileName = $ftxt->getTextFromFile();
        }
    }

    public function checkSsdeep() {
        if ($this->chkssdeep) {
            if (isset($this->txtFileName)) {
                $hf = new HashesFile($this->txtFileName);
                $ssdeepMatches = $hf->checkSsdeepFile($this->hashFilePath);
                foreach ($ssdeepMatches as $ssdeepMatch) {
                    if ((int) $ssdeepMatch['value'] > 50) {
                        $ssdeep_match = $ssdeepMatch['value'] . '% similar';
                        foreach ($this->ssdeeps as $ssdeep) {
                            if ($ssdeep[1] == $ssdeepMatch['file']) {
                                $this->analyzeMatch[] = array($ssdeep['id'], $ssdeep['action'], $ssdeep['severity'], $ssdeep['policies_id'], $ssdeep_match, $ssdeep['type']);
                                $this->setAction($ssdeep['action'], $ssdeep['severity']);
                                $this->chkregex = false;
                            }
                        }
                    }
                }
            } else {
                $format = 'Warning: %s can not be analyzed by ssdeep';
                Tools::printError(sprintf($format, $this->relativePath));
            }
        }
    }

    public function checkRegexes() {
        if ($this->chkregex) {
            if (isset($this->txtFileName)) {
                $content = Tools::readFile($this->txtFileName);
                $results = array();
                $out = @preg_match_all($this->pattern, $content, $results);
                if ($out !== false) {
                    foreach ($this->regexes as $regex) {

                        $matches_list = array_filter($results[$regex['id']]);
                        $context_list = array_filter($results['c' . $regex['id']]);

                        $matches = array();
                        foreach ($matches_list as $key => $value) {
                            $matches[] = array(
                                'match' => trim($value),
                                'context' => trim($context_list[$key]),
                            );
                        }

                        if (sizeof($matches) > 0) {
                            if (isset($regex['verify'])) {
                                $matches = $this->checkVerify($regex['verify'], $matches);
                            }
                            if (sizeof($matches) > 0) {
                                $this->analyzeMatch[] = array($regex['id'], $regex['action'], $regex['severity'], $regex['policies_id'], $matches, $regex['type']);
                                $this->setAction($regex['action'], $regex['severity']);
                            }
                        }
                    }
                }
            } else {
                $format = 'Warning: %s can not be analyzed by regexes';
                Tools::printError(sprintf($format, $this->relativePath));
            }
        }
    }

    public function checkVerify($verify, $matches) {
        $code = base64_decode($verify);
        $new_list = array();
        foreach ($matches as $value) {
            $return_val = false;
            //$match is used on $code
            $match = $value['match'];
            eval($code);
            //$return_val is boolean used on $code
            if (is_bool($return_val) and $return_val) {
                $new_list[] = $value;
            }
        }
        return $new_list;
    }

    public function loadPolicy($policy) {
        $this->jsonRules = Tools::object_to_array(json_decode($policy));
        $this->getMd5Sha1Files();
        $this->getSsDeepFiles();
        $this->getRegexes();
        $this->blockEncryptFiles = 0;
        $this->screenshotSeverity = $this->jsonRules['screenshot_severity'];
    }

    public function makeAnalysis($file_path) {
        $this->fileName = $file_path;
        $this->txtFileName = null;
        $this->relativePath = Tools::getRelativeFileName($file_path);
        $this->analyzeMatch = array();

        $this->checkMd5Sha1();
        $this->transformFileTxt();
        $this->checkSsdeep();
        $this->checkRegexes();
    }

    public function getMatches() {
        if (sizeof($this->analyzeMatch) > 0) {

            $file_coincidence = array();
            $file_coincidence["FileName"] = $this->relativePath;
            $file_coincidence["Coincidences"] = array();

            foreach ($this->analyzeMatch as $match) {
                $coincidence = array(
                    "Id" => (string) $match[0],
                    "Action" => $match[1],
                    "Severity" => $match[2],
                    "Policies" => $match[3],
                    "Matches" => $match[4],
                    "Type" => $match[5]
                );
                $file_coincidence["Coincidences"][] = $coincidence;
            }
            return $file_coincidence;
        }
    }

    public function getReturnValue() {
        $severities = array("none" => 4, "low" => 1, "medium" => 2, "high" => 3);
        if (!$this->blockEncryptFiles) {
            if ($this->action != 3) {
                if ($this->severity < $severities[$this->screenshotSeverity]) {
                    $this->returnValue = 1;
                } else {
                    $this->returnValue = 2;
                }
            } else {
                if ($this->severity < $severities[$this->screenshotSeverity]) {
                    $this->returnValue = 3;
                } else {
                    $this->returnValue = 4;
                }
            }
        } else {
            $this->returnValue = $severities[$this->screenshotSeverity] == 4 ? 3 : 4;
        }

        return $this->returnValue;
    }

    public static function getEncryptedMatches($encryted_matches) {
        $final_result = array();

        foreach ($encryted_matches as $encrypted_match) {
            $file_coincidence = array();
            $file_coincidence["FileName"] = $encrypted_match[4];
            $file_coincidence["Coincidences"] = array();

            $coincidence = array(
                'Id' => (string) $encrypted_match[0],
                'Action' => $encrypted_match[1],
                'Severity' => $encrypted_match[2],
                'Policies' => array('Advanced'),
                'Matches' => $encrypted_match[3],
                'Type' => 'encrypted'
            );
            $file_coincidence['Coincidences'][] = $coincidence;
            $final_result[] = $file_coincidence;
        }

        return $final_result;
    }

}

function generateSsdeep($path_name) {
    $tmp_dir = Tools::createTempBackUp($path_name);
    $tmp_file = $tmp_dir . DIRECTORY_SEPARATOR . basename($path_name);
    $ftxt = new TransformFileToTxt($tmp_file);
    $txt_file_name = $ftxt->getTextFromFile();
    $ssdeep = null;
    if (isset($txt_file_name)) {
        $hf = new HashesFile($txt_file_name);
        $ssdeep = $hf->getSsdeep();
    }
    Tools::removeTempBackUp($tmp_dir);
    return $ssdeep;
}

function analyzeFile($src_file, $policy, $origin, $application = null) {
    $return = 0;
    if (file_exists($src_file)) {
        $tmp_dir = Tools::createTempBackUp($src_file);
        $path_name = $tmp_dir . DIRECTORY_SEPARATOR . basename($src_file);

        $fmt = new FileType($path_name);
        $file_type = $fmt->getType();

        $final_result = array();

        $final_result["Source"] = $origin;
        if (isset($application)) {
            $final_result["Application"] = json_decode($application);
        }
        $final_result["Results"] = array();

        if ($file_type == 'compressed') {
            $af = new AnalyzeFile($path_name, true, false, false);
            $af->loadPolicy($policy);
            $af->makeAnalysis($path_name);

            if ($af->getMatches()) {
                $final_result['Results'][] = $af->getMatches();
            } else {
                $encrypted_matches = array();
                Tools::extractCompressFile(array($path_name), $encrypted_matches, true);

                if (sizeof($encrypted_matches) == 0) {
                    foreach (Tools::getFileList($path_name) as $path_file) {
                        $af->setCheckers(true, true, true);
                        $af->makeAnalysis($path_file);
                        if ($af->getMatches()) {
                            $final_result['Results'][] = $af->getMatches();
                        }
                    }
                } else {
                    $encrypted_opcion = $af->getBlockEncryptFiles();
                    if ($encrypted_opcion) {
                        $final_result['Results'] = AnalyzeFile::getEncryptedMatches($encrypted_matches);
                    } else {
                        foreach (Tools::getFileList($path_name) as $path_file) {
                            $af->setCheckers(true, true, true);
                            $af->makeAnalysis($path_file);
                            if ($af->getMatches()) {
                                $final_result['Results'][] = $af->getMatches();
                            }
                        }
                    }
                }
            }
        } else {
            $af = new AnalyzeFile($path_name);
            $af->loadPolicy($policy);
            $af->makeAnalysis($path_name);
            if ($af->getMatches()) {
                $final_result['Results'][] = $af->getMatches();
            }
        }

        $return = $af->getReturnValue();

        if (sizeof($final_result['Results']) == 0) {
            $final_result['Results'] = 'Clean';
            $return = 0;
        }

        //Tools::removeTempBackUp($tmp_dir);

        printf('%s', json_encode($final_result));
    } else {
        $format = 'Error: %s doesnt exists';
        Tools::printError(sprintf($format, $src_file));
    }
    return $return;
}

function helpMessage() {
    $help_message = '
            Usage: 
                    analyze_file.py --options
            Options:
                    --ssdeep: Generate ssdeep value of File
                            > analyze_file.py --ssdeep BinaryDir FilePath
                    --analyze: Analyze File with Json Rules 
                            > analyze_file.py --analyze BinaryDir FilePath JsonRules
            ';
    printf('%s', $help_message);
}

function main($argc, $argv) {

    if ($argc == 4) {
        //analyze_file.py --ssdeep FilePath 
        $option = $argv[1];
        $src_file = $argv[3];
        if ($option == '--ssdeep') {
            generateSsdeep($src_file);
        } else {
            helpMessage();
        }
        $return_value = 0;
    } elseif ($argc == 5) {
        //analyze_file.py --analyze FilePath JsonRulesPath
        $option = $argv[1];
        $src_file = $argv[3];
        $policy_file = $argv[4];

        if ($option == '--analyze') {
            $return_value = analyzeFile($src_file, $policy_file);
        } else {
            helpMessage();
            $return_value = 0;
        }
    } else {
        helpMessage();
        $return_value = 0;
    }
    exit($return_value);
    return 0;
}

/*
$src_file = $argv[1];
$policy_file = 'C:\drainware\json.txt';
$policy = file_get_contents($policy_file);
$origin = "Filter";
$app = "Application xxxx";
//generateSsdeep($src_file);
echo "\nRETURN: " . analyzeFile($src_file, $policy, $origin, $app);
*/

?>