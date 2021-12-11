<?php

function download($filePath) {
    
    if (is_dir($filePath)) {
        
        // debugLog('Cannot download directory at '.$filePath);
        throwException(400);
        
    } if (!is_file($filePath)) {
        
        // debugLog('Cannot download missing file at '.$filePath);
        throwException(404);

    } if (!is_readable($filePath)) {
        
        // debugLog('Cannot download unreadable file at '.$filePath);
        throwException(403);
    
    } else if (!($fp = fopen($filePath, 'rb'))) {
         
        // debugLog('Could not open file for download at '.$filePath);   
        throwException(403);
            
    } else {
        
        // Get the size for this file
        $size = realFileSize($filePath);
        
        // Initialize variables
        $length = $size;
        $start = 0.0;
        $end = $size - 1.0;
    
        sendHeader("Accept-Ranges: bytes");
        sendHeader('Content-Type: '.mime_content_type($filePath));    
        
        // Manipulate file name
        // header('Content-Disposition: inline; filename="asdf"');
        
        if (isset($_SERVER['HTTP_RANGE'])) {
        
            // Initialize variables
            $maxIntegerAsFloat = floatval(PHP_INT_MAX);
            $chunkStart = $start;
            $chunkEnd   = $end;
            
            // Extract the range string
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            
            // // debugLog('Specified range: '.$range);
            
            // Make sure the client hasn't sent us a multibyte range
            if (strpos($range, ',') !== false) {
    
                // debugLog('Warning! Multibyte range requested: '.$_SERVER['HTTP_RANGE']);
                sendHeader('HTTP/1.1 416 Requested Range Not Satisfiable');
                sendHeader("Content-Range: bytes $start-$end/$size");
                throwException(416);
                
            }
                
            if (startsWith($range,'-')) {
                
                // The n-number of the last bytes is requested
                $chunkStart = $size - floatval(substr($range, 1));
    
            } else {
            
                $range  = explode('-', $range);
                $chunkStart = floatval($range[0]);
                $chunkEnd   = (isset($range[1]) && is_numeric($range[1])) ? floatval($range[1]) : $size;
                
            }
            
            // End bytes cannot be larger than $end.
            if ($chunkEnd > $end) {
                $chunkEnd = $end;
            } else {
                $end = $chunkEnd;
            }
            
            // Validate the requested range and return an error if it's not correct.
            if ($chunkStart > $chunkEnd || $chunkStart > $size - 1.0 || $chunkEnd >= $size) {
            
                // debugLog('Warning! Invalid range requested: '.$_SERVER['HTTP_RANGE']);
                sendHeader('HTTP/1.1 416 Requested Range Not Satisfiable');
                sendHeader("Content-Range: bytes $start-$end/$size");
                throwException(416);
                
            }
            
            $start  = $chunkStart;
            $end    = $chunkEnd;
            $length = $end - $start + 1.0; 
            
            if ($start <= $maxIntegerAsFloat) {
                
                fseek($fp, intval($start), SEEK_SET);
    
                $fileStart = $start;

            } else {
                
                // Seek as far as PHP's maximum integer will carry us
                fseek($fp, PHP_INT_MAX, SEEK_SET);

                $buffer = floatval(1024 * 1024);
                $position = $maxIntegerAsFloat;

                // Advance file pointer to start by actually reading the file                
                while(!feof($fp) && $position < $start) {
        
                    if ($position + $buffer > $start) {
                        $buffer = $start - $position;
                    }
            
                    if (fread($fp, intval($buffer)) === false) {
                        
                        // debugLog('Warning! Failed to read file at '.var_export($position, true).' to advance position to start position at '.var_export($start, true).' with a buffer size of '.var_export($buffer, true));
                        
                        throwException(500);
                        
                    }
                    
                    $position += $buffer;

                }
                
                $fileStart = $position;
                
            }
            
            sendHeader('HTTP/1.1 206 Partial Content');
            
            // debugLog('Ranged download for '.$filePath.' with size: '.fileSizeDisplay($size));
            
        } else {
            
            fseek($fp, 0, SEEK_SET); 
            
            $fileStart = 0.0;
            
            // debugLog('Download for '.$filePath.' with size: '.fileSizeDisplay($size));
            
        }
        
        // debugLog('File position should now be '.var_export($fileStart, true));
    
        // End content headers
        sendHeader("Content-Range: bytes $start-$end/$size");
        sendHeader("Content-Length: $length");
     
        if (debugMode()) {
            // debugLog('Download start: '.var_export($start, true).', end: '.var_export($end, true).', length: '.var_export($length, true)); 
            $startTime = microtime(true);
        }

        $buffer = floatval(1024 * 8);    	
        $position = $fileStart;

        // Start buffered download
        while(!feof($fp) && $position <= $end) {
        
            // // debugLog('Reaching end position ('.var_export($end, true).'): '.var_export($position, true));
            
            if ($position + $buffer > $end) {
                $buffer = $end - $position + 1.0;
            }
            set_time_limit(0); // Reset time limit for big files
            
            $data = fread($fp, intval($buffer));
            
            if ($data === false) {
                
                // debugLog('Failed to read data size '.var_export($buffer, true).' at position '.var_export($position, true));
                scriptExit(1);
                
            }

            echo $data;
            flush();
            
            $position += $buffer;
            
        }
     
        fclose($fp);
        
        if (debugMode()) {
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            // debugLog('Transfer done after '.round($duration * 1000, 4).' ms');	
        }

    
    }
                  
}


//! HTML

function composeHTMLListing($list, $requestedPath, $resourcePath) {

    // Compose an HTML list from the specified directory listing
    
    $listing = '';
          
    $dirName = basename($requestedPath);
    $parentPath = dirname($resourcePath);

    if (!endsWith($parentPath, DIRECTORY_SEPARATOR)) {
        $parentPath .= DIRECTORY_SEPARATOR;
    }
    
    if ($requestedPath != '/') {
        
        // Generate script and link for back button
        $backScript = "window.location.href = '".pathToURL($parentPath)."'";
        $backLink = composeHTMLLink(pathToURL($parentPath), 'Back', 'directory-item back-link', 'Go up a directory');

        // Add back button
        $listing .= composeHTMLListButton(0, 'back-button', $backScript, '..', '', $backLink);
        
        $itemNumber = 1;
        
    } else {
        
        $itemNumber = 0;
        
    }
    
    if ($list->count == 0) {
        
        $listing .= composeHTMLListButton($itemNumber, 'directory-item directory-empty', '', '', '', 'No browsable items found');
        
    } else {
        
        foreach ($list->files as $file) {
            
            // Determine icon
            if ($file->isDirectory && empty($file->suffix)) {
                
                $icon = '/';
                
            } else {
                
                $icon = $file->suffix;
                
                if (empty($icon)) {
                    $icon = '•';
                } else if (strlen($icon) > 5) {
                    $icon = substr($icon, 0, 5);
                }
            }
            
            // Determine number and title
            if ($file->seasonEpisode !== false) {
    
                $number = $file->seasonEpisode;
                $title = $file->displayedNameWithoutPrefix;
                
            } else if ($file->date !== false) {
    
                $number = $file->date->format('Y-m-d');
                $title = $file->displayedNameWithoutPrefix;
    
            } else if ($file->number !== false) {
                
                $number = $file->number;
                $title = $file->displayedNameWithoutPrefix;
                
            } else {
                
                $number = '';                        
                $title = $file->displayedName;
     
            }
            
            // Determine tooltip
            $tooltip = $file->name;
            if (!$file->isDirectory && $file->sizeDisplay !== false) {
                $tooltip .= ' ('.$file->sizeDisplay.')';    
            }
            

            $youtubeLink = '';
/*
            if ($list->suffixedWithVideoId && $file->videoId !== false) {
                // Add YouTube link
                $youtubeLink = composeHTMLLink("https://www.youtube.com/watch?v={$file->videoId}", "Y", 'youtube', "https://www.youtube.com/watch?v={$file->videoId}", "_blank");
            }
*/
            
            $script = "window.location.href = '{$file->url}'";
            $link = composeHTMLLink($file->url, $title, 'directory-item'.($file->isDirectory ? ' directory' : ''), $tooltip);
            
            $listing .= composeHTMLListButton($itemNumber, ($file->isDirectory ? 'directory' : 'file'), $script, $icon, $number, $link.$youtubeLink);
                   
            $itemNumber++;
            
        }
        
    }
    
    if ($requestedPath != '/') {     
        $listing .= composeHTMLListButton($itemNumber, 'back-button', $backScript, '..', '', $backLink);
    }
    
    $body = '';
    
    if (!isPlatform('ios')) {
        
        $body .= "\n\t\t<div id=\"container\">";
        
    }

    
    $body .= "\n\t\t<div id=\"sidebar\" class=\"".platform()."\">";
    
    if ($requestedPath != '/') { 
        $body .= "\n\t\t\t".'<h1>'.$dirName.'</h1>';
    } else {
        $body .= "\n\t\t\t".'<h1>'.ROOT_TITLE.'</h1>';
    }
    
    $body .=  "\n\t\t\t".'<ul>';
    
    $body .= $listing;
    
    
    
    $body .= "\n\t\t\t".'</ul>';

    $body .= "\n\t\t</div>";

    if (!isPlatform('ios')) {
                
        $body .= "\n\t\t<div id=\"viewer\">";
        
        $body .= "<video id=\"video\" width=\"320\" height=\"240\" controls>";
        
        // $body .= "<source src=\"\">";
    
        $body .= "</video>";
    
        $body .= "\n\t\t</div>";
        
        $body .= "\n\t\t</div>";

    }
    
    return composeHTMLPage($requestedPath, $body);
    
}

function composeHTMLListButton($id, $class, $script, $icon, $number, $title) {
  
    if (empty($number)) {
        
        $class .= ' without-number';
        
    } else {
        
        $class .= ' with-number';
            
    }
    
    $class = trim($class);
    
    $html = '';
    
    $html .= "\n\t\t\t\t<li class=\"{$class}\" id=\"{$id}\" onclick=\"selectListItem(this, false, event)\">";
    $html .= "\n\t\t\t\t\t<div class=\"list-button\">";
    
    $html .= "\n\t\t\t\t\t\t<div class=\"icon\">{$icon}</div>";
    $html .= "\n\t\t\t\t\t\t<div class=\"clear\"></div>";
    if (!empty($number)) {
        $html .= "\n\t\t\t\t\t\t<div class=\"number\">{$number}</div>";        
    }
    $html .= "\n\t\t\t\t\t\t<div class=\"title\">{$title}</div>";

    
    $html .= "\n\t\t\t\t\t</div>";
    $html .= "\n\t\t\t\t</li>";   

    return $html;
    
}


function composeHTMLLink($url, $title, $class = '', $tooltip = '', $target = '') {
    
    $anchor = '';
    $anchor .= "<a class=\"{$class}\" href=\"{$url}\" title=\"{$tooltip}\"";
    if (!empty($target)) {
        $anchor .= " target=\"{$target}\"";
    }
    $anchor .= ">{$title}</a>";
    
    return $anchor;
            
}

function composeHTMLPage($title, $body, $headExtras = '') {
    
    // debugLog('Composing HTML page with title '.var_export($title, true));
    
    if (debugMode()) {
        $cacheBuster = '?v='.uniqid();
    } else {
        $cacheBuster = '';        
    }
    
    if ($title == '/') {
        $title = ROOT_TITLE;
    } else {
        $title = ROOT_TITLE.$title;
    }
    
    $html = '';
    $html .= '<!DOCTYPE html>
    
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <link rel="apple-touch-icon" href="icon.png'.$cacheBuster.'">
        <title>'.$title.'</title>
        <link rel="stylesheet" type="text/css" href="/styles.css'.$cacheBuster.'">
        <script src="/script.js'.$cacheBuster.'"></script>
        '.$headExtras.'
    </head>
    <body>';

    $html .= $body;

    $html .= '</body>
</html>';

    return $html;

}

//! DIRECTORY LISTING

function allowFileName($fileName) {
    
    if (!startsWith($fileName, '.') && 
        !endsWith($fileName, '.part') &&
        !endsWith($fileName, '.ytdl')
    ) {
        return true;
    } else {
        return false;
    }
     
}

function listDirectory($directoryPath) {

    // Make sure directory path end with a separator
    if (!endsWith($directoryPath, DIRECTORY_SEPARATOR)) {
        $directoryPath .= DIRECTORY_SEPARATOR;
    }
            
    // debugLog('Listing directory at '.$directoryPath);
    
    if (!is_dir($directoryPath)) {
        
        // debugLog('Could not open directory at '.$directoryPath);   
        return false;
    
    } else if (!is_readable($directoryPath)) {
        
        // debugLog('Directory not readable at '.$directoryPath);   
        return false;

    } else {
        
        if (!$handle = opendir($directoryPath)) {

            // debugLog('Could not open directory at '.$directoryPath);
            return false;
                    
        } else {
            
            // Initialize variables
            $allPrefixedWithNumber = true;
            $allPrefixedWithDate = true;
            $allSuffixedWithVideoId = true;
            $onlyFiles = true;
            $onlyDirectories = true;
            $dirName = basename($directoryPath);
            $fileNames = [];
            $files = [];
            
            while (false !== ($item = readdir($handle))) {
        
                $fullPath = $directoryPath.$item;
                $isFile = is_file($fullPath);
                $isDirectory = is_dir($fullPath);
                                    
                if (!$isFile && !$isDirectory) {
                    
                    // debugLog('Excluding '.$item.' because it does not exist');
                    
                } else if (!allowFileName($item)) {
                    
                    // debugLog('Excluding '.$item.' based on file name');
                
                } else if (!SKIP_CHECKING_FILE_SIZES && $isFile && (($size = realFileSize($fullPath)) === false || $size <= 0)) {
                    
                    // debugLog('Excluding '.$item.' because file size is too low');
                    
                } else if ($isDirectory && directoryItemCount($fullPath) == 0) {
                    
                    // debugLog('Excluding '.$item.' because it is an empty directory');
                    
                } else {
                    
                    // debugLog('Including '.$item);
                    
                    // Find out whether this directory contains only files or only directories
                    if ($onlyFiles && $isDirectory) {
                        $onlyFiles = false;
                    }
                    if ($onlyDirectories && !$isDirectory) {
                        $onlyDirectories = false;
                    }

                    // Generate displayed from file name without suffix
                    $displayedName = trim(pathinfo($item, PATHINFO_FILENAME));

                    // Get the suffix
                    $suffix = pathinfo($item, PATHINFO_EXTENSION);
                    
                    // youtube-dl downloads include the YouTube video identifier at the end
                    // The best we can do is analyze the string that comes after the last dash
                    // FIXME: Identifiers can contain dashes themselves
                    
                    $videoId = false;
                    $dashOffset = strrpos($displayedName, '-');
                    
                    if ($dashOffset !== false && $dashOffset > 0 && substr($displayedName, $dashOffset -1, 1) != ' ') {
                        
                        // A dash was found, is not the first character and is not preceded by a space
                        
                        if (($videoId = substr($displayedName, $dashOffset + 1)) !== false) {
                            
                            // Successfully got the string that comes after the last dash

                            // Identifiers should not contains spaces                            
                            if (strpos($videoId, ' ') === false) {
                                
                                // debugLog('Found possible video id: '.$videoId);
                                
                                // Remove the identifier from the displayed name
                                $displayedName = substr($displayedName, 0, (strlen($videoId) + 1) * -1);
                                
                            } else {
                                
                                // Invalidate identifier as it contains a space
                                $videoId = false;
                            }
                        }
                    }
                    
                    // Try to remove directory name from start of file
                    
                    if (startsWith($displayedName, $dirName.' - ')) {
                        $displayedName = trim(substr($displayedName, strlen($dirName.' - ')));
                    } else if (startsWith($displayedName, $dirName)) {
                        $displayedName = trim(substr($displayedName, strlen($dirName)));
                    }
                    
                    // Replace single quotes with apostrophe
                    $displayedName = str_replace("'", "’", $displayedName);
                    
                    // Replace underscores
                    $displayedName = str_replace('_', ' ', $displayedName);
                    
                    // Initialize prefix variables
                    $number = false;
                    $date = false;
                    $episode = false;
                    $season = false;
                    $seasonEpisode = false;
                    $prefixedWithNumber = false;                    
                    $prefixedWithDate = false;
                    $prefixedWithEpisode = false;
                    $displayedNameWithoutPrefix = false;
                    
                    // Delimit name by space character
                    $arr = explode(' ', $displayedName);
                    
                    // Check that there are actually multiple name components
                    if (count($arr) > 1) {

                        // Try to find a number prefix
                        if (($prefixedWithNumber = ctype_digit($arr[0])) !== false) {
                            
                            $rawPrefix = $arr[0];
                            $number = intval($arr[0]);
                            
                        } else {
                                                        
                            // Try to find episode prefix
                            if (startsWith($arr[0], 'S') && strpos($arr[0], 'E') !== false) {
    
                                $split = preg_split( "/(S|E)/", $arr[0]);
                                
                                if (count($split) == 3) {
                                    
                                    $prefixedWithEpisode = true;
                                    $rawPrefix = $arr[0];
                                    $season = intval($split[1]);
                                    $episode = intval($split[2]);
                                    $seasonEpisode = 'Season '.$season.' - Episode '.$episode;
    
                                }
                                
                            }
                            
                            if (!$prefixedWithEpisode) {
                                
                                // Try to find date prefix
                                if (preg_match("/^(([0-9]{4})-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])).*/", $displayedName, $matches)) {
                                    $prefixedWithDate = true;
                                    $rawPrefix = $matches[1];
                                    $date = (new DateTime())->setDate(
                                        intval($matches[2]), 
                                        intval($matches[3]), 
                                        intval($matches[4])
                                    );
                                }
                                    
                            }
                            
                        }
                        
                        // Remove prefix from displayed name
                        if ($prefixedWithNumber || $prefixedWithEpisode || $prefixedWithDate) {
                            $displayedNameWithoutPrefix = trim(substr($displayedName, strlen($rawPrefix)));
                            
                            // Try to remove more extraneous characters
                            if (startsWith($displayedNameWithoutPrefix, '- ')) {
                                $displayedNameWithoutPrefix = trim(substr($displayedNameWithoutPrefix, 1));
                            }
                        }
                        
                        // Replace underscores
                        $displayedNameWithoutPrefix = str_replace('_', ' ', $displayedNameWithoutPrefix);
                                        
                    }
                    
                    if ($allPrefixedWithNumber && !$prefixedWithNumber) {
                        $allPrefixedWithNumber = false;
                    }
                    
                    if ($allPrefixedWithDate && !$prefixedWithDate) {
                        $allPrefixedWithDate = false;
                    }
                    
                    if ($allSuffixedWithVideoId && $videoId === false) {
                        $allSuffixedWithVideoId = false;
                    }
                    
                    if (isset($size)) {
                        if ($isDirectory) {
                            $size = false;
                            $sizeDisplay = false;
                        } else {
                            $sizeDisplay = fileSizeDisplay($size);
                        }   
                    } else {
                        $size = false;
                        $sizeDisplay = false;
                    }
                    
                    $fileNames[] = $item;
                    
                    $files[] = (object) [
                        
                        'isDirectory' => $isDirectory,
                        
                        'name' => $item,
                        'path' => $fullPath,
                        'url' => pathToURL($fullPath),
                        'displayedName' => $displayedName,
                        'suffix' => $suffix,

                        'prefixedWithNumber' => $prefixedWithNumber,                        
                        'number' => $number,

                        'prefixedWithEpisode' => $prefixedWithEpisode,                        
                        'episode' => $episode,
                        'season' => $season,
                        'seasonEpisode' => $seasonEpisode,

                        'prefixedWithDate' => $prefixedWithDate,
                        'date' => $date,
                        
                        'displayedNameWithoutPrefix' => $displayedNameWithoutPrefix,
                        
                        'size' => $size,
                        'sizeDisplay' => $sizeDisplay,
                        'videoId' => $videoId
                    ];                        
                    
                }
                
            }
            
            closedir($handle);
             
            array_multisort($fileNames, SORT_ASC, SORT_NATURAL, $files);
                            
            return (object) [
                'count' => count($fileNames),
                'fileNames' => $fileNames,
                'files' => $files,
                'prefixedWithNumber' => $allPrefixedWithNumber,
                'prefixedWithDate' => $allPrefixedWithDate,
                'suffixedWithVideoId' => $allSuffixedWithVideoId,
                'onlyDirectories' => $onlyDirectories
            ];
            
        }
        
    }
    
}

function directoryItemCount($directoryPath) {

    // Make sure directory path end with a separator
    if (!endsWith($directoryPath, DIRECTORY_SEPARATOR)) {
        $directoryPath .= DIRECTORY_SEPARATOR;
    }
    
    // debugLog('Counting items in directory at '.$directoryPath);
    
    if (is_dir($directoryPath)) {
        
        if ($handle = opendir($directoryPath)) {
            
            $fileNames = [];
            
            while (false !== ($item = readdir($handle))) {
        
                $fullPath = $directoryPath.$item;
                
                $isFile = is_file($fullPath);
                $isDirectory = is_dir($fullPath);
                
                if (!$isFile && !$isDirectory) {
                    
                    // debugLog('Excluding '.$fullPath.' because it does not exist');
                    
                } else if (!allowFileName($item)) {
                    
                    // debugLog('Excluding '.$fullPath.' based on file name');
                
                } else if (!SKIP_CHECKING_FILE_SIZES && $isFile && (($size = realFileSize($fullPath)) === false || $size <= 0)) {
                    
                    // debugLog('Excluding '.$fullPath.' because file size is too low');
                
                // Disabled for performance reasons    
                // } else if ($isDirectory && directoryItemCount($fullPath) == 0) {
                    
                    // // debugLog('Excluding '.$fullPath.' because it is an empty directory');
                    
                } else {
        
                    $fileNames[] = $item;
        
                }
                
            }
            
            $count = count($fileNames);
            
            // debugLog("Found $count item(s)");
            
            return $count;
                    
        } else {
            
            return 0;
            
        }
        
    } else {
        
        return 0;
        
    }
    
}

//! FILESYSTEM

function shortenPath($path) {
    
    $path = substr($path, strlen(ROOT_DIR_SANITIZED));
    
    // Make sure the requested path starts with a slash
    if (!startsWith($path, '/')) {
        $path = '/'.$path;        
    }
    
    return $path;
    
}

function pathToURL($path) {
    
    if (!is_dir($path) && USE_SYMLINK) {
        $pathPrefix = '/'.SYMBOLIC_LINK_NAME.'/';
    } else {
        $pathPrefix = '/';
    }
    
    $path = str_replace(ROOT_DIR_SANITIZED, '', $path);
    
    $path = rawurlencode($pathPrefix.$path);

    $path = str_replace('%2F', '/', $path);
    
    return $path;
    
}

function fileSizeDisplay($bytes, $decimals = 2) {
    
    $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)).' '.@$size[$factor];
    
}

function debugFileSizeDisplay($bytes, $decimals = 2) {
    
    return fileSizeDisplay($bytes, $decimals).' ('.$bytes.' bytes)';
    
}

function realFileSize($path) {
    
    $cachedSize = cachedSizeForPath($path);
    
    if ($cachedSize !== false) {
        
        return $cachedSize;
        
    } else {
        
        if (!file_exists($path)) {
            
            return false;
            
        } else {
            
            if (!($file = fopen($path, 'rb'))) {
                
                return false;
                
            } else {
            
                $size = filesize($path);
                    
                if ($size >= 0) {
                
                    // Check if it really is a small file (< 2 GB)
                    if (fseek($file, 0, SEEK_END) === 0) {
                        
                        // It really is a small file
                        fclose($file);
                        
                        cacheSizeForPath($path, $size);
                    
                        // debugLog('File size for '.$path.' is '.debugFileSizeDisplay($size));
                    
                        return $size;
                    }
                    
                }
                
                // Quickly jump the first 2 GB with fseek. After that fseek is not working on 32 bit php (it uses int internally)
                
                $size = PHP_INT_MAX;
                
                if (fseek($file, $size) !== 0) {
                    
                    // fseek failed
                    
                    fclose($file);
                    return false;
                    
                } else {
                    
                    // debugLog('realFileSize(): Need to read rest of file with fread() because fseek() only got us to '.fileSizeDisplay(PHP_INT_MAX));
                    
                    // fseek successful; position should now be at end of file
                    
                    $length = 1024 * 1024;
                    
                    while (!feof($file)) {
                        
                        // Read the file until end
                        $read = fread($file, $length);
                        $size = bcadd($size, $length);
                        
                    }
                
                    $size = bcsub($size, $length);
                    $size = bcadd($size, strlen($read));
                    
                    fclose($file);
                    
                    $size = floatval($size);
                    
                    cacheSizeForPath($path, $size);
                    
                    // debugLog('File size for '.$path.' is '.fileSizeDisplay($size).' ('.$size.' bytes)');
                     
                    return $size;
                    
                }
                
                
                
            }
    
        }
    
    }
    
}

function cacheSizeForPath($path, $size) {
    
    if (isset($_SESSION)) {
    
        // debugLog('Caching size for file at '.$path.': '.debugFileSizeDisplay($size));
    
        $shortPath = shortenPath($path);
                            
        if (!isset($_SESSION['file_sizes'])) {
            $_SESSION['file_sizes'] = [];                         
        }
        
        $_SESSION['file_sizes'][$shortPath] = [
            'modified_at' => filemtime($path),
            'size' => $size
        ];

    }
    
}

function cachedSizeForPath($path) {
    
    $shortPath = shortenPath($path);
    
    if (
        isset($_SESSION) && 
        isset($_SESSION['file_sizes']) && 
        isset($_SESSION['file_sizes'][$shortPath])
    ) {
        
        if (
            isset($_SESSION['file_sizes'][$shortPath]['modified_at']) &&
            isset($_SESSION['file_sizes'][$shortPath]['size'])
        ) {
      
            $cachedDate = $_SESSION['file_sizes'][$shortPath]['modified_at'];
      
            if ($cachedDate !=  filemtime($path)) {
        
                // debugLog('Clearing cached size for modified file at '.$path);
                
                // Clear cache
                unset($_SESSION['file_sizes'][$shortPath]);
        
                return false;

            } else {
          
                $cachedSize = floatval($_SESSION['file_sizes'][$shortPath]['size']);
                
                // debugLog('Retrieving cached size for file at '.$path.': '.debugFileSizeDisplay($cachedSize));
            
                return $cachedSize;
        
            }
        
        } else {

            // debugLog('Clearing cached size for file at '.$path. ' because the cache data structure is wrong.');

            // Clear cache
            unset($_SESSION['file_sizes'][$shortPath]);
        
            return false;
    
        }
    
    } else {
     
        return false;
           
    }

}

//! LOGGING

function debugLog($msg) {

    if (!is_string($msg)) {
        
        $msg = var_export($msg, true);
        
    }
        
    if (defined('DEBUG') && DEBUG === true) {

        file_put_contents(DEBUG_LOG_FILE, logTimestamp().' '.$msg."\n", FILE_APPEND);
            
    }
    
}

function logTimestamp() {
    
    $milliseconds = round(microtime(true) * 1000);
    
    return date('Y-m-d H:i:s.').str_pad($milliseconds % 1000, 3, '0', STR_PAD_LEFT);
    
}

//! UTILITIES

function startsWith($haystack, $needle) {
    
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
     
}

function endsWith($haystack, $needle) {

    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function dd($var) {
    
    var_dump($var);
    scriptExit();
    
}

//! PLATFORM

function initializePlatform() {
    
    // debugLog('User agent: '.$_SERVER['HTTP_USER_AGENT']);
    
    if (stripos($_SERVER['HTTP_USER_AGENT'],"iPod")) {
        $GLOBALS['platform'] = 'ios';    
    } else if (stripos($_SERVER['HTTP_USER_AGENT'],"iPhone")) {
        $GLOBALS['platform'] = 'ios';        
    } else if (stripos($_SERVER['HTTP_USER_AGENT'],"iPad")) {
        $GLOBALS['platform'] = 'ipados';        
    } else {
        $GLOBALS['platform'] = 'other-platform';
    }
    
}

function platform() {
    
    return $GLOBALS['platform'];
    
}

function isPlatform($platform) {
    
    return $platform == $GLOBALS['platform'];
    
}
