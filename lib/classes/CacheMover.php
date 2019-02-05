<?php

namespace WebPExpress;

include_once "FileHelper.php";
use \WebPExpress\FileHelper;

include_once "Paths.php";
use \WebPExpress\Paths;

class CacheMover
{

    public static function getUploadFolder($destinationFolder)
    {
        switch ($destinationFolder) {
            case 'mingled':
                return Paths::getUploadDirAbs();
            case 'separate':
                return Paths::getCacheDirAbs() . '/doc-root/' . Paths::getUploadDirRel();
        }
    }

    /**
     *  Sets permission, uid and gid of all subfolders/files of a dir to same as the dir
     */
    public static function chmodFixSubDirs($dir)
    {
        $perm = FileHelper::filePermWithFallback($dir, 0775);
        $stat = @stat($dir);
        $uid = null;
        $gid = null;
        if ($stat !== false) {
            if (isset($stat['uid'])) {
                $uid = $stat['uid'];
            }
            if (isset($stat['gid'])) {
                $uid = $stat['gid'];
            }
        }
        FileHelper::chmod_r($dir, $perm, $uid, $gid);
    }

    /**
     *  Move cache because of change in options.
     *  Only move the upload folder
     *  Only move those that has an original
     *  Only move those that can be moved.
     *  @return [$numFilesMoved, $numFilesFailedMoving]
     */
    public static function move($newConfig, $oldConfig)
    {
        $fromDir = self::getUploadFolder($oldConfig['destination-folder']);
        $fromExt = $oldConfig['destination-extension'];

        $toDir = self::getUploadFolder($newConfig['destination-folder']);
        $toExt = $newConfig['destination-extension'];

        $srcDir = self::getUploadFolder('mingled');

        // for testing!
        /*
        $fromDir = self::getUploadFolder('mingled');    // separate | mingled
        $toDir = self::getUploadFolder('mingled');
        $fromExt = 'set';       // set | append
        $toExt = 'append';

        echo '<pre>';
        echo 'from: ' . $fromDir . '<br>';
        echo 'to: ' . $toDir . '<br>';
        echo 'ext:' . $fromExt . ' => ' . $toExt . '<br>';
        echo '</pre>';*/


        $result = self::moveRecursively($fromDir, $toDir, $srcDir, $fromExt, $toExt);
        self::chmodFixSubDirs($toDir);

        return $result;
        //self::moveRecursively($toDir, $fromDir, $srcDir, $fromExt, $toExt);
    }

    /**
     *  @return [$numFilesMoved, $numFilesFailedMoving]
     */
    public static function moveRecursively($fromDir, $toDir, $srcDir, $fromExt, $toExt)
    {
        if (!@is_dir($fromDir)) {
            return [0, 0];
        }
        if (!@file_exists($toDir)) {
            if (!@mkdir($toDir)) {
                return [0, 0];
            }
        }

        $numFilesMoved = 0;
        $numFilesFailedMoving = 0;

        //$filenames = @scandir($fromDir);
        $fileIterator = new \FilesystemIterator($fromDir);

        //foreach ($filenames as $filename) {
        while ($fileIterator->valid()) {
            $filename = $fileIterator->getFilename();

            if (($filename != ".") && ($filename != "..")) {
                //$filePerm = FileHelper::filePermWithFallback($filename, 0777);

                if (@is_dir($fromDir . "/" . $filename)) {
                    list($r1, $r2) = self::moveRecursively($fromDir . "/" . $filename, $toDir . "/" . $filename, $srcDir . "/" . $filename, $fromExt, $toExt);
                    $numFilesMoved += $r1;
                    $numFilesFailedMoving += $r2;

                    // Remove dir, if its empty. But do not remove dirs in srcDir
                    if ($fromDir != $srcDir) {
                        $fileIterator2 = new \FilesystemIterator($fromDir . "/" . $filename);
                        $dirEmpty = !$fileIterator2->valid();
                        if ($dirEmpty) {
                            @rmdir($fromDir . "/" . $filename);
                        }
                    }
                } else {
                    // its a file.
                    // check if its a webp
                    if (strpos($filename, '.webp', strlen($filename) - 5) !== false) {

                        $filenameWithoutWebp = substr($filename, 0, strlen($filename) - 5);
                        $srcFilePathWithoutWebp = $srcDir . "/" . $filenameWithoutWebp;

                        // check if a corresponding source file exists
                        $newFilename = null;
                        if (($fromExt == 'append') && (@file_exists($srcFilePathWithoutWebp))) {
                            if ($toExt == 'append') {
                                $newFilename = $filename;
                            } else {
                                // remove ".jpg" part of filename (or ".png")
                                $newFilename = preg_replace("/\.(jpe?g|png)\.webp$/", '.webp', $filename);
                            }
                        } elseif ($fromExt == 'set') {
                            if ($toExt == 'set') {
                                if (
                                    @file_exists($srcFilePathWithoutWebp . ".jpg") ||
                                    @file_exists($srcFilePathWithoutWebp . ".jpeg") ||
                                    @file_exists($srcFilePathWithoutWebp . ".png")
                                ) {
                                    $newFilename = $filename;
                                }
                            } else {
                                // append
                                if (@file_exists($srcFilePathWithoutWebp . ".jpg")) {
                                    $newFilename = $filenameWithoutWebp . ".jpg.webp";
                                } elseif (@file_exists($srcFilePathWithoutWebp . ".jpeg")) {
                                    $newFilename = $filenameWithoutWebp . ".jpeg.webp";
                                } elseif (@file_exists($srcFilePathWithoutWebp . ".png")) {
                                    $newFilename = $filenameWithoutWebp . ".png.webp";
                                }
                            }
                        }

                        if ($newFilename !== null) {
                            //echo 'moving to: ' . $toDir . '/' .$newFilename . "<br>";
                            $toFilename = $toDir . "/" . $newFilename;
                            if (@rename($fromDir . "/" . $filename, $toFilename)) {
                                $numFilesMoved++;
                            } else {
                                $numFilesFailedMoving++;
                            }
                        }
                    }
                }
            }
            $fileIterator->next();
        }
        return [$numFilesMoved, $numFilesFailedMoving];
    }

}
